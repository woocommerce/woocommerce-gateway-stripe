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

## Test Checkout with Stripe Checkout/Modal enabled in Test Mode While Logged In

To be able to test checkout flow with Stripe Checkout JS in Test Mode, you will need
test product, add the product to the cart, and then checkout using Stripe
as the payment method.

### Go to WooCommerce > Settings > Checkout > Stripe

### Enable Stripe Checkout setting and save

### Click Save changes button at the bottom

I see **Your settings have been saved** notice.

### Create simple virtual product with price $10

### View the product

### Click Add to cart

### Go to the checkout page

I see Stripe is available as a payment method.

### Fill in all requried details

### Select Stripe as payment method if not selected already

I see TEST MODE ENABLED in the description and I can use card number 4242424242424242
for testing.

### Click on **Continue to payment** button

I see a popup modal appear in the middle of the screen prompting for credit card information.

### Fill the Credit or debit card field with 4242424242424242

The field asks me for MM / YY which is the expiration date.

### Fill the MM / YY with valid value month and year in the future

Using a valid expiration date in the future.

### Fill the CVC with 123

### Click Pay

It redirects me to Order received page. I can see the order number and Stripe
as the payment method.

### Go to the admin dashboard and click WooCommerce

I see the order number I created from checkout.

### Click the order number

I see the order status is processing/completed and from Order notes there's **Stripe charge
complete (Charge ID: xxx)**.

## Test checkout with Stripe Bancontact enabled in Test Mode While Logged In

### Go to WooCommerce > Settings > Checkout > Stripe Bancontact

### Click on Enable and save

### Click Save changes button at the bottom

I see **Your settings have been saved** notice.

Be sure to also copy the webhook endpoint provided on the settings page and add it to your Stripe Dashboard API/Webhook setting.

### Go to WooCommerce > Settings > General

### Set currency to Euro

### Click Save changes button at the bottom

I see **Your settings have been saved** notice.

### Create simple virtual product with price $10

### View the product

### Click Add to cart

### Go to the checkout page

I see Stripe Bancontact is available as a payment method.

### Fill in all requried details

### Select Stripe Bancontact as payment method if not selected already

### Click on **Place order** button

I see a redirect Stripe page simulating an authorization. I also see two options **Fail Test Payment** and **Authenticate Test Payment**.

### Click on ***Authenticate Test Payment***

It redirects me to Order received page. I can see the order number and Stripe
as the payment method.

### Go to the admin dashboard and click WooCommerce

I see the order number I created from checkout.

### Click the order number

I see the order status is processing/completed and from Order notes there's **Stripe charge
complete (Charge ID: xxx)**. I understand I may not see the order status changed to processing/completed right away as some payment methods are asynchronous and can take a little time to trigger. But eventually I see the change.

## Test checkout with Stripe SoFort enabled in Test Mode While Logged In

### Go to WooCommerce > Settings > Checkout > Stripe SoFort

### Click on Enable and save

### Click Save changes button at the bottom

I see **Your settings have been saved** notice.

Be sure to also copy the webhook endpoint provided on the settings page and add it to your Stripe Dashboard API/Webhook setting.

### Go to WooCommerce > Settings > General

### Set currency to Euro

### Click Save changes button at the bottom

I see **Your settings have been saved** notice.

### Create simple virtual product with price $10

### View the product

### Click Add to cart

### Go to the checkout page

I see Stripe SoFort is available as a payment method.

### Fill in all requried details

### Select Stripe SoFort as payment method if not selected already

### Select a SoFort ***Country origin of your bank.***

### Click on **Place order** button

I see a redirect Stripe page simulating an authorization. I also see two options **Fail Test Payment** and **Authenticate Test Payment**.

### Click on ***Authenticate Test Payment***

It redirects me to Order received page. I can see the order number and Stripe
as the payment method.

### Go to the admin dashboard and click WooCommerce

I see the order number I created from checkout.

### Click the order number

I see the order status is on-hold and from Order notes there's **Stripe awaiting payment**. I understand I may not see the order status changed to processing/completed right away as some payment methods are asynchronous and can take a little time to trigger. But eventually I see the change. Note for this payment method, order status will not change in test mode.

## Test checkout with Stripe Giropay enabled in Test Mode While Logged In

### Go to WooCommerce > Settings > Checkout > Stripe Giropay

### Click on Enable and save

### Click Save changes button at the bottom

I see **Your settings have been saved** notice.

