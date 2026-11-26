# Integration tests

PHPUnit tests that run inside an installed OXID 7 shop with this module and the demo data. They build real
OXID baskets and orders and check that the Mondu payload adds up to the OXID total: shipping, payment fee,
voucher, percent, absolute and article discounts, free bundle article, gift wrapping and greeting card, two VAT
rates, net prices (B2B), costs with VAT on top and another currency. They also cover how the Mondu order is
synced or canceled when the buyer comes back from the hosted checkout.

The tests change shop data (payment costs, a voucher series, discounts, wrappings, a test user, the VAT of
one article) and restore it. Use a test shop.

```bash
# in this repository
composer install

# in the shop container, with this repository mounted at /module
OXID_BOOTSTRAP=/var/www/html/source/bootstrap.php php /module/vendor/bin/phpunit -c /module/phpunit.xml.dist
```

| Variable | Meaning |
|---|---|
| `OXID_BOOTSTRAP` | path to `source/bootstrap.php` of the shop, without it the tests are skipped |
| `E2E_PRODUCT_ID` | article of the basket (default: an article of the OXID 7.6 demo data) |
| `E2E_SECOND_PRODUCT_ID` | article that gets a 7 % VAT rate for the two-rates cases |
