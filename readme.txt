=== WooCommerce Stripe Payment Gateway ===
Contributors: woocommerce, automattic, royho, akeda, mattyza, bor0, woothemes
Tags: credit card, stripe, apple pay, payment request, google pay, sepa, bancontact, alipay, giropay, ideal, p24, woocommerce, automattic
Requires at least: 6.2
Tested up to: 6.5.2
Requires PHP: 7.4
Stable tag: 8.4.0
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

= 8.4.0 - 2024-06-13 =
* Tweak - Resets the list of payment methods when any Stripe key is updated.
* Fix - Removes the list of saved payment methods when the setting is disabled.
* Tweak - Update WooCommerce.com docs links.
* Fix - Correctly setting the preferred card brand when creating and updating a payment intent.
* Fix - Added a feedback message + redirection back to cart when a Cash App payment fails.
* Tweak - Update WordPress.org screenshots and captions.
* Fix - Remove the duplicated customization notice in the settings screen.
* Add - Include Cash App as a payment method for stores using the updated checkout experience.
* Fix - Fixed fatal errors with subscription helper methods when subscriptions classes (from WooCommerce Subscriptions) are not available.
* Add - Add a new dismissible banner to promote Stripe products to the settings page.
* Fix - Hiding BNPL payment methods when the Stripe account country is not supported.
* Fix - Resolved checkout error with UPE when using test mode customer in live mode or vice versa.
* Fix - Handle checkbox type required fields correctly on the shortcode checkout when paying with Google Pay/Apple Pay.
* Tweak - Remove unused confirmation modal a capability properties.
* Fix - Resgister script on the checkout page only when the gateway is enabled.
* Tweak - Allow to enable/disable payment methods irrespective of currency requirement.
* Add - Include WeChat Pay as a payment method for stores using the updated checkout experience.
* Add - Show currency requirement details in a popover.
* Fix - Resolve invalid recurring shipping method errors when attempting to purchase multiple subscriptions with Apple Pay or Google Pay.
* Fix - Deprecation errors on PHP 8.2 caused by using the deprecated constant FILTER_SANITIZE_STRING.
* Update - Declare compatibility with the Cart and Checkout blocks.

[See changelog for all versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-stripe/trunk/changelog.txt).
