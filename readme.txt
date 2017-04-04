=== WooCommerce Stripe Payment Gateway ===
Contributors: automattic, woothemes, mikejolley, akeda, royho, mattyza, slash1andy, woosteve, spraveenitpro, mikedmoore, fernashes, shellbeezy, danieldudzic, mikaey, dsmithweb, fullysupportedphil, corsonr, deskroid, luminus, tiagonoronha, zandyring
Tags: credit card, stripe, woocommerce
Requires at least: 4.4
Tested up to: 4.7.2
Stable tag: 3.1.6
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Take credit card payments on your store using Stripe.

== Description ==

Accept Visa, MasterCard, American Express, Discover, JCB, Diners Club, and more cards directly on your store with the Stripe payment gateway for WooCommerce, including Apple Pay for mobile and desktop.

= Take Credit card payments easily and directly on your store =

The Stripe plugin extends WooCommerce allowing you to take payments directly on your store via Stripe’s API.

Stripe is available in:

* United States
* Canada
* UK
* Australia
* Belgium
* France
* Germany
* Ireland
* The Netherlands
* [and more](https://support.stripe.com/questions/what-countries-does-stripe-support)

Stripe is a simple way to accept payments online. With Stripe you can accept Visa, MasterCard, American Express, Discover, JCB, and Diners Club cards, even Bitcoin, directly on your store.

= Why choose Stripe? =

Stripe has no setup fees, no monthly fees, no hidden costs: you only get charged when you earn money! Earnings are transferred to your bank account on a 7-day rolling basis.

Stripe also supports the [Subscriptions extension](http://www.woothemes.com/extension/subscriptions/) and re-using cards. When a customer pays, they are set up in Stripe as a customer. If they create another order, they can check out using the same card. A massive timesaver for returning customers.

= Apple Pay Support =

WooCommerce Stripe now includes [Apple Pay](https://stripe.com/apple-pay) support, which means customers can pay using payment details associated to their Apple ID. Checkout is now just a thumbprint away. on both mobile and desktop.

= Web Payments API Support =

WooCommerce Stripe now includes [Web Payments API](https://www.w3.org/TR/payment-request/) support, which means customers can pay using payment details associated to their mobile devices, in browsers supporting the Web Payments API (Chrome for Android, amongst others). Checkout is now just a few taps away. on mobile.

== Installation ==

Please note, v3 of this gateway requires WooCommerce 2.5 and above.

You can download an [older version of this gateway for older versions of WooCommerce from here](https://wordpress.org/plugins/woocommerce-gateway-stripe/developers/).

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To
do an automatic install of, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type “WooCommerce Stripe Payment Gateway” and click Search Plugins. Once you’ve found our plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking "Install Now".

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your web server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Frequently Asked Questions ==

= Does this support recurring payments, like for subscriptions? =

Yes!

= Does this support both production mode and sandbox mode for testing? =

Yes it does - production and sandbox mode is driven by the API keys you use.

= Where can I find documentation? =

For help setting up and configuring, please refer to our [user guide](https://docs.woothemes.com/document/stripe/)

= Where can I get support or talk to other users? =

If you get stuck, you can ask for help in the Plugin Forum.

== Screenshots ==

1. The settings panel used to configure the gateway.
2. Normal checkout with Stripe.
3. Option to save a card to your account.
4. Checking out with a saved card.
5. Using the Stripe checkout modal.

== Changelog ==

= 3.1.6 =
* Fix - TypeError issues on single product page when using Apple Pay on Desktop.
* Fix - In certain case, Apple Pay on single product page does not show shipping info.
* Fix - Use store's base location to show/hide accepted credit cards instead of currency.
* Fix - Unsupported product type when a variable product is added with Apple Pay.

= 3.1.5 =
* Add - Check for WP error object in domain verification.

= 3.1.4 =
* Tweak - If Apple Pay is not enabled, prevent Apple Pay Init.
* Fix - Update for WooCommerce 3.0 compatibility.
* Fix - Apple Pay on product detail page causes qty issue when using normal add to cart.
* Fix - A partial workaround for UK/CA postal codes issue for shipping methods that support partial postal code estimates.
* Fix - Stripe checkout did not validate properly formatted email address.
* Fix - Error messages were not able to translate when validating checkout form.
* Fix - Prevent payment gateway title logic ( Apple Pay ) from hijacking other payment methods.
* Remove - Stripe Checkout allow remember me setting as it is redundant to saved card setting.

= 3.1.3 =
* Fix - When using Stripe Checkout, add payment method was disabled.
* Fix - Possible non object type when using preview pages.

= 3.1.2 =
* Fix - Add payment method on My Account page error.

= 3.1.1 =
* Fix - Apple Pay error notice log link is incorrect.
* Fix - Apple Pay domain verification paths check.

= 3.1.0 =
* New - Apple Pay Support.
* New - Add Google Payment Request API.

= 3.0.7 =
* New - Option to allow/disallow remember me on Stripe checkout modal.
* Fix - Paying for order incorrectly uses cart amount.
* Fix - Using WC function before checking exists causes fatal error.

= 3.0.6 =
* Fix - When adding declined cards, fatal error is thrown.
* Fix - After a failed/declined process, valid cards are not accepted.
* Fix - When paying via pay order page/link, billing info is not sent.
* Fix - Account for all types of errors for proper localization.
* Fix - Correctly reference Stripe fees/net based on Stripe account locale.
* Fix - Bitcoin image not showing.
* New - Introduce "wc_gateway_stripe_process_payment_error" action hook.
* New - Introduce "wc_gateway_stripe_process_payment" action hook.

= 3.0.5 =
* Fix - Previous upload of files didn't take. Retry.

= 3.0.4 =
* Fix - Missing min files.

= 3.0.3 =
* Fix - Remove bitcoin icon when not using Stripe Checkout mode as it is not supported.
* Fix - Failed payment order was not sending email to admin.
* Fix - Saved card option was not being honored.
* New - Filter for WC_Payment_Gateway_CC::wc_stripe_generate_payment_request return value.
* New - Filter to disallow prepaid cards. "wc_stripe_allow_prepaid_card".
* New - Filter to require billing address on Stripe Modal Checkout. "wc_stripe_checkout_require_billing_address".
* New - Localized Stripe error messages.

= 3.0.2 =
* Fix - Set empty array as default value for first argument in WC_Stripe_Customer::create_customer
* Tweak - Update default title to make it consistent with existing titles

= 3.0.1 =
* Backwards compatibility update to prevent error finding WC_Payment_Token_CC.
* Added inline validation of keys.

= 3.0.0 =
* First public WordPress.org release.
* Refactor for WC 2.6 and above. Legacy support for 2.5.
* Improved saved card handling using tokenization API in WooCommerce.

[See changelog for all versions](https://raw.githubusercontent.com/woothemes/woocommerce-gateway-stripe/master/changelog.txt).

== Upgrade Notice ==

= 3.1.6 =
* Fix - TypeError issues on single product page when using Apple Pay on Desktop.
* Fix - In certain case, Apple Pay on single product page does not show shipping info.
* Fix - Use store's base location to show/hide accepted credit cards instead of currency.
* Fix - Unsupported product type when a variable product is added with Apple Pay.
