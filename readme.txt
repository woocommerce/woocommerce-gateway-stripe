=== WooCommerce Stripe Payment Gateway ===
Contributors: woocommerce, automattic, royho, akeda, mattyza, bor0, woothemes
Tags: credit card, stripe, apple pay, payment request, google pay, sepa, bancontact, alipay, giropay, ideal, p24, woocommerce, automattic
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 8.7.0
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

= 8.7.0 - 2024-09-16 =
* Add - Introduces a new promotional surface to encourage merchants with the legacy checkout experience and APMs enabled to use the new checkout experience.
* Fix - Prevent duplicate failed-order emails from being sent.
* Fix - Support custom name and description for Afterpay.
* Fix - Link APM charge IDs in Order Details page to their Stripe dashboard payments page.
* Fix - Fix Indian subscription processing by forcing the recreation of mandates during switches (upgrading/downgrading).
* Fix - Add back support for Stripe Link autofill for shortcode checkout.
* Fix - Fix undefined method error caused by settings refactor when connecting Stripe account.
* Fix - Fix multiple compatibility issues and deprecation warnings when running the extension on PHP 8.1.
* Fix - Re-connect promotional surface blinking after disappearing for merchants that have already connected their Stripe account.
* Fix - Fix possible fatal errors when Stripe settings format is invalid during account connection.
* Fix - Clear webhook state after reconfiguring webhooks to remove outdated error and success statuses.
* Fix - Prevent payment methods from being detached from Stripe customers on non-production sites when a WP user is deleted with the new checkout experience enabled.
* Add - Log incoming webhook events and their request body.
* Add - Show UPE payment methods in saved order on block checkout page.
* Add - Display UI elements for connection type and expired keys status for Stripe accounts linked via the WooCommerce Stripe App.
* Tweak - Delete the notice about the missing customization options on the updated checkout experience.
* Fix - Prevent fatal error when canceling uncaptured orders by ensuring refunds array is expanded in Stripe API response.
* Fix - Fix error in saving settings when express payment methods are disabled.
* Fix - Catch error when getting intent from order.
* Fix - Handle undefined array key when no matching customer account is found when guest customers checkout.
* Tweak - Update capabilities to payment methods mapping.
* Fix - Address QIT Security test errors.
* Fix - Address QIT PHPStan test errors.
* Update - Specify the JS Stripe API version as 2024-06-20.
* Tweak - Use order ID from 'get_order_number' in stripe intent metadata.
* Fix - Ensure payment tokens are detached from Stripe when a user is deleted, regardless of if the admin user has a Stripe account.
* Fix - Address Klarna availability based on correct presentment currency rules.
* Fix - Use correct ISO country code of United Kingdom in supported country and currency list of AliPay and WeChat.
* Fix - Prevent duplicate order notes and emails being sent when purchasing subscription products with no initial payment.
* Add - Display an admin notice on the WooCommerce > Subscriptions screen for tracking the progress of SEPA subscriptions migrations after the legacy checkout is disabled.
* Add - Introduce a new tool on the WooCommerce > Status > Tools screen to restart the legacy SEPA subscriptions update.
* Fix - Remove the Stripe OAuth Keys when uninstalling the plugin.
* Fix - Resolve an error for checkout block where 'wc_stripe_upe_params' is undefined due to the script registering the variable not being loaded yet.

[See changelog for all versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-stripe/trunk/changelog.txt).
