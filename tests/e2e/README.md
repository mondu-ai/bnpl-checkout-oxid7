# Mondu OXID — E2E

Playwright end-to-end check of the Mondu checkout in the OXID storefront.

## What it asserts

1. The order confirm step of a Mondu payment loads no widget SDK and renders no widget container.
2. Submitting a Mondu invoice order redirects to the Mondu hosted checkout, and confirming there
   returns the buyer to the OXID thank-you page.
3. A non-Mondu payment (prepayment) places the order without any call to the Mondu checkout controller.

## Preconditions on the target shop

- OXID 7 (Apex theme) with demo data and the Mondu module active, sandbox mode on, a valid sandbox API key.
- The Mondu payment methods and prepayment are assigned to the standard shipping set and to Germany.
- `E2E_PRODUCT_ID` is an active, in-stock article (defaults to a demo-data article).

## Run

```bash
npm install
npx playwright install chromium
E2E_BASE_URL=http://localhost:18007 npx playwright test
```
