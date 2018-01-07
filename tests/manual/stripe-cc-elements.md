## Credit card checkout with Elements form

### Add a product to the cart

### Go to the checkout page

I see Stripe is available as a payment method.

### Fill in all requried details

### Select Stripe as payment method if not selected already

I see TEST MODE ENABLED in the description and I can use card number 4242424242424242
for testing.

### Fill the Credit or debit card field with 4242424242424242

The field asks me for MM / YY which is the expiration date.

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

I see the order status is processing/completed and from Order notes there's **Stripe charge
complete (Charge ID: xxx)**.
