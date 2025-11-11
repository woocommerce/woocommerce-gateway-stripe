=== WooCommerce Stripe Payment Gateway ===
Contributors: woocommerce, automattic, royho, akeda, mattyza, bor0, woothemes
Tags: credit card, stripe, payments, woocommerce, woo
Requires at least: 6.6
Tested up to: 6.8.3
Requires PHP: 7.4
Stable tag: 10.0.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Attributions: thorsten-stripe

Accept debit and credit cards in 135+ currencies, many local methods like Alipay, ACH, and SEPA, and express checkout with Apple Pay and Google Pay.

== Description ==

Changing consumer behavior has resulted in an explosion of payment methods and experiences, which are great for increasing conversion and lowering costs—but can be difficult for businesses to maintain. Give customers a best-in-class checkout experience while you remain focused on your core business. This is the official plugin created by Stripe and WooCommerce.

= Drive 11.9% in revenue with an optimized checkout experience from Stripe =

The enhanced checkout experience from Stripe can help customers:

- **Boost conversion:** Provide an optimal experience across mobile, tablet, and desktop with a responsive checkout, and offer 23 payment methods, including [Link](https://stripe.com/payments/link), [Apple Pay](https://woocommerce.com/apple-pay/), and [Google Pay](https://www.google.com/payments/solutions/), out of the box.
- **Expand your customer base:** Convert customers who might otherwise abandon their cart with buy now, pay later methods like Klarna, Affirm, and Afterpay/Clearpay, wallets like Apple Pay, Google Pay, Alipay, and WeChat Pay, and local payment methods such as Bancontact in Europe and Alipay in Asia Pacific. Deliver a localized payment experience with out-of-the-box support for localized error messages, right-to-left languages, and automatic adjustment of input fields based on payment method and country.
- **Meet existing customer demand and localize the experience:** Offer [local payment methods](https://stripe.com/guides/payment-methods-guide), such as ACH Direct Debit, Bacs Direct Debit, Bancontact, BECS Direct Debit, BLIK, Boleto, Cash App Pay, EPS, iDEAL, Multibanco, OXXO, Pre-authorized debit payments, Przelewy 24, and SEPA Direct Debit.
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
5. Accept in-person payments for products and services using the Stripe Terminal M2 card reader.
6. Get started with no additional hardware using Tap to Pay on iPhone, or Tap to Pay on Android.

== Changelog ==

= 10.1.0 - xxxx-xx-xx =
* Dev - Remove unused `shouldShowPaymentRequestButton` parameter and calculations from backend
* Fix - Improves the error message shown in checkout when a saved payment method is no longer valid
* Fix - Fix fatal error when trying to allow the `display` CSS property using the `safe_style_css` filter
* Fix - Remove `redirect_url` parameter from Express Checkout payment flow
* Fix - Adjust UI spacing of help text on express checkout theme settings page
* Update - Renames and migrates all Payment Request Buttons settings to Express Checkout
* Dev - Upgrades `@automattic/interpolate-components` to 1.2.1 to remove the `node-fetch` dependency
* Add - Includes a notice to inform merchants about methods that are automatically enabled upon account connection
* Dev - Upgrades the `nock` NPM package to version `^13.5.6` to remove the lodash.set dependency
* Add - Add a new filter allowing third-party plugins to hook captcha solutions when creating and confirming setup intents
* Dev - Add track events when clicking the "Reconnect to Stripe" button (both in the settings page and the admin notice)
* Update - Removes unnecessary legacy checkout gateway instantiations and UPE disablement code
* Dev - Renames previous Order Helper class methods to use the `_id` suffix
* Dev - Expands the Stripe Order Helper class to handle customer ID, card ID, UPE payment type, and UPE redirect status metas
* Fix - Remove redundant secret management logic when configuring webhooks
* Dev - Improve Payment Method Configuration error logging
* Dev - Add Stripe's request-id to API response logs
* Fix - Increase limit when listing available payment method configurations from the Stripe API
* Fix - Klarna not processing recurring payments
* Fix - Fix Express Checkout error with free trial subscription on blocks cart/checkout
* Fix - Improve Express Checkout compatibility with One Page Checkout
* Fix - Allow express checkout to complete successfully for addresses without postal codes in countries where it's not required (eg: Israel)
* Fix - Prevent retrying requests that errored out due to declined payment methods
* Fix - GooglePay/ApplePay fail when there are more than 9 shipping options
* Fix - Detect WooCommerce Subscriptions staging sites when checking if payments can be detached
* Fix - Fix saved ACH payment methods sending unsupported capture_method parameter causing checkout failures
* Dev - Add Stripe's masked API key to API request/response logs
* Add - Add wc_stripe_is_amazon_pay_available filter to override Amazon Pay feature flag
* Dev - Add verbose debug logging mode to the OAuth connect flow
* Fix - Disable Amazon Pay when taxes are based on billing address and add notices with details
* Fix - Fix express checkout error for a Saudi Arabian address without state and postal code

[See changelog for full details across versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-stripe/trunk/changelog.txt).
