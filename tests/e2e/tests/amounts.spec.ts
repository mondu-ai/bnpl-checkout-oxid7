import { test, expect, Page, APIRequestContext } from '@playwright/test';
import {
  adminLogin,
  captureMonduOrder,
  confirmHostedCheckout,
  confirmHostedCheckoutOnce,
  dbConfigured,
  getMonduInvoices,
  getMonduOrder,
  goToOrderStep,
  makeShippingRulesUnambiguous,
  monduApiKey,
  openOrderOverview,
  quote,
  shipOrderInAdmin,
  sql,
  submitOrder,
} from './helpers';

/**
 * The Mondu order must have the same amount as the OXID order, with shipping, payment fees, vouchers and
 * discounts, also when OXID calculates the order differently after the buyer comes back from the hosted
 * checkout. Otherwise Mondu rejects the invoice.
 *
 * Needs the shop database (E2E_DB_NAME) to set up shipping, fees, vouchers and discounts, the sandbox API key
 * of the shop (E2E_MONDU_API_KEY) and the admin login (E2E_ADMIN_USER / E2E_ADMIN_PASSWORD).
 */

test.describe.configure({ mode: 'serial' });
test.skip(!dbConfigured || !monduApiKey || !process.env.E2E_ADMIN_PASSWORD, 'needs E2E_DB_NAME, E2E_MONDU_API_KEY and E2E_ADMIN_PASSWORD');

const PAYMENT_ID = 'oxmondu_invoice';
const DISCOUNT_ID = 'mondue2ediscount';
const SERIES_ID = 'mondue2eseries';
const backup: Record<string, string> = {};
let restoreShippingRules = () => {};

function setShipping(amount: number) {
  sql(`update oxdelivery set oxaddsum = ${amount} where oxid = ${quote(backup.standardId)}`);
}

function setPaymentFee(amount: number) {
  sql(`update oxpayments set oxaddsum = ${amount}, oxaddsumtype = 'abs' where oxid = '${PAYMENT_ID}'`);
}

function setDiscountActive(active: boolean) {
  sql(`update oxdiscount set oxactive = ${active ? 1 : 0} where oxid = '${DISCOUNT_ID}'`);
}

// OXID EE assigns discounts and voucher series to shops through mapping tables
function assignToShop(table: 'oxdiscount' | 'oxvoucherseries', oxid: string) {
  if (!sql(`show tables like '${table}2shop'`)) return;
  sql(`insert ignore into ${table}2shop (oxshopid, oxmapobjectid) select 1, oxmapid from ${table} where oxid = ${quote(oxid)}`);
}

function removeFromShop(table: 'oxdiscount' | 'oxvoucherseries', oxid: string) {
  if (!sql(`show tables like '${table}2shop'`)) return;
  sql(`delete m from ${table}2shop m join ${table} t on t.oxmapid = m.oxmapobjectid where t.oxid = ${quote(oxid)}`);
}

function newVoucherCode(): string {
  const code = `MONDUE2E${Date.now()}`;
  sql(`insert into oxvouchers (oxid, oxvouchernr, oxvoucherserieid) values (md5(${quote(code)}), ${quote(code)}, '${SERIES_ID}')`);
  return code;
}

