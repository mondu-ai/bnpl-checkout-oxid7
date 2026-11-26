import { test, expect } from '@playwright/test';
import { goToOrderStep, submitOrder } from './helpers';

/**
 * The widget is sunset: the order confirm step must not load the Mondu widget
 * SDK, a Mondu order must redirect to the hosted checkout and come back to the
 * thank-you page, and other payment methods must still place orders normally.
 */

test('Mondu order goes through hosted checkout without loading the widget SDK', async ({ page }) => {
  const widgetRequests: string[] = [];
  page.on('request', (request) => {
    const url = new URL(request.url());
    if (url.hostname.includes('mondu') && url.pathname.endsWith('/widget.js')) widgetRequests.push(url.href);
  });

  await goToOrderStep(page, 'oxmondu_invoice');

  await expect(page.locator('#mondu-checkout-input')).toHaveCount(1);
  await expect(page.locator('#mondu-checkout-widget')).toHaveCount(0);
  expect(await page.evaluate(() => typeof (window as any).widgetUrl)).toBe('undefined');

  await submitOrder(page);
  await page.waitForURL('**mondu.ai/**', { timeout: 60_000 });

  const confirmButton = page.getByRole('button', { name: /zahlen mit|pay with|bestätigen|confirm|submit/i }).first();
  await confirmButton.waitFor({ state: 'visible', timeout: 30_000 });
  await confirmButton.click({ force: true });

  await page.waitForURL(/cl=thankyou/, { timeout: 90_000 });
  expect(widgetRequests).toEqual([]);
});

test('non-Mondu payment still places the order without Mondu interception', async ({ page }) => {
  const monduCalls: string[] = [];
  page.on('request', (request) => {
    if (request.url().includes('cl=oemonducheckout')) monduCalls.push(request.url());
  });

  await goToOrderStep(page, 'oxidpayadvance');

  await expect(page.locator('#mondu-checkout-input')).toHaveCount(0);

  await submitOrder(page);
  await page.waitForURL(/cl=thankyou/, { timeout: 60_000 });
  expect(monduCalls).toEqual([]);
});

test('Mondu order with a VAT ID from the checkout goes through', async ({ page }) => {
  // the VAT ID is sent as buyer.vat_number; the API rejects the order if the payload is invalid
  await goToOrderStep(page, 'oxmondu_invoice', { extra: { oxustid: 'DE123456789' } });
  await submitOrder(page);
  await page.waitForURL('**mondu.ai/**', { timeout: 60_000 });

  const confirmButton = page.getByRole('button', { name: /zahlen mit|pay with|bestätigen|confirm|submit/i }).first();
  await confirmButton.waitFor({ state: 'visible', timeout: 30_000 });
  await confirmButton.click({ force: true });

  await page.waitForURL(/cl=thankyou/, { timeout: 90_000 });
});
