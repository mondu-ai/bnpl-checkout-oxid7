import { execFileSync } from 'node:child_process';
import { APIRequestContext, expect, Page } from '@playwright/test';

export const PRODUCT_ID = process.env.E2E_PRODUCT_ID ?? '066e3ce119c43c81cc0e46d4f1681eed';

// Mondu sandbox authorizes this buyer automatically (company "Mondu GmbH", email ac.good.*).
export const BUYER = {
  oxfname: 'Max',
  oxlname: 'Mustermann',
  oxcompany: process.env.E2E_BUYER_COMPANY ?? 'Mondu GmbH',
  oxstreet: 'Strassmannstr.',
  oxstreetnr: '45',
  oxzip: '10122',
  oxcity: 'Berlin',
  oxfon: '+493031196513',
};

type OrderStepOptions = {
  extra?: Record<string, string>;
  // runs after the article is in the basket, before the checkout steps
  onBasket?: (page: Page) => Promise<void>;
  // runs on the payment step, after the payment method is selected
  onPayment?: (page: Page) => Promise<void>;
};

export async function goToOrderStep(page: Page, paymentId: string, options: OrderStepOptions = {}) {
  await page.goto(`/index.php?cl=details&anid=${PRODUCT_ID}`);
  await page.click('#toBasket');
  await page.waitForLoadState('networkidle');

  if (options.onBasket) {
    await options.onBasket(page);
  }

  // guest checkout
  await page.goto('/index.php?cl=user');
  await page.getByRole('button', { name: 'Bestellen ohne Registrierung' }).click();
  await page.waitForLoadState('networkidle');

  await page.fill('#userLoginName', `ac.good.${Date.now()}@example.com`);
  for (const [field, value] of Object.entries({ ...BUYER, ...(options.extra ?? {}) })) {
    await page.fill(`[name="invadr[oxuser__${field}]"]`, value);
  }
  // the country select is wrapped by bootstrap-select, set the native value
  await page.selectOption('#invCountrySelect', { label: 'Deutschland' });
  await page.click('#userFormSubmit');
  await page.waitForURL(/cl=payment/);

  await page.check(`#payment_${paymentId}`);
  if (options.onPayment) await options.onPayment(page);
  await page.getByRole('button', { name: 'Weiter', exact: true }).click();
  await page.waitForURL(/cl=order/);

  const agb = page.locator('#checkAgbTop, #checkAgbBottom, input[name="ord_agb"]').first();
  if (await agb.isVisible().catch(() => false)) await agb.check();
}

export async function submitOrder(page: Page) {
  // the Apex confirm button sits outside the #orderConfirmAgbBottom form
  await page.getByRole('button', { name: 'Zahlungspflichtig bestellen' }).click();
}

/** Confirms the payment on the hosted checkout and waits until the buyer is back in the shop. */
export async function confirmHostedCheckoutOnce(page: Page) {
  await page.waitForURL('**mondu.ai/**', { timeout: 60_000 });
  const confirmButton = page.getByRole('button', { name: /zahlen mit|pay with|bestätigen|confirm|submit/i }).first();
  await confirmButton.waitFor({ state: 'visible', timeout: 30_000 });
  await confirmButton.click({ force: true });
  await page.waitForURL((url) => !url.hostname.includes('mondu.ai'), { timeout: 90_000 });
  await page.waitForLoadState('networkidle');
}

export async function confirmHostedCheckout(page: Page) {
  await confirmHostedCheckoutOnce(page);
  await page.waitForURL(/cl=thankyou/, { timeout: 90_000 });
}

/**
 * Reads the Mondu order uuid from the checkout controller before the page leaves for the hosted checkout.
 * `afterCreate` runs right after the Mondu order is created, before the buyer is redirected.
 */
export async function captureMonduOrder(page: Page, afterCreate?: () => void | Promise<void>) {
  const captured = { uuid: '' };
  await page.route(/cl=oemonducheckout/, async (route) => {
    const response = await route.fetch();
    captured.uuid = JSON.parse(await response.text()).token ?? '';
    if (afterCreate) await afterCreate();
    await route.fulfill({ response });
  });
  return captured;
}

// --- shop database (local docker setup), optional ---

export const dbConfigured = Boolean(process.env.E2E_DB_NAME);