test.beforeAll(() => {
  // the invoice number is the OXID order number and must be unique per Mondu merchant; a sandbox merchant is
  // often shared by several test shops, so continue the order numbers from a value no other shop uses
  sql(`update oxcounters set oxcount = greatest(oxcount, ${Math.floor(Date.now() / 1000)}) where oxident = 'oxOrder'`);

  backup.standardId = sql(`select oxid from oxdelivery where oxtitle = 'Standard'`);
  backup.standardSum = sql(`select oxaddsum from oxdelivery where oxtitle = 'Standard'`);
  backup.paymentFee = sql(`select oxaddsum from oxpayments where oxid = '${PAYMENT_ID}'`);
  restoreShippingRules = makeShippingRulesUnambiguous();

  removeFromShop('oxdiscount', DISCOUNT_ID);
  sql(`delete from oxdiscount where oxid = '${DISCOUNT_ID}'`);
  sql(`insert into oxdiscount (oxid, oxshopid, oxactive, oxtitle, oxamount, oxamountto, oxpriceto, oxprice, oxaddsumtype, oxaddsum, oxsort)
       select '${DISCOUNT_ID}', 1, 0, 'Mondu E2E 10%', 1, 999999, 999999, 0, '%', 10, max(oxsort) + 1 from oxdiscount`);
  sql(`delete from oxvouchers where oxvoucherserieid = '${SERIES_ID}'`);
  removeFromShop('oxvoucherseries', SERIES_ID);
  sql(`delete from oxvoucherseries where oxid = '${SERIES_ID}'`);
  sql(`insert into oxvoucherseries (oxid, oxshopid, oxserienr, oxdiscount, oxdiscounttype, oxbegindate, oxenddate,
         oxallowsameseries, oxallowotherseries, oxallowuseanother, oxminimumvalue)
       values ('${SERIES_ID}', 1, 'MONDUE2E', 5, 'absolute', '2020-01-01', '2099-01-01', 1, 1, 1, 0)`);
  assignToShop('oxdiscount', DISCOUNT_ID);
  assignToShop('oxvoucherseries', SERIES_ID);
});

test.afterAll(() => {
  if (!backup.standardId) return;
  setShipping(Number(backup.standardSum));
  restoreShippingRules();
  setPaymentFee(Number(backup.paymentFee));
  removeFromShop('oxdiscount', DISCOUNT_ID);
  sql(`delete from oxdiscount where oxid = '${DISCOUNT_ID}'`);
  sql(`delete from oxvouchers where oxvoucherserieid = '${SERIES_ID}'`);
  removeFromShop('oxvoucherseries', SERIES_ID);
  sql(`delete from oxvoucherseries where oxid = '${SERIES_ID}'`);
});

test.beforeEach(() => {
  setShipping(10);
  setPaymentFee(0);
  setDiscountActive(false);
});

type OxidOrder = { id: string; total: number; delivery: number; payment: number; voucher: number; discount: number; folder: string };

function loadOxidOrder(uuid: string): OxidOrder {
  const row = sql(
    `select o.oxid, o.oxtotalordersum, o.oxdelcost, o.oxpaycost, o.oxvoucherdiscount, o.oxdiscount, o.oxfolder
     from oxorder o join oemondu_orders m on m.oxid_order_id = o.oxid where m.order_uuid = ${quote(uuid)}`,
  ).split('\t');
  expect(row[0], `OXID order for Mondu order ${uuid}`).toBeTruthy();
  return {
    id: row[0],
    total: Math.round(Number(row[1]) * 100),
    delivery: Math.round(Number(row[2]) * 100),
    payment: Math.round(Number(row[3]) * 100),
    voucher: Math.round(Number(row[4]) * 100),
    discount: Math.round(Number(row[5]) * 100),
    folder: row[6],
  };
}

async function placeMonduOrder(
  page: Page,
  options: { voucher?: string; afterMonduOrderCreated?: () => void; onPayment?: (page: Page) => Promise<void> } = {},
) {
  const mondu = await captureMonduOrder(page, options.afterMonduOrderCreated);
  await goToOrderStep(page, PAYMENT_ID, {
    onPayment: options.onPayment,
    onBasket: options.voucher
      ? async (basketPage) => {
          // the same request as the coupon form in the checkout sidebar of the Apex theme, with its CSRF token
          const stoken = await basketPage.locator('input[name="stoken"]').first().inputValue();
          await basketPage.goto(
            `/index.php?cl=basket&fnc=addVoucher&stoken=${stoken}&voucherNr=${encodeURIComponent(options.voucher!)}`,
          );
        }
      : undefined,
  });
  if (options.voucher) {
    await expect(page.getByText(`(${options.voucher})`).first(), 'voucher applied').toBeVisible();
  }
  await submitOrder(page);
  await confirmHostedCheckout(page);
  expect(mondu.uuid).toMatch(/^[0-9a-f-]{36}$/);
  return mondu.uuid;
}

