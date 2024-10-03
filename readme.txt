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

Changing consumer behavior has resulted in an explosion of payment methods and experiences, which are great for increasing conversion and lowering costs—but can be difficult for businesses to maintain. Give customers a best-in-class checkout experience while you remain focused on your core business. This is the official plugin created by Stripe and WooCommerce.

= Drive 11.9% in revenue with an optimized checkout experience from Stripe =

The enhanced checkout experience from Stripe can help customers:

- **Boost conversion:** Provide an optimal experience across mobile, tablet, and desktop with a responsive checkout, and offer 23 payment methods, including [Link](https://stripe.com/payments/link), [Apple Pay](https://woocommerce.com/apple-pay/), and [Google Pay](https://www.google.com/payments/solutions/), out of the box.
- **Expand your customer base:** Convert customers who might otherwise abandon their cart with buy now, pay later methods like Klarna, Affirm, and Afterpay/Clearpay, wallets like Apple Pay, Google Pay, Alipay, and WeChat Pay, and local payment methods such as Bancontact in Europe and Alipay in Asia Pacific. Deliver a localized payment experience with out-of-the-box support for localized error messages, right-to-left languages, and automatic adjustment of input fields based on payment method and country.
- **Meet existing customer demand and localize the experience:** Offer [local payment methods](https://stripe.com/guides/payment-methods-guide), such as Bancontact, Boleto, Cash App Pay, EPS, giropay, iDEAL, Multibanco, OXXO, Przelewy 24, and SEPA Direct Debit.
- **Fight fraud:** Detect and prevent fraud with [Stripe Radar](https://stripe.com/radar), which offers seamlessly integrated, powerful fraud-detection tools that use machine learning to detect and flag potentially fraudulent transactions.
- **Accept in-person payments for products and services:** Use the Stripe Terminal M2 card reader or get started with no additional hardware using Tap to Pay on iPhone, or Tap to Pay on Android.
- **Support subscriptions:** Support recurring payments with various payment methods via [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/).
- **Manage cash flow:** Get paid within minutes with Stripe Instant Payouts, if eligible.
- **Achieve [PCI-DSS](https://docs.stripe.com/security) compliance with [Stripe Elements](https://stripe.com/payments/elements) hosted input fields.**
- Support Strong Customer Authentication (SCA).

Stripe is available for store owners and merchants in [46 countries worldwide](https://stripe.com/global), with more to come.

== Frequently Asked Questions ==

= In which specific countries is Stripe available? =

Stripe is available in the following countries, with more to come:

- Australia
- Austria
- Belgium
- Brazil
- Bulgaria
- Canada
- Croatia
- Cyprus
- Czech Republic
- Denmark
- Estonia
- Finland
- France
- Germany
- Gibraltar
- Greece
- Hong Kong
- Hungary
- India
- Ireland
- Italy
- Japan
- Latvia
- Liechtenstein
- Lithuania
- Luxembourg
- Malaysia
- Malta
- Mexico
- Netherlands
- New Zealand
- Norway
- Poland
- Portugal
- Romania
- Singapore
- Slovakia
- Slovenia
- Spain
- Sweden
- Switzerland
- Thailand
- United Arab Emirates
- United Kingdom
- United States

= Does this require an SSL certificate? =

Yes. In Live Mode, an SSL certificate must be installed on your site to use Stripe. In addition to SSL encryption, Stripe provides an extra JavaScript method to secure card data using [Stripe Elements](https://stripe.com/elements).

= Does this support both production mode and sandbox mode for testing? =

Yes, it does. Both production and test (sandbox) modes are driven by the API keys you use with a checkbox in the admin settings to toggle between both.

= Where can I find documentation? =

Refer to the [Stripe WooCommerce Extension documentation for more information, including how to set up and configure the extension](https://woocommerce.com/document/stripe/).

= Where can I get support or talk to other users? =

If you get stuck, you can ask for help in the [Plugin Forum](https://wordpress.org/support/plugin/woocommerce-gateway-stripe/).

== Screenshots ==

1. With the enhanced checkout from Stripe, you can surface 23 payment methods including buy now, pay later methods; and Link, an accelerated checkout experience.
2. Link autofills your customers’ payment information to create an easy and secure checkout experience.
3. Convert customers who would usually abandon their cart and increase average order value with buy now, pay later options like Klarna, Afterpay, and Affirm. Accept credit and debit card payments from Visa, Mastercard, American Express, Discover, and Diners.
4. Stripe Radar offers seamlessly integrated, powerful fraud-detection tools that use machine learning to detect and flag potentially fraudulent transactions.

== Changelog ==

= 8.8.0 - xxxx-xx-xx =
* Fix - Resolve an error for checkout block where 'wc_stripe_upe_params' is undefined due to the script registering the variable not being loaded yet.

= 8.7.0 - xxxx-xx-xx =
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

[See changelog for all versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-stripe/trunk/changelog.txt).
