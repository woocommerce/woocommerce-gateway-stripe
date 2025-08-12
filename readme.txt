=== WooCommerce Stripe Payment Gateway ===
Contributors: woocommerce, automattic, royho, akeda, mattyza, bor0, woothemes
Tags: credit card, stripe, payments, woocommerce, woo
Requires at least: 6.6
Tested up to: 6.8.2
Requires PHP: 7.4
Stable tag: 9.8.0
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

= 9.8.0 - 2025-08-11 =

**New Features**

* Optimized Checkout is now available to all users via a configuration setting.
  - Optimized Checkout maximizes conversion by displaying the most relevant payment methods for each customer.
* After several consecutive 401 (Unauthorized) responses, we will now temporarily stop making Stripe API calls to prevent further authentication failures. API calls will resume automatically after a cooldown period.
* When we detect the official Affirm or Klarna plugin is active, we will deactivate the related Stripe payment method.

**Important Fixes and Updates**

* Fix - Reduce number of calls to Stripe payment_methods API
* Fix - Fixes issues related to booking multiple slots with express checkout payment methods enabled
* Fix - 3DS authentication modal not shown when using Google Pay
* Fix - Remove validation error check from classic checkout before payment method creation
* Fix - Only clear customer cache when an action has been performed
* Fix - Free trial subscription orders with payment methods that require redirection (eg: iDeal, Bancontact)
* Add - Adds a new bulk action option to the subscriptions listing screen to check for detached payment methods
* Update - Improve Stripe API connector logging to include request/response context

**Other Fixes**

* Fix - Require credit cards to be enabled before Apple Pay and Google Pay can be enabled in PMC
* Fix - Force the card payment method to be enabled when the Optimized Checkout is enabled in the merchant's Payment Method Configuration
* Fix - Handle missing customer when calling payment_methods API
* Add - Adds the current setting value for the Optimized Checkout to the Stripe System Status Report data
* Add - A new pill to the payment methods page to indicate the credit card requirement when the Optimized Checkout feature is enabled
* Fix - Update the Optimized Checkout promotional inbox note to link to the relevant section in the Stripe settings page
* Add - Introduces a new banner to promote the Optimized Checkout feature in the Stripe settings page for versions 9.8 and above
* Add - Introduces a new inbox note to promote the Optimized Checkout feature on version 9.8 and later
* Tweak - Use wp_ajax prefix for its built-in security for Add Payment Method action
* Update - Removes the ability to change the title for the Optimized Checkout payment element, as it is now set to "Stripe" by default
* Fix - Add `get_icon_url()` to Payment Method base class

**Internal Changes and Upcoming Features**

* Add - Tracks the toggle of the Optimized Checkout feature in the promotional banner
* Dev - Use product type constants that were added in WooCommerce 9.7
* Dev - Removes the inclusion of the deprecated WC_Stripe_Order class
* Tweak - Update checkout error message for invalid API key to be more generic and user-friendly
* Update - Copy for the Optimized Checkout settings and notices
* Tweak - Disable Amazon Pay in the merchant's Payment Method Configuration object if it is still behind a feature flag
* Dev - Clean up LPM (Local Payment Method) feature flags and related code
* Dev - Move some testing and compiler node dependencies to devDependencies
* Dev - Minor CSS change to comply with a SASS rule deprecation
* Dev - Update SCSS to replace @import with @use and @forward

[See changelog for full details across versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-stripe/trunk/changelog.txt).
