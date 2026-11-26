# OXID eShop module for Mondu Payment

### Installation

Run in the root directory of the shop:

```
composer require mondu/bnpl-checkout-oxid7
```

Composer installs the module and its configuration, no separate install command is needed.

### Module configuration

1. After successful installation, Mondu BNPL module should be visible in admin dashboard (_Admin Dashboard -> Extensions -> Modules_)
2. Navigate to Mondu module
3. In module overview tab, activate Mondu module.

    > NOTE: Page in shop `page/checkout/order.tpl` should have smarty block `checkout_order_btn_confirm_bottom` that wraps up submission form with id `orderConfirmAgbBottom`. Module uses this block to hook into the `orderConfirmAgbBottom` form submit event: it creates the order on Mondu side and redirects the buyer to the Mondu hosted checkout.

    > NOTE: On module activation, three new payment methods (Mondu Invoice, Mondu SEPA and Mondu Installment) are added and activated

4. Navigate to Mondu module settings page (_Extensions -> Modules -> Mondu -> Settings tab_)

```
    1. Enter API key provided by Mondu
    2. Check 'Sandbox mode' checkbox for testing in sandbox environment
    3. Activate Mondu logging (if you want to allow logging of all errors to oemondu.log file, located inside your log directory)
```

5. Save settings

    > NOTE: In case any issues happen during module configuration, a proper error message will be shown.

    > NOTE: When a new API key is saved, the module registers the Mondu webhooks for the shop address the admin is opened on (`<shop address>?cl=oemonduwebhooks`). Open the admin on the shop's public domain when you save the key. Webhooks that already exist for exactly this address are not registered again. A webhook on another variant of the address (http instead of https, with or without `www.`) does not count, the module registers the exact address.

6. Assign desired countries to Mondu Payment methods (_Admin Dashboard -> Shop Settings -> Payment Methods -> `<desired Mondu payment method>` -> Country -> Assign Countries_)

> NOTE: In case no country is assigned to payment method, it will not be visible in checkout flow

7. Assign desired payment methods to Shop shipping methods (_Admin Dashboard -> Shop Settings -> Shipping Methods -> `<desired shipping method>` -> Payment -> Assign Payment Methods_)

> NOTE: In case payment method is not assigned to any shipping method, it will not be visible in checkout flow
