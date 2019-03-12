=== WooCommerce Stripe Payment Gateway ===
Contributors: automattic, royho, akeda, mattyza, bor0, woothemes
Tags: credit card, stripe, apple pay, payment request, google pay, sepa, sofort, bancontact, alipay, giropay, ideal, p24, woocommerce, automattic
Requires at least: 4.4
Tested up to: 5.0
Requires PHP: 5.6
Stable tag: 4.1.15
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Attributions: thorsten-stripe

Take credit card payments on your store using Stripe.

== Description ==

Accept Visa, MasterCard, American Express, Discover, JCB, Diners Club, SEPA, Sofort, iDeal, Giropay, Alipay and more directly on your store with the Stripe payment gateway for WooCommerce, including Apple Pay for mobile and desktop.

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

Stripe also supports the [Subscriptions extension](https://woocommerce.com/products/woocommerce-subscriptions/) and re-using cards. When a customer pays, they are set up in Stripe as a customer. If they create another order, they can check out using the same card. A massive timesaver for returning customers.

= Apple Pay Support =

WooCommerce Stripe now includes [Apple Pay](https://stripe.com/apple-pay) support, which means customers can pay using payment details associated to their Apple ID. Checkout is now just a thumbprint away. on both mobile and desktop. Only supports simple and variable products for now. More support to come.

= Web Payments API Support =

WooCommerce Stripe now includes [Web Payments API](https://www.w3.org/TR/payment-request/) support, which means customers can pay using payment details associated to their mobile devices, in browsers supporting the Web Payments API (Chrome for Android, amongst others). Checkout is now just a few taps away. on mobile. Only supports simple and variable products for now. More support to come.

== Installation ==

Please note, v4 of this gateway requires WooCommerce 2.6 and above.

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

= Does this require an SSL certificate? =

Yes! In live mode, an SSL certificate must be installed on your site to use Stripe. In addition to SSL encryption, Stripe provides an extra JavaScript method to secure card data.

= Does this support both production mode and sandbox mode for testing? =

Yes it does - production and sandbox mode is driven by the API keys you use.

= Where can I find documentation? =

For help setting up and configuring, please refer to our [user guide](https://docs.woocommerce.com/document/stripe/)

= Where can I get support or talk to other users? =

If you get stuck, you can ask for help in the Plugin Forum.

== Screenshots ==

1. The settings panel used to configure the gateway.
2. Normal checkout with Stripe.
3. Option to save a card to your account.
4. Checking out with a saved card.
5. Using the Stripe checkout modal.

== Changelog ==

= 4.1.15 - 2019-03-12 =
* Fix - Prevent canceled webhook from processing non Stripe payments.
* Fix - "Retain Stripe Data" setting placement on WooCommerce settings page.
* Tweak - Stripe API version updated to support 2019-02-19.

[See changelog for all versions](https://raw.githubusercontent.com/woothemes/woocommerce-gateway-stripe/master/changelog.txt).

== Upgrade Notice ==

= 4.1 =
4.1 is a minor release. Please do a full site backup and test on a staging site before deploying to a live/production server.
