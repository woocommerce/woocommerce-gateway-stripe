# DEVELOPER.md

## Testing

* In wp-admin > WooCommerce > Settings > Checkout > Stripe, Enable Stripe, Enable Test Mode, Enable Stripe Checkout and Enable Payment via Saved Cards
* In wp-admin > WooCommerce > Settings > Checkout > Stripe, enter a Test Secret Key and a Test Publishable Key
* Enable at least one other payment gateway (e.g. Cheques)

* On the front side, place an item in your cart and proceed to Checkout
* Fill in all required fields in the Billing Details area
* Select Credit Card (Stripe) and "Use a new credit card"
* Click on Continue to payment
* Verify you get the stripe modal requesting card number, expiration and CVC
* Enter 4242 4242 4242 4242, 12/17, 123
* Leave Remember Me unchecked
* Click Confirm and Pay
* Verify the modal closes, the page dims for a bit, and then you are redirected to Order Received

* Repeat the above steps, but this time instead of "Use a new credit card" use a stored card
* Click on Continue to payment
* Verify the page dims for a bit and then you are redirected to Order Received

* Repeat the above steps, but this time clear the Billing Details (e.g. Name, etc)
* Choose a stored card in Stripe
* Click on Continue to payment
* Verify you get prompted to fill in required fields.
* Fill in the required fields
* Click on Continue to payment
* Verify the page dims for a bit and then you are redirected to Order Received

* Repeat the above steps, but this time choose the "Cheque Payment" gateway
* Click on Place Order
* Verify the page dims for a bit and then you are redirected to Order Received

* Repeat at least the "Use a new credit card" case on Chrome on an iPhone or iPad

* In wp-admin > WooCommerce > Settings > Checkout > Stripe, uncheck Enable Payment via Saved Cards
* On the front side, place an item in your cart and proceed to Checkout
* Fill in all required fields in the Billing Details area
* Select Credit Card (Stripe)
* Click on Continue to payment
* Verify you get the stripe modal requesting card number, expiration and CVC
* Enter 4242 4242 4242 4242, 12/17, 123
* Leave Remember Me unchecked
* Click Confirm and Pay
* Verify the modal closes, the page dims for a bit, and then you are redirected to Order Received
