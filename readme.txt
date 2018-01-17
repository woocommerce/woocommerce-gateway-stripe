=== WooCommerce Stripe Payment Gateway ===
Contributors: automattic, royho, akeda, mattyza, bor0, woothemes
Tags: credit card, stripe, woocommerce
Requires at least: 4.4
Tested up to: 4.9
Requires PHP: 5.6
Stable tag: 4.0.2
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

= 4.0.2 - 2018-01-17 =
* Add - 3DS support on Stripe Checkout ( Modal Popup ).
* Add - Filter to enable Payment Request Button on Checkout 'wc_stripe_show_payment_request_on_checkout'.
* Add - Filter to remove all fields from checkout validation. 'wc_stripe_validate_checkout_all_fields'.
* Add - Filter to remove required fields from checkout validation. 'wc_stripe_validate_checkout_required_fields'.
* Add - Custom error exceptions class.
* Add - Credit card field names for clarity.
* Fix - Possible conflicts with payment request totals when subscriptions is active.
* Fix - Undefined notice on save card via subscriptions if card is deleted.
* Fix - Error translation issue when 3DS is required.
* Fix - Possible 404 if getting customer source if no id was found.
* Fix - Add payment method was still available when save cards setting is disabled.
* Fix - In certain cases when webhook is used, refunds can show as doubled when it is not.
* Fix - Stripe checkout with bitcoin returns error on processing.
* Tweak - Remove Sofort country selection in favor of using the billing country.
* Tweak - Add payment page CSS styling.
* Tweak - Error log to show full response object.

= 4.0.1 - 2018-01-11 =
* Fix - Add payment method conflict with terms and agreement page.
* Fix - Checkout validation checkout field names/labels were not translated.
* Fix - Card error translations.
* Add - Deprecated Apple Pay Class to prevent errors.

= 4.0.0 - 2018-01-08 =
* Add - Stripe Elements Credit Card form for PCI compliance.
* Add - Stripe Sources.
* Add - SEPA Direct Debit.
* Add - Bancontact.
* Add - Giropay.
* Add - Sofort.
* Add - iDeal.
* Add - Bitcoin.
* Add - P24.
* Add - Alipay.

= 3.2.3 - 2017-08-23 =
* Fix - Apple Pay action hook with wrong parameter causing errors.

= 3.2.2 - 2017-07-10 =
* Fix - Apple Pay button displaying in non Safari browser.
* Fix - Apple Pay with coupon not applying to total.

= 3.2.1 =
* Fix - Discounts were not applying to total with Apple Pay.

= 3.2.0 =
* Fix - Authorized first orders were not able to be refund.
* Fix - Payment Request not honoring different shipping address in certain cases.
* Fix - In certain Chrome versions, states are not abbreviated causing shipping validation issues with Payment Request API.
* Add - Support for Dynamic Pricing.

= 3.1.9 =
* Fix - Handle a subscription renewal failed payment order correctly to prevent orders going into onhold status.
* Fix - Auto accept terms for Payment Request API to prevent blocker for the checkout.
* Fix - Add payment method via Stripe checkout button showed pricing.
* Fix - When Stripe customer id is no longer linked to Stripe account, customer not found can happen.
* Fix - On Subscription failed payment, new payment info not updating causing failed payments for recurring charges.
* Add - Action hook to manipulate process response from API "wc_gateway_stripe_process_response".
* Add - Apple Pay compatibility with WooCommerce Sequential Numbers Pro.

= 3.1.8 =
* Fix - Legacy < WC 3.0 stripe checkout file reference link name causing file not found.

= 3.1.7 =
* Fix - Additional WC 3.0 compatibility with subscriptions addons.
* Fix - Retry failed subscription payments with customer ID.
* Add - Site URL to metadata when charging subscription orders for reference.

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
* New - Minimum PHP 5.6.

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

= 4.0.2 =
* Add - 3DS support on Stripe Checkout ( Modal Popup ).
* Add - Filter to enable Payment Request Button on Checkout 'wc_stripe_show_payment_request_on_checkout'.
* Add - Filter to remove all fields from checkout validation. 'wc_stripe_validate_checkout_all_fields'.
* Add - Filter to remove required fields from checkout validation. 'wc_stripe_validate_checkout_required_fields'.
* Add - Custom error exceptions class.
* Add - Credit card field names for clarity.
* Fix - Possible conflicts with payment request totals when subscriptions is active.
* Fix - Undefined notice on save card via subscriptions if card is deleted.
* Fix - Error translation issue when 3DS is required.
* Fix - Possible 404 if getting customer source if no id was found.
* Fix - Add payment method was still available when save cards setting is disabled.
* Fix - In certain cases when webhook is used, refunds can show as doubled when it is not.
* Fix - Stripe checkout with bitcoin returns error on processing.
* Tweak - Remove Sofort country selection in favor of using the billing country.
* Tweak - Add payment page CSS styling.
* Tweak - Error log to show full response object.