/** Ships the order in the OXID admin and checks that Mondu accepted the invoice with the order total. */
async function expectInvoiceAccepted(page: Page, request: APIRequestContext, uuid: string, order: OxidOrder) {
  const admin = await adminLogin(page);
  await openOrderOverview(page, admin, order.id);
  await expect(page.locator('.errorbox')).toHaveCount(0);

  await shipOrderInAdmin(page, admin, order.id);
  await expect(page.locator('.errorbox')).toHaveCount(0);

  const invoices = await getMonduInvoices(request, uuid);
  expect(invoices, 'Mondu invoices').toHaveLength(1);
  expect(invoices[0].gross_amount_cents).toBe(order.total);
  expect(sql(`select count(*) from oemondu_invoices where invoice_id = (select oxordernr from oxorder where oxid = ${quote(order.id)})`)).toBe('1');
  expect((await getMonduOrder(request, uuid)).state).toBe('shipped');
}

test('order with shipping costs: Mondu gets the full order total and accepts the invoice', async ({ page, request }) => {
  const uuid = await placeMonduOrder(page);

  const order = loadOxidOrder(uuid);
  expect(order.delivery).toBe(1000);
  const monduOrder = await getMonduOrder(request, uuid);
  expect(monduOrder.real_price_cents).toBe(order.total);
  expect(monduOrder.lines[0].shipping_price_cents).toBe(1000);
  expect(monduOrder.adjusted, 'no adjustment needed').toBe(false);
  expect(order.folder).toBe('ORDERFOLDER_NEW');

  await expectInvoiceAccepted(page, request, uuid, order);
});

test('order with payment fee, voucher and discount: Mondu gets the order total and accepts the invoice', async ({
  page,
  request,
}) => {
  setPaymentFee(2.5);
  setDiscountActive(true);
  const uuid = await placeMonduOrder(page, {
    voucher: newVoucherCode(),
    onPayment: async (paymentPage) => {
      // the fee next to the Mondu payment method: 2,50 gross, or 2,10 net plus 0,40 VAT depending on the shop setting
      const option = paymentPage.locator('.well', { has: paymentPage.locator(`#payment_${PAYMENT_ID}`) });
      const text = (await option.innerText()).replace(/\s+/g, ' ');
      expect(text).toMatch(/\(2,50 €\)|\(2,10 € [^()]+ 0,40 €\)/);
      expect(text).not.toContain('1,00 €');
    },
  });

  const order = loadOxidOrder(uuid);
  expect(order.payment).toBe(250);
  expect(order.voucher).toBe(500);
  expect(order.discount).toBeGreaterThan(0);
  const monduOrder = await getMonduOrder(request, uuid);
  expect(monduOrder.real_price_cents).toBe(order.total);
  expect(monduOrder.lines[0].buyer_fee_cents).toBe(250);
  expect(monduOrder.adjusted, 'no adjustment needed').toBe(false);
  expect(order.folder).toBe('ORDERFOLDER_NEW');

  await expectInvoiceAccepted(page, request, uuid, order);
});