Be sure to also copy the webhook endpoint provided on the settings page and add it to your Stripe Dashboard API/Webhook setting.

### Go to WooCommerce > Settings > General

### Set currency to Euro

### Click Save changes button at the bottom

I see **Your settings have been saved** notice.

### Create simple virtual product with price $10

### View the product

### Click Add to cart

### Go to the checkout page

I see Stripe Giropay is available as a payment method.

### Fill in all requried details

### Select Stripe Giropay as payment method if not selected already

### Click on **Place order** button

I see a redirect Stripe page simulating an authorization. I also see two options **Fail Test Payment** and **Authenticate Test Payment**.

### Click on ***Authenticate Test Payment***

It redirects me to Order received page. I can see the order number and Stripe
as the payment method.

### Go to the admin dashboard and click WooCommerce

I see the order number I created from checkout.

### Click the order number

I see the order status is processing/completed and from Order notes there's **Stripe charge
complete (Charge ID: xxx)**. I understand I may not see the order status changed to processing/completed right away as some payment methods are asynchronous and can take a little time to trigger. But eventually I see the change.

## Test checkout with Stripe iDeal enabled in Test Mode While Logged In

### Go to WooCommerce > Settings > Checkout > Stripe iDeal

### Click on Enable and save

### Click Save changes button at the bottom

I see **Your settings have been saved** notice.

Be sure to also copy the webhook endpoint provided on the settings page and add it to your Stripe Dashboard API/Webhook setting.

### Go to WooCommerce > Settings > General

### Set currency to Euro

### Click Save changes button at the bottom

I see **Your settings have been saved** notice.

### Create simple virtual product with price $10

### View the product

### Click Add to cart

### Go to the checkout page

I see Stripe iDeal is available as a payment method.

### Fill in all requried details

### Select Stripe iDeal as payment method if not selected already

### Click on **Place order** button

I see a redirect Stripe page simulating an authorization. I also see two options **Fail Test Payment** and **Authenticate Test Payment**.

### Click on ***Authenticate Test Payment***

It redirects me to Order received page. I can see the order number and Stripe
as the payment method.

### Go to the admin dashboard and click WooCommerce

I see the order number I created from checkout.

### Click the order number

I see the order status is processing/completed and from Order notes there's **Stripe charge
complete (Charge ID: xxx)**. I understand I may not see the order status changed to processing/completed right away as some payment methods are asynchronous and can take a little time to trigger. But eventually I see the change.

## Test checkout with Stripe Alipay enabled in Test Mode While Logged In

### Go to WooCommerce > Settings > Checkout > Stripe Alipay

### Click on Enable and save

### Click Save changes button at the bottom

I see **Your settings have been saved** notice.

Be sure to also copy the webhook endpoint provided on the settings page and add it to your Stripe Dashboard API/Webhook setting.

### Go to WooCommerce > Settings > General

### Set currency to Euro

### Click Save changes button at the bottom

I see **Your settings have been saved** notice.

### Create simple virtual product with price $10

### View the product

### Click Add to cart

### Go to the checkout page

I see Stripe Alipay is available as a payment method.

### Fill in all requried details

### Select Stripe Alipay as payment method if not selected already

### Click on **Place order** button

I see a redirect Stripe page simulating an authorization. I also see two options **Fail Test Payment** and **Authenticate Test Payment**.

### Click on ***Authenticate Test Payment***

It redirects me to Order received page. I can see the order number and Stripe
as the payment method.

### Go to the admin dashboard and click WooCommerce

I see the order number I created from checkout.

### Click the order number

I see the order status is processing/completed and from Order notes there's **Stripe charge
complete (Charge ID: xxx)**. I understand I may not see the order status changed to processing/completed right away as some payment methods are asynchronous and can take a little time to trigger. But eventually I see the change.

## Test checkout with Stripe SEPA Direct Debit enabled in Test Mode While Logged In

### Go to WooCommerce > Settings > Checkout > Stripe SEPA Direct Debit

### Click on Enable and save

### Click Save changes button at the bottom

I see **Your settings have been saved** notice.

Be sure to also copy the webhook endpoint provided on the settings page and add it to your Stripe Dashboard API/Webhook setting.

### Go to WooCommerce > Settings > General

### Set currency to Euro

### Click Save changes button at the bottom

I see **Your settings have been saved** notice.

### Create simple virtual product with price $10

### View the product

### Click Add to cart

### Go to the checkout page

I see Stripe SEPA Direct Debit is available as a payment method.

### Fill in all requried details

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