export function sql(query: string): string {
  const container = process.env.E2E_DB_CONTAINER ?? 'db';
  const passwordFile = process.env.E2E_DB_PASSWORD_FILE ?? '/run/secrets/db_root_password';
  const database = process.env.E2E_DB_NAME;
  if (!database) throw new Error('E2E_DB_NAME is not set');
  return execFileSync(
    'docker',
    ['exec', '-i', container, 'sh', '-c', `mariadb -uroot -p"$(cat ${passwordFile})" -N ${database}`],
    // OXID runs without the strict SQL mode, its tables rely on that for columns without defaults
    { input: `SET SESSION sql_mode = '';\n${query}` },
  )
    .toString()
    .trim();
}

export function quote(value: string): string {
  return `'${value.replace(/'/g, "''")}'`;
}

/**
 * The OXID demo data has two shipping rules of the same priority for the standard shipping set (Standard 0 EUR,
 * Express 10 EUR). The database then decides which one comes first and that can change between two requests, so
 * the total on the order page can differ from the total when the buyer comes back from the hosted checkout.
 * Gives Express a lower priority so that Standard always wins. Returns a function that restores the demo data.
 */
export function makeShippingRulesUnambiguous(): () => void {
  const row = sql(
    `select s.oxid, e.oxid, e.oxsort from oxdelivery s, oxdelivery e where s.oxtitle = 'Standard' and e.oxtitle = 'Express'`,
  );
  if (!row) return () => {};
  const [standardId, expressId, expressSort] = row.split('\t');
  sql(`update oxdelivery set oxsort = (select m from (select max(oxsort) + 1 m from oxdelivery) t) where oxid = ${quote(expressId)}`);
  return () => sql(`update oxdelivery set oxsort = ${Number(expressSort)} where oxid = ${quote(expressId)}`);
}

// --- Mondu API (sandbox), optional ---

export const monduApiKey = process.env.E2E_MONDU_API_KEY ?? '';
const MONDU_API = process.env.E2E_MONDU_API_URL ?? 'https://api.demo.mondu.ai/api/v1';

export async function getMonduOrder(request: APIRequestContext, uuid: string) {
  const res = await request.get(`${MONDU_API}/orders/${uuid}`, { headers: { 'Api-Token': monduApiKey } });
  expect(res.ok(), `GET Mondu order ${uuid}`).toBeTruthy();
  return (await res.json()).order;
}

export async function getMonduInvoices(request: APIRequestContext, uuid: string) {
  const res = await request.get(`${MONDU_API}/orders/${uuid}/invoices`, { headers: { 'Api-Token': monduApiKey } });
  expect(res.ok(), `GET Mondu invoices of ${uuid}`).toBeTruthy();
  return (await res.json()).invoices;
}

// --- OXID admin ---

export async function adminLogin(page: Page): Promise<{ sid: string; stoken: string }> {
  await page.goto('/admin/');
  await page.fill('input[name="user"]', process.env.E2E_ADMIN_USER ?? 'admin@example.com');
  await page.fill('input[name="pwd"]', process.env.E2E_ADMIN_PASSWORD ?? '');
  await Promise.all([page.waitForNavigation(), page.click('input[type="submit"]')]);
  await page.waitForTimeout(1000);
  const frameUrl = page.frames().map((f) => f.url()).find((u) => u.includes('force_admin_sid'));
  expect(frameUrl, 'admin login').toBeTruthy();
  const params = new URL(frameUrl!).searchParams;
  return { sid: params.get('force_admin_sid')!, stoken: params.get('stoken')! };
}

export async function openOrderOverview(page: Page, admin: { sid: string; stoken: string }, orderId: string) {
  await page.goto(
    `/admin/index.php?cl=order_overview&oxid=${orderId}&force_admin_sid=${admin.sid}&stoken=${admin.stoken}&shp=1`,
  );
}

/** "Jetzt versenden" in the order overview: OXID marks the order as shipped and the module creates the Mondu invoice. */
export async function shipOrderInAdmin(page: Page, admin: { sid: string; stoken: string }, orderId: string) {
  await openOrderOverview(page, admin, orderId);
  await Promise.all([page.waitForNavigation(), page.evaluate(() => (document.getElementById('sendorder') as HTMLFormElement).submit())]);
}
