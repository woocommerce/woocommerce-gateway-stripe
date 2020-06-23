=== WooCommerce Stripe Payment Gateway ===
Contributors: automattic, royho, akeda, mattyza, bor0, woothemes
Tags: credit card, stripe, apple pay, payment request, google pay, sepa, sofort, bancontact, alipay, giropay, ideal, p24, woocommerce, automattic
Requires at least: 4.4
Tested up to: 5.4
Requires PHP: 5.6
Stable tag: 4.5.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Attributions: thorsten-stripe

Take credit card payments on your store using Stripe.

== Description ==

Accept Visa, MasterCard, American Express, Discover, JCB, Diners Club, SEPA, Sofort, iDeal, Giropay, Alipay, and more directly on your store with the Stripe payment gateway for WooCommerce, including Apple Pay, Google Pay, and Microsoft Pay for mobile and desktop.

= Take Credit card payments easily and directly on your store =

The Stripe plugin extends WooCommerce allowing you to take payments directly on your store via Stripe’s API.

Stripe is available for Store Owners and Merchants in:

* Australia
* Austria
* Belgium
* Canada
* Denmark
* Finland
* France
* Germany
* Hong Kong
* Ireland
* Italy
* Japan
* Luxembourg
* Netherlands
* New Zealand
* Norway
* Portugal
* Singapore
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

WooCommerce Stripe includes [Apple Pay](https://stripe.com/apple-pay) support, which means customers can pay using payment details associated with their Apple ID. Checkout is now just an authorization (Touch ID or Face ID) away on both mobile and desktop. Only supports simple, variable, and Subscription products for now. More support to come.

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

For help setting up and configuring, please refer to our [documentation](https://docs.woocommerce.com/document/stripe/).

= Where can I get support or talk to other users? =

If you get stuck, you can ask for help in the Plugin Forum.

== Screenshots ==

1. The Stripe payment gateway settings screen used to configure the main Stripe gateway.
2. Offer a range of payment methods such as local and alternative payment methods.
3. Pay with a saved payment method, a new card, and allow customers to save the payment card for future transactions.
4. Apple Pay and other Payment Request buttons can be used on the Product Page and Checkout for express checkout.

== Changelog ==

= 4.5.0 2020-06-24 =
* Tweak - Improve branded Google Pay button user agent detection.
* Add   - New filter to manage the display of payment request buttons in cart.
* Fix   - Display Apple Pay button with text if branded type is text and logo.
* Fix   - Prevent branded payment request button duplication when checkout is re-calculated.
* Fix   - Payment request buttons on a single product page now correctly show the product name instead of a subtotal.
* Fix   - Quotes in variadic product attributes no longer cause payment request buttons to show only the cheapest variation.

= 4.4.0 2020-05-21 =
* Tweak - Remove support for WooCommerce versions lower than 3.0.
* Tweak - Update plugin assets.
* Tweak - Improve performance on order pay screen.
* Tweak - Compress payment methods SVGs.
* Add   - Support for custom payment request button.
* Fix   - Prevent users from entering secret key in the public key field and stop it from showing if they did.
* Fix   - Revert caching layer removal to speed up My Account subscriptions tab.
* Fix   - Bug where source ID was not set resulting in an uncaught exception.
* Fix   - Collecting level 3 data triggers a warning.
* Fix   - Custom button class typo.
* Fix   - Payment Request incorrect shipping option passed.
* Fix   - Payment Requests, like Apple Pay, not initially showing correct quantity if used on Product Page.
* Fix   - State abbreviation breaks Payment Request button.
* Fix   - Stripe Payment Request button using incorrect Google Pay brand asset by adding a special branded button.
* Fix   - Move docs and support links in the plugins list page.

= 4.3.3 2020-04-08 =
* Fix - Fix Payment Request issue when product attribute has quotes
* Fix - Fix "no such customer" error on checkout after the keys have been changed
* Add - Add alert for end of support WC < 3.0 in future releases
* Fix - Fix crash when used with WooCommerce Subscriptions <2.6
* Fix - Add missing customer ID to subscriptions before processing payment
* Fix - Fix transactions failing with trailing slash

= 4.3.2 2020-02-17 =
* Fix - Add compatibility to payment request buttons with some of the WooCommerce Product Add-ons on the product page
* Fix - Improved compatibility for free orders with other extensions
* Add - Support for multisite when sites use different Stripe accounts
* Fix - Display a localized error message when a customer tries to save a card during checkout, but there's an error
* Add - Send level 3 credit card data for purchases when possible

= 4.3.1 2019-11-12 =
* Fix - Overwrite the previous Apple Pay verification file if it has changed.
* Fix - Avoid re-mounting card elements if they are already mounted in the DOM.
* Fix - Compatibility with WooCommerce Deposits by retrieving order statuses in a different way.
* Fix - Duplicate payment notifications for subscriptions.
* Fix - Use the same customer after a new credit card has been entered.
* Fix - Google Pay buttons on subscriptions.
* Add - A filter, which allows all subscriptions' payment methods to be overwritten when adding a new payment method.

[See changelog for all versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-stripe/master/changelog.txt).

== Upgrade Notice ==

= 4.2 =
4.2 is a minor release. Please do a full site backup and test on a staging site before deploying to a live/production server.