for (const [label, before, after] of [
  ['cheaper', 10, 0],
  ['more expensive', 0, 10],
] as const) {
  test(`shipping gets ${label} while the buyer is on the hosted checkout: the buyer confirms the new total`, async ({
    page,
    request,
  }) => {
    setShipping(before);
    const mondu = await captureMonduOrder(page, () => setShipping(after));
    await goToOrderStep(page, PAYMENT_ID);
    await submitOrder(page);
    await confirmHostedCheckoutOnce(page);

    // back on the order page with the new total, no order placed, the first Mondu order canceled
    await page.waitForURL(/cl=order/, { timeout: 90_000 });
    expect(page.url()).not.toContain('cl=thankyou');
    await expect(page.getByText(/Gesamtbetrag Ihrer Bestellung hat sich geändert|total of your order has changed/)).toBeVisible();
    const firstUuid = mondu.uuid;
    expect(firstUuid).toMatch(/^[0-9a-f-]{36}$/);
    expect(sql(`select count(*) from oemondu_orders where order_uuid = ${quote(firstUuid)}`), 'no OXID order').toBe('0');
    expect((await getMonduOrder(request, firstUuid)).state).toBe('canceled');

    // the buyer confirms again: a new Mondu order with the new total
    const agb = page.locator('#checkAgbTop, #checkAgbBottom, input[name="ord_agb"]').first();
    if (await agb.isVisible().catch(() => false)) await agb.check();
    await submitOrder(page);
    await confirmHostedCheckout(page);
    expect(mondu.uuid).not.toBe(firstUuid);

    const order = loadOxidOrder(mondu.uuid);
    expect(order.delivery).toBe(after * 100);
    const monduOrder = await getMonduOrder(request, mondu.uuid);
    expect(monduOrder.real_price_cents).toBe(order.total);
    expect(monduOrder.adjusted, 'no adjustment needed').toBe(false);
    expect(order.folder).toBe('ORDERFOLDER_NEW');

    await expectInvoiceAccepted(page, request, mondu.uuid, order);
  });
}

test('the admin warns when the Mondu order does not match the OXID order', async ({ page, request }) => {
  // Mondu sandbox accepts every adjustment, so the mismatch is made in the OXID order after it is placed
  const uuid = await placeMonduOrder(page);
  const order = loadOxidOrder(uuid);
  expect((await getMonduOrder(request, uuid)).real_price_cents).toBe(order.total);

  const changedTotal = order.total + 500;
  sql(`update oxorder set oxdelcost = oxdelcost + 5, oxtotalordersum = ${changedTotal / 100} where oxid = ${quote(order.id)}`);

  const admin = await adminLogin(page);
  await openOrderOverview(page, admin, order.id);
  const euro = (cents: number) => (cents / 100).toLocaleString('de-DE', { minimumFractionDigits: 2 });
  const warning = page.locator('.errorbox');
  await expect(warning).toContainText('Mondu');
  await expect(warning).toContainText(euro(order.total));
  await expect(warning).toContainText(euro(changedTotal));
});

test('OXID does not place the order after the hosted checkout: the Mondu order is canceled', async ({ page, request }) => {
  // the Mondu payment method is switched off while the buyer is on the hosted checkout, so OXID refuses the order
  const restore = sql(`select oxactive from oxpayments where oxid = '${PAYMENT_ID}'`);
  const mondu = await captureMonduOrder(page, () => {
    sql(`update oxpayments set oxactive = 0 where oxid = '${PAYMENT_ID}'`);
  });

  try {
    await goToOrderStep(page, PAYMENT_ID);
    await submitOrder(page);
    await confirmHostedCheckoutOnce(page);

    // back in the shop, but not on the thank-you page
    await page.waitForURL((url) => !url.hostname.includes('mondu.ai'), { timeout: 90_000 });
    await page.waitForLoadState('networkidle');
    expect(page.url()).not.toContain('cl=thankyou');
  } finally {
    sql(`update oxpayments set oxactive = ${Number(restore)} where oxid = '${PAYMENT_ID}'`);
  }

  expect(mondu.uuid).toMatch(/^[0-9a-f-]{36}$/);
  expect(sql(`select count(*) from oemondu_orders where order_uuid = ${quote(mondu.uuid)}`), 'no OXID order').toBe('0');
  expect((await getMonduOrder(request, mondu.uuid)).state).toBe('canceled');
});
