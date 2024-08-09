=== WooCommerce Stripe Payment Gateway ===
Contributors: woocommerce, automattic, royho, akeda, mattyza, bor0, woothemes
Tags: credit card, stripe, apple pay, payment request, google pay, sepa, bancontact, alipay, giropay, ideal, p24, woocommerce, automattic
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 8.6.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Attributions: thorsten-stripe

Take credit card payments on your store using Stripe.

== Description ==

Accept Visa, MasterCard, American Express, Discover, JCB, Diners Club, SEPA, iDEAL, giropay, Alipay, and more directly on your store with the Stripe payment gateway for WooCommerce, including Apple Pay, Google Pay, and Microsoft Pay for mobile and desktop.

= Take Credit card payments easily and directly on your store =

The Stripe plugin extends WooCommerce allowing you to take payments directly on your store via Stripe’s API.

Stripe is available for Store Owners and Merchants in:

* Australia
* Austria
* Belgium
* Brazil
* Bulgaria
* Canada
* Cyprus
* Czech Republic
* Denmark
* Estonia
* Finland
* France
* Germany
* Greece
* Hong Kong
* Ireland
* Italy
* Japan
* Latvia
* Lithuania
* Luxembourg
* Malaysia
* Malta
* Mexico
* Netherlands
* New Zealand
* Norway
* Poland
* Portugal
* Puerto Rico
* Singapore
* Slovakia
* Slovenia
* Spain
* Sweden
* Switzerland
* United Kingdom
* United States
* [with more being added](https://stripe.com/global)

Stripe is a simple way to accept payments online. With Stripe you can accept Visa, MasterCard, American Express, Discover, JCB, and Diners Club cards, even Bitcoin, directly on your store.

= Why choose Stripe? =

Stripe has no setup fees, no monthly fees, no hidden costs: you only get charged when you earn money! Earnings are transferred to your bank account on a 7-day rolling basis.

Stripe also supports the [WooCommerce Subscriptions extension](https://woocommerce.com/products/woocommerce-subscriptions/) and re-using cards. When a customer pays, they are set up in Stripe as a customer. If they create another order, they can check out using the same card. A massive timesaver for returning customers.

= Apple Pay Support =

WooCommerce Stripe includes [Apple Pay](https://woocommerce.com/apple-pay) support, which means customers can pay using payment details associated with their Apple ID. Checkout is now just an authorization (Touch ID or Face ID) away on both mobile and desktop. Only supports simple, variable, and Subscription products for now. More support to come.

= Web Payments API Support =

WooCommerce Stripe includes [Web Payments API](https://www.w3.org/TR/payment-request/) support, which means customers can pay using payment details associated to their mobile devices, in browsers supporting the Web Payments API (Chrome for Android, amongst others). Checkout is now just a few taps away on mobile. Only supports simple, variable, and Subscription products for now. More support to come.

== Installation ==

You can download an [older version of this gateway for older versions of WooCommerce from here](https://wordpress.org/plugins/woocommerce-gateway-stripe/developers/).

Please note, v4 of this gateway requires WooCommerce 3.0 and above.

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To do an automatic install of the WooCommerce Stripe plugin, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type “WooCommerce Stripe Payment Gateway” and click Search Plugins. Once you’ve found our plugin you can view details about it such as the point release, rating, and description. Most importantly, of course, you can install it by simply clicking "Install Now", then "Activate".

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your web server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Frequently Asked Questions ==

= Does this support recurring payments, like for subscriptions? =

Yes!

= Does this require an SSL certificate? =

Yes! In Live Mode, an SSL certificate must be installed on your site to use Stripe. In addition to SSL encryption, Stripe provides an extra JavaScript method to secure card data using [Stripe Elements](https://stripe.com/elements).

= Does this support both production mode and sandbox mode for testing? =

Yes, it does - production and Test (sandbox) mode is driven by the API keys you use with a checkbox in the admin settings to toggle between both.

= Where can I find documentation? =

For help setting up and configuring, please refer to our [documentation](https://woocommerce.com/document/stripe/).

= Where can I get support or talk to other users? =

If you get stuck, you can ask for help in the Plugin Forum.

== Screenshots ==

1. The Stripe payment gateway settings page used to configure the plugin.
2. Offer a range of payment options such as local methods and express checkouts.
3. Allow customers to save payment methods and add new ones for their future purchases.
4. Apple Pay and other express methods can be added to product pages, cart, and checkout.

== Changelog ==

= 8.6.1 - 2024-08-09 =
* Tweak - Improves the wording of the invalid Stripe keys errors, instructing merchants to click the "Configure connection" button instead of manually setting the keys.
* Add - Includes a new promotional surface to encourage merchants to re-connect their Stripe account using the new flow.
* Add - Added filter to enable updating Level 3 data based on order data.
* Add - Replace account key sharing and replace it with an OAuth connect flow allowing users to connect their Stripe account automatically without the need to find keys.
* Add - Indicate the activation status of each payment method individually, instead of using a general notice.
* Fix - JS error when billing country field does not exist on the payment method page.
* Fix - Prevent multiple instances of the "Update the Payment Method" checkbox from displaying on the My Account > Payment Methods page when using the legacy checkout experience.
* Fix - Prevent duplicate customer creation during guest checkout.
* Fix - Hiding Multibanco payment method when the Stripe account country is not supported.
* Fix - Display the payment decline reason on the checkout when using Cash App or WeChat.
* Fix - Re-enable the "Place order" button on the block checkout after closing the WeChat or Cash App payment modal.
* Fix - When SEPA tokens are added via the My Account > Payment methods page, ensure they are attached to the Stripe customer.
* Fix - Clear the saved Stripe Link payment methods when a customer cache is cleared to ensure cached methods are updated promptly.
* Fix - Display Stripe Link payment methods correctly in both Block Checkout and My Account pages.
* Fix - Resolve an error when adding a saved card payment method in My Account when Stripe Link is enabled.
* Fix - Resolved an error when using 3D Secure-enabled cards with Stripe Link enabled.
* Fix - Corrected setup intent payment method types to include 'link' when Stripe Link is enabled, resolving errors during subscription signups.
* Fix - Resolved an issue where changing the payment method for subscriptions failed after 3D-Secure authentication.
* Fix - Prevent displaying the default admin description on the checkout page when a payment method description is empty.
* Fix - Adds back the ability to perform direct refunds for giropay orders via the order details page.
* Fix - After configuring webhooks automatically ensure only the latest webhook endpoint is active, deleting duplicates configured manually.
* Fix - Resolved PHP errors related to detaching payment methods after failed 3D-Secure challenges.
* Tweak - Minor text updates to webhook-related configuration labels and buttons.
* Tweak - Improve UX by using the 3DS verification modal to confirm setup intents for subscription sign-ups, ensuring customers stay on the checkout page.
* Tweak - Display a notice when the Stripe connect URL is not available.
* Fix - Prevent adding multiple copies of the same order notes.
* Tweak - Automatically configure webhooks after completing the OAuth Stripe flow.
* Tweak - Don't process webhooks when the webhook secret isn't set in the store.

[See changelog for all versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-stripe/trunk/changelog.txt).
