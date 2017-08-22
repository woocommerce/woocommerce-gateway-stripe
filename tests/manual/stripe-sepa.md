## Stripe SEPA Direct Debit

### Go to WooCommerce > Settings > Checkout > Stripe SEPA Direct Debit

### Click on Enable and save

### Click Save changes button at the bottom

I see **Your settings have been saved** notice.

Be sure to also copy the webhook endpoint provided on the settings page and add it to your Stripe Dashboard API/Webhook setting.

### Go to WooCommerce > Settings > General

### Set currency to Euro

### Click Save changes button at the bottom

I see **Your settings have been saved** notice.

### Add a product to cart

### Go to the checkout page

I see Stripe SEPA Direct Debit is available as a payment method.

### Fill in all required details

### Select Stripe SEPA Direct Debit as payment method if not selected already

### Enter in any account name

### Copy and paste the IBAN number in the payment method description

### Click on **Place order** button

It redirects me to Order received page. I can see the order number and Stripe
as the payment method.

### Go to the admin dashboard and click WooCommerce

I see the order number I created from checkout.

### Click the order number

I see the order status is processing/completed and from Order notes there's **Stripe charge
complete (Charge ID: xxx)**. I understand I may not see the order status changed to processing/completed right away as some payment methods are asynchronous and can take a little time to trigger. But eventually I see the change.
