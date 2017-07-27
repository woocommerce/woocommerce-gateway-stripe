## Acivate

```
start_path: /wp-admin/plugins.php?plugin_status=search&s=stripe
```

You can skip this test if plugin already activated.

### Click Activate under WooCommerce Stripe Gateway plugin.

I see **Plugin activated** notice.

## Enable the Stripe Gateway Test Mode

To be able to use and test Stripe, you need to enable Stripe and set required
settings.

### Go to WooCommerce > Settings > Checkout

I see Stripe, Stripe Bancontact, Stripe Sofort, Stripe Giropay, Stripe iDeal,
Stripe Alipay, Stripe SEPA, and Stripe Bitcoin submenus.

### Click Stripe submenu

I see settings form.

### Click Enable Stripe Checkbox

### Fill the Test Publishable Key text field

### Fill the Test Secret Key text field

### Click Save changes button at the bottom

I see **Your settings have been saved** notice.

## Test Checkout with Stripe in Test Mode While Logged In

To be able to test checkout flow with Stripe in Test Mode, you will need
test product, add the product to the cart, and then checkout using Stripe
as the payment method.

### Create simple virtual product with price $10

### View the product

### Click Add to cart

### Go to the checkout page

You see Stripe is available as a payment method.

### I fill Billing details

### Select Stripe as payment method if not selected already

I see TEST MODE ENABLED in the description and I can use card number 4242424242424242
for testing.

### Fill the Credit or debit card field with 4242424242424242

The field asks me for MM / YY which is a the expiration date.

### Fill the MM / YY with invalid value

Using past MM / YY (e.g. 10 16).

I see the MM / YY text becomes red and *Your card's expiration year is in the past.*
red notice at the bottom of the the Credit or debit card field.

### Fill the MM / YY with valid value month and year in the future

Using a valid expiration date in the future.

It asks me for CVC now.

### Fill the CVC with non-numeric

It doesn't allow me to fill CVC with non-numeric value.

### Fill the CVC with 123

### Click Place order

It redirects me to Order received page. I can see the order number and Stripe
as the payment method.

### Go to the admin dashboard and click WooCommerce

I see the order number I created from checkout.

### Click the order number

I see the order status is processing and from Order notes there's **Stripe charge
complete (Charge ID: xxx)**.
