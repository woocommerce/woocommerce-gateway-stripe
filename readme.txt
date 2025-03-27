=== WooCommerce Stripe Payment Gateway ===
Contributors: woocommerce, automattic, royho, akeda, mattyza, bor0, woothemes
Tags: credit card, stripe, apple pay, payment request, google pay, sepa, bancontact, alipay, giropay, ideal, p24, woocommerce, automattic
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 9.3.1
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
5. Accept in-person payments for products and services using the Stripe Terminal M2 card reader.
6. Get started with no additional hardware using Tap to Pay on iPhone, or Tap to Pay on Android.

== Changelog ==

= 9.4.0 - xxxx-xx-xx =
* Dev - Do not generate filenames with underscores.
* Fix - Fixes the Stripe checkout container visuals when Smart Checkout is disabled.
* Dev - Implements the new Stripe order class into the express checkout classes.
* Dev - Implements the new Stripe order class into the wp-admin related classes.
* Dev - Replaces references to order status values with their respective constants from the WooCommerce plugin.
* Tweak - Updates the Smart Checkout (classic/shortcode checkout version) to make all the payment methods look as similar as possible to any other WooCommerce payment method.
* Tweak - Updates the Smart Checkout (block checkout version) to make all the payment methods look as similar as possible to any other WooCommerce payment method.
* Fix - Improves the subscriptions detached admin notice, making it less intrusive and limiting the querying to 5 subscriptions (avoiding slow loading times).
* Dev - Implements the new Stripe order class into the PHP unit tests.
* Dev - Introduces new payment method constants for the express methods: Google Pay, Apple Pay, Link, and Amazon Pay (backend version).
* Dev - Introduces a new Stripe Order class to wrap Stripe-specific logic and data on the backend.
* Dev - Improves how we handle express payment method titles by introducing new constants and methods to replace duplicate code.
* Fix - Fixes an issue where the order signature retrieval method could throw a fatal error when the received order parameter is actually an OrderRefund object (instead of a WC_Order).
* Fix - Fixes a possible fatal error when a product added to the cart cannot be found (with Payment Request Buttons).
* Add - Add Amazon Pay payment method class.
* Add - Implements the Single Payment Element feature for the new checkout experience on the classic/shortcode checkout page.
* Tweak - Record a Tracks event when enabling/disabling SPE
* Tweak - Updates the Single Payment Element setting copy. Now it is labeled "Smart Checkout".
* Update - Enable/disable Amazon Pay by adding/removing it from the enabled payment methods list.
* Add - Add ACSS payment tokenization.
* Add - Add BLIK payment method.
* Fix - Prevent reuse of payment intents when order total doesn't match intent amount.
* Update - Update payment method type for Amazon Pay orders.
* Fix - Compatibility with email preview in the Auth Requested email
* Update - Update Alipay and bank debit icons.
* Tweak - Update payment method type check for charge.succeeded webhook.
* Add - Disable unsupported payment methods in Stripe settings
* Update - Update handling of PR as a country in the terminal locations endpoint.
* Fix - Hide Amazon Pay in settings when legacy checkout is enabled.
* Fix - Fix subscription renewal issues for Amazon Pay.
* Tweak - SPE: Remove radio buttons
* Update - Hide express checkout buttons when no product variation is selected.
* Fix - Translation warning when initializing the status page information.
* Add - Add ACSS support for WC Subscriptions.

[See changelog for all versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-stripe/trunk/changelog.txt).
