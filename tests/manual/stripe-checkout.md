## Stripe Checkout/Modal

### Go to WooCommerce > Settings > Payments > Stripe

#### Check box for "Stripe Modal Checkout"

#### Click Save changes button at the bottom

I see **Your settings have been saved** notice.

### Add a product to cart

### Go to the checkout page

I see Stripe is available as a payment method.

#### Fill in all required details

#### Select Stripe as payment method if not selected already

I see TEST MODE ENABLED in the description and I can use card number 4242424242424242
for testing.

#### Click on **Continue to payment** button

I see a new page "Pay for order" with details of my order.

I see the option to save payment information if this setting has been enabled in WooCommerce > Payments > Stripe settings

I see a "Place Order" button.

### Click on **Place Order** button

I see a popup modal appear in the middle of the screen prompting for credit card information and a button to pay

#### Fill the Credit or debit card field with 4242424242424242

The field asks me for MM / YY which is the expiration date.

#### Fill the MM / YY with valid value month and year in the future

Using a valid expiration date in the future.

#### Fill the CVC with 123

### Click Pay

It redirects me to Order received page. I can see the order number and Stripe as the payment method.

### Go to the admin dashboard and click WooCommerce > Orders

I see the order number I created from checkout.

### Click the order number

I see the order status is processing/completed and from Order notes there's **Stripe charge complete (Charge ID: xxx)**.
