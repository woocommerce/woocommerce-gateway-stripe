## Acivate

```
start_path: /wp-admin/plugins.php?plugin_status=search&s=stripe
```

You can skip this test if plugin already activated.

### Click Activate under WooCommerce Stripe Gateway plugin.

I see **Plugin activated** notice.

## Dismiss feature and ssl notices

When Stripe is active, there may be notices such as new features or ssl not detected. These notices should be dimissible.

### Click on the dismiss/close icon/link in each notice

Observe that on next page load, these notices should be gone.

## Enable the Stripe Gateway Test Mode

To be able to use and test Stripe, you need to enable Stripe and set required
settings.

### Go to WooCommerce > Settings > Checkout

I see Stripe, Stripe Bancontact, Stripe SOFORT, Stripe Giropay, Stripe iDeal, Stripe P24, Stripe Alipay and Stripe SEPA Direct Debit submenus.

### Click Stripe submenu

I see settings form.

### Click Enable Stripe Checkbox

### Fill the Test Publishable Key text field

### Fill the Test Secret Key text field

### Click Save changes button at the bottom

I see **Your settings have been saved** notice.

## Test Checkout with Stripe in Test Mode

To be able to test checkout flow with Stripe in Test Mode, you will need
test product, add the product to the cart, and then checkout using Stripe
as the payment method.

## Credit card checkout with Elements form

[stripe-cc-elements.md](stripe-cc-elements.md)

## Credit card checkout with legacy form

[stripe-cc.md](stripe-cc.md)

## Stripe Checkout/Modal

[stripe-checkout.md](stripe-checkout.md)

## Stripe Bancontact

[stripe-bancontact.md](stripe-bancontact.md)

## Stripe SoFort

[stripe-sofort.md](stripe-sofort.md)

## Stripe Giropay

[stripe-giropay.md](stripe-giropay.md)

## Stripe iDeal

[stripe-ideal.md](stripe-ideal.md)

## Stripe P24

[stripe-p24.md](stripe-p24.md)

## Stripe Alipay

[stripe-alipay.md](stripe-alipay.md)

## Stripe SEPA Direct Debit

[stripe-sepa.md](stripe-sepa.md)

## Stripe with Subscriptions

[stripe-with-subscriptions.md](stripe-with-subscriptions.md)
