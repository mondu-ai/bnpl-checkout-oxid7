# Integration tests

PHPUnit tests that run inside an installed OXID 7 shop with this module and the demo data. They build real
OXID baskets and orders (shipping, payment fee, voucher, discount, two VAT rates) and check that the
Mondu payload adds up to the OXID total, and how the Mondu order is synced when OXID places the order.

The tests change shop data (payment costs, a voucher series, a discount, a test user, VAT of one article)
and restore it. Use a test shop.

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
