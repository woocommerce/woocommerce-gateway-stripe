=== WooCommerce Stripe Payment Gateway ===
Contributors: woocommerce, automattic, royho, akeda, mattyza, bor0, woothemes
Tags: credit card, stripe, payments, woocommerce, woo
Requires at least: 6.6
Tested up to: 6.8.2
Requires PHP: 7.4
Stable tag: 9.9.0
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

= 9.9.0 - 2025-09-08 =

**New Features**

* Add - Setting to allow merchants to control the layout of the Optimized Checkout payment element on the checkout page
* Add - Adds a new action (`wc_stripe_webhook_received`) to allow additional actions to be taken for webhook notifications from Stripe

**Important Fixes and Updates**

* Update - Show all available payment methods before unavailable payment methods
* Update - Increases the default font size for the Optimized Checkout payment element to match the rest of the checkout form
* Fix - The availability of the Link payment method when the Optimized Checkout is enabled
* Fix - Removes the credit card payment method requirement for the Optimized Checkout feature
* Fix - Checks for the subscription payment method (if it is Stripe) when verifying for the payment method detachment
* Fix - Fix unnecessary Stripe API calls when rendering subscription details
* Fix - Allow checkout for logged-in users without an email in their account when a billing email is provided
* Fix - Payment method test instructions not showing up for the Optimized Checkout payment element
* Update - Add nightly task and WooCommerce tool to remove stale entries from our database cache

**Other Fixes**

* Fix - Use the item name as fallback when normalizing line items for the express checkout
* Fix - Ensure all Javascript strings use the correct text domain for translation
* Fix - Display only Apple Pay and Google Pay buttons in the Customize page preview
* Fix - Fix some PHP warnings
* Add - Includes a new notice to highlight the Optimized Checkout feature above the payment methods list in the Stripe settings page
* Tweak - Use smaller image for Optimized Checkout banner

**Internal Changes and Upcoming Features**

* Update - Upgrade React to v18 and related dependencies
* Dev - Adds PMC setting information to the Payment Intent object metadata
* Dev - Adds debug information to the Payment Intent object metadata
* Dev - Normalize intent metadata to yes/no/null values
* Dev - Simplifies the way test payment instructions are built when the Optimized Checkout is enabled
* Dev - Upgrades the ESLint packages and their related libraries
* Dev - Upgrade the Prettier package and related libraries, and apply updated formatting rules
* Dev - Extracts Optimized Checkout logic from card payment method into new WC_Stripe_UPE_Payment_Method_OC class
* Dev - Update Javascript unit tests for compatibility with Node 20
* Dev - Replaces some payment method instantiation logic for the Optimized Checkout with calls to the `get_payment_method_instance` method
* Dev - Multiple lint fixes in preparation for the Node 20 upgrade
* Dev - Introduces a new helper method to identify Stripe orders
* Dev - Implements WooCommerce constants for the tax statuses
* Dev - Update WooCommerce Subscriptions e2e tests after 7.8.0 release
* Dev - Make 'Add to cart' more robust in e2e tests
* Dev - Ensure e2e tests enable or disable Optimized Checkout during setup
* Tweak - Use more specific selector in express checkout e2e tests
* Tweak - Small improvements to e2e tests

[See changelog for full details across versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-stripe/trunk/changelog.txt).
