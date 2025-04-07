# OXID eShop module for Mondu Payment

### Installation

##### Manual installation

1. Switch to the shop modules directory (`<shop_directory>/source/modules`)

2. Create `mondu/bnpl-checkout-oxid` directory inside modules directory

3. Download the latest plugin release zip file from [Releases](https://github.com/mondu-ai/bnpl-checkout-oxid/releases)

4. Copy unzipped content to `<shop_directory>/source/modules/mondu/bnpl-checkout-oxid`

    > NOTE: After this step, you should be able to see all module code inside of this directory

5. Navigate back to root directory of shop (`shop_directory`)
6. Install module configuration using following command

```
vendor/bin/oe-console oe:module:install-configuration source/modules/mondu/bnpl-checkout-oxid
```

7. Register module package in project composer.json (in root directory of shop)

```
composer config repositories.mondu/bnpl-checkout-oxid7 path source/modules/mondu/bnpl-checkout-oxid

composer require mondu/bnpl-checkout-oxid7
```

### Module configuration

1. After successful installation, Mondu BNPL module should be visible in admin dashboard (_Admin Dashboard -> Extensions -> Modules_)
2. Navigate to Mondu module
3. In module overview tab, activate Mondu module.

    > NOTE: Page in shop `page/checkout/order.tpl` should have smarty block `checkout_order_btn_confirm_bottom` that wraps up submission form with id `orderConfirmAgbBottom`. Module uses this block in order to load Mondu widget, that is responsible for order creation on Mondu side. Widget will be opened on `orderConfirmAgbBottom` form submit event.

    > NOTE: On module activation, three new payment methods (Mondu Invoice, Mondu SEPA and Mondu Installment) are added and activated

4. Navigate to Mondu module settings page (_Extensions -> Modules -> Mondu -> Settings tab_)

```
    1. Enter API key provided by Mondu
    2. Check 'Sandbox mode' checkbox for testing in sandbox environment
    3. Activate Mondu logging (if you want to allow logging of all errors to oemondu.log file, located inside your log directory)
```

5. Save settings

    > NOTE: In case any issues happen during module configuration, a proper error message will be shown.

6. Assign desired countries to Mondu Payment methods (_Admin Dashboard -> Shop Settings -> Payment Methods -> `<desired Mondu payment method>` -> Country -> Assign Countries_)

> NOTE: In case no country is assigned to payment method, it will not be visible in checkout flow

7. Assign desired payment methods to Shop shipping methods (_Admin Dashboard -> Shop Settings -> Shipping Methods -> `<desired shipping method>` -> Payment -> Assign Payment Methods_)

> NOTE: In case payment method is not assigned to any shipping method, it will not be visible in checkout flow
