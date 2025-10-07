=== WooCommerce Stripe Payment Gateway ===
Contributors: woocommerce, automattic, royho, akeda, mattyza, bor0, woothemes
Tags: credit card, stripe, payments, woocommerce, woo
Requires at least: 6.6
Tested up to: 6.8.2
Requires PHP: 7.4
Stable tag: 9.9.2
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

= 10.0.0 - xxxx-xx-xx =
* Update - Removes frontend code related to Payment Request Buttons in the checkout page
* Update - Disable Payment Request Buttons and ensure Express Checkout is used when express checkout buttons are enabled
* Dev - Expands the Stripe Order Helper class to handle source ID, refund ID, intent ID, and setup intent ID metas
* Update - Removing the `wc_stripe_is_upe_checkout_enabled` filter, as Legacy Checkout is no longer supported
* Dev - Upgrades `jest` to version 29.7.0, `@wordpress/scripts` to 26.19.0, and adds `axios`(version 1.12.2) to the JavaScript development dependencies
* Update - Shows the Stripe account connection modal in settings when the merchant did not connect via OAuth along with a new notice
* Update - The usage of SEPA Direct Debit as a saved payment method for iDEAL and Bancontact is now disabled by default
* Dev - Introduces a new helper class to handle Stripe orders
* Dev - Fixes a warning thrown when running Klarna payment token PHP Unit tests
* Dev - Fixes some possible warnings shown in the browser console when the Optimized Checkout payment element is instantiated with invalid parameters
* Dev - Renaming the Klarna payment token class to WC_Stripe_Klarna_Payment_Token
* Fix - Minor fixes and code improvements for the saved payment methods comparison logic
* Dev - Upgrades Node to v20
* Add - Allow the purchase of free trials using the Express Payment methods when the product does not require shipping
* Update - Changes the documentation page URL for the Optimized Checkout feature to https://woocommerce.com/document/stripe/admin-experience/optimized-checkout-suite/
* Update - Changes the background color and spacing for the Woo logo shown in the account modal
* Dev - Fix live reload issue with Webpack 5
* Update - Splits the "Enable SEPA for other methods" setting into two separate settings for Bancontact and iDEAL
* Dev - Upgrades the Webpack-related packages
* Dev - Upgrade the cross-env and rimraf NPM packages; remove chromedriver NPM dependency
* Dev - Removes three unused NPM script commands: `test`, `test:grep`, and `test:single`
* Dev - Upgrades the Babel-related packages
* Dev - Consolidate component used for unavailable payment methods
* Dev - Update webhook unit tests to be compatible with WooCommerce 10.2
* Add - Implement cache prefetch for payment method configuration
* Dev - Update the @woocommerce/navigation dependency
* Update - Reduce settings Javascript file size by using smaller image
* Dev - Update @wordpress/scripts to 30.24.0 and @wordpress/base-styles to 6.7.0

[See changelog for full details across versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-stripe/trunk/changelog.txt).
