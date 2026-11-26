# Mondu OXID — E2E

Playwright end-to-end check of the Mondu checkout in the OXID storefront.

## What it asserts

`tests/checkout.spec.ts`:

1. The order confirm step of a Mondu payment loads no widget SDK and renders no widget container.
2. Submitting a Mondu invoice order redirects to the Mondu hosted checkout, and confirming there
   returns the buyer to the OXID thank-you page.
3. A non-Mondu payment (prepayment) places the order without any call to the Mondu checkout controller.
4. A Mondu order with a VAT ID from the checkout goes through.

`tests/amounts.spec.ts` (the Mondu order amount matches the OXID order, the invoice is accepted):

1. Order with shipping costs: Mondu gets the full total, the invoice sent from the OXID admin is accepted.
2. Order with payment fee, voucher and discount: same.
3. Shipping gets cheaper while the buyer is on the hosted checkout: the Mondu order is adjusted.
4. Shipping gets more expensive while the buyer is on the hosted checkout: the Mondu order is adjusted, or
   the OXID order is moved to the problems folder with a warning in the admin.
5. The admin order overview warns when the Mondu order and the OXID order differ.

## Preconditions on the target shop

- OXID 7 (Apex theme) with demo data and the Mondu module active, sandbox mode on, a valid sandbox API key.
- The Mondu payment methods and prepayment are assigned to the standard shipping set and to Germany.
- `E2E_PRODUCT_ID` is an active, in-stock article (defaults to a demo-data article of OXID 7.2;
  on OXID 7.6 demo data use `7c82af3a29fb1d0f644316e2da9c68c0`).

`tests/amounts.spec.ts` sets up shipping costs, a payment fee, a discount and a voucher series in the shop
database and restores them afterwards, so it needs:

| Variable | Meaning |
|---|---|
| `E2E_DB_NAME` | shop database |
| `E2E_DB_CONTAINER` | MariaDB docker container (default `db`) |
| `E2E_DB_PASSWORD_FILE` | root password file inside that container (default `/run/secrets/db_root_password`) |
| `E2E_MONDU_API_KEY` | the sandbox API key configured in the shop |
| `E2E_ADMIN_USER` / `E2E_ADMIN_PASSWORD` | OXID admin login (user default `admin@example.com`) |

Without them the amounts tests are skipped. They continue the OXID order numbers from the current time,
because the invoice number must be unique per Mondu merchant and a sandbox merchant is often shared.

## Run

```bash
npm install
npx playwright install chromium
E2E_BASE_URL=http://localhost:18007 npx playwright test
```
