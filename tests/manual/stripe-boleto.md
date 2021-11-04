## Stripe Alipay

### Go to WooCommerce > Settings > Payments > Stripe Boleto

#### Click Enable/Disable checkbox

#### Click Save changes button at the bottom

I see **Your settings have been saved** notice.

Be sure to also copy the webhook endpoint provided on the settings page and add it to your Stripe Dashboard API/Webhook setting.

### Go to WooCommerce > Settings > General

#### Set currency to Brazilian Real (R$)
#### Set country to Brazil

#### Click Save changes button at the bottom

I see **Your settings have been saved** notice.

### Add a product to cart

### Go to the checkout page

I see Stripe Boleto is available as a payment method.

#### Fill in all required details

#### Select Stripe Boleto as payment method if not selected already

#### Fill in the cpf/cnpj field with 75993088060

I see this mask was applied xxx.xxx.xxx-xx

#### Fill in the cpf/cnpj field with 26920537000163

I see this mask was applied xx.xxx.xxx/xxxx-xx

#### Click on **Place order** button

I see a boleto barcode on a modal

#### Close modal

Checktout is completed with success

### Go to the admin dashboard and click WooCommerce > Orders

I see the order number I created from checkout.

#### Click the order number

I see the order status is pending payment and from Order notes there's **Stripe payment intent created (Payment Intent ID: xxx)**.

#### Wait a few minutes
#### Reload page
I see the order is processing/completed (This will only happen when using a development mode. In production mode the customer has to pay the boleto and wait up to 2 days to compensate)
