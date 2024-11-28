REST API
========

Since WooCommerce 3.0.0, there's [payment gateways API](https://woocommerce.github.io/woocommerce-rest-api-docs/?shell#payment-gateways)
that allows you to view and update individual payment gateways. This document
explains how payment gateways API is used to manage WooCommerce Stripe gateway.

Table of Contents
=================

- [REST API](#rest-api)
- [Table of Contents](#table-of-contents)
	- [Retrieve Stripe Payment Gateway](#retrieve-stripe-payment-gateway)
		- [Capability required](#capability-required)
		- [Request](#request)
		- [Response](#response)
		- [Notes](#notes)
	- [Update Stripe Payment Gateway](#update-stripe-payment-gateway)
		- [Capability required](#capability-required-1)
		- [Request](#request-1)
		- [Response](#response-1)
		- [Notes](#notes-1)

## Retrieve Stripe Payment Gateway

### Capability required

* `manage_woocommerce`

### Request

```
GET /wp-json/wc/v2/payment_gateways/stripe
```

Example request:

```
curl -X GET https://example.com/wp-json/wc/v2/payment_gateways/stripe -u consumer_key:consumer_secret
```

### Response

```
Status: 200 OK
```

```json
{
  "id": "stripe",
  "title": "Stripe",
  "description": "Pay with your credit card via Stripe. TEST MODE ENABLED. In test mode, you can use the card number 4242424242424242 with any CVC and a valid expiration date or check the documentation \"<a href=\"https://docs.stripe.com/testing\">Testing Stripe</a>\" for more card numbers.",
  "order": 4,
  "enabled": true,
  "method_title": "Stripe",
  "method_description": "Stripe works by adding credit card fields on the checkout and then sending the details to Stripe for verification. <a href=\"https://dashboard.stripe.com/register\" target=\"_blank\">Sign up</a> for a Stripe account, and <a href=\"https://dashboard.stripe.com/account/apikeys\" target=\"_blank\">get your Stripe account keys</a>.",
  "settings": {
    "title": {
      "id": "title",
      "label": "Title",
      "description": "This controls the title which the user sees during checkout.",
      "type": "text",
      "value": "Stripe",
      "default": "Credit Card (Stripe)",
      "tip": "This controls the title which the user sees during checkout.",
      "placeholder": ""
    },
    "testmode": {
      "id": "testmode",
      "label": "Enable Test Mode",
      "description": "Place the payment gateway in test mode using test API keys.",
      "type": "checkbox",
      "value": "yes",
      "default": "yes",
      "tip": "Place the payment gateway in test mode using test API keys.",
      "placeholder": ""
    },
    "test_publishable_key": {
      "id": "test_publishable_key",
      "label": "Test Publishable Key",
      "description": "Get your API keys from your stripe account.",
      "type": "text",
      "value": "pk_test_xxx",
      "default": "",
      "tip": "Get your API keys from your stripe account.",
      "placeholder": ""
    },
    "test_secret_key": {
      "id": "test_secret_key",
      "label": "Test Secret Key",
      "description": "Get your API keys from your stripe account.",
      "type": "text",
      "value": "sk_test_xxx",
      "default": "",
      "tip": "Get your API keys from your stripe account.",
      "placeholder": ""
    },
    "publishable_key": {
      "id": "publishable_key",
      "label": "Live Publishable Key",
      "description": "Get your API keys from your stripe account.",
      "type": "text",
      "value": "",
      "default": "",
      "tip": "Get your API keys from your stripe account.",
      "placeholder": ""
    },
    "secret_key": {
      "id": "secret_key",
      "label": "Live Secret Key",
      "description": "Get your API keys from your stripe account.",
      "type": "text",
      "value": "",
      "default": "",
      "tip": "Get your API keys from your stripe account.",
      "placeholder": ""
    },
    "statement_descriptor": {
      "id": "statement_descriptor",
      "label": "Statement Descriptor",
      "description": "Extra information about a charge. This will appear on your customer’s credit card statement.",
      "type": "text",
      "value": "Local WP Dev",
      "default": "",
      "tip": "Extra information about a charge. This will appear on your customer’s credit card statement.",
      "placeholder": ""
    },
    "capture": {
      "id": "capture",
      "label": "Capture charge immediately",
      "description": "Whether or not to immediately capture the charge. When unchecked, the charge issues an authorization and will need to be captured later. Uncaptured charges expire in 7 days.",
      "type": "checkbox",
      "value": "yes",
      "default": "yes",
      "tip": "Whether or not to immediately capture the charge. When unchecked, the charge issues an authorization and will need to be captured later. Uncaptured charges expire in 7 days.",
      "placeholder": ""
    },
    "request_payment_api": {
      "id": "request_payment_api",
      "label": "Enable Payment Request API",
      "description": "If enabled, users will be able to pay using the Payment Request API if supported by the browser.",
      "type": "checkbox",
      "value": "no",
      "default": "no",
      "tip": "If enabled, users will be able to pay using the Payment Request API if supported by the browser.",
      "placeholder": ""
    },
    "apple_pay": {
      "id": "apple_pay",
      "label": "Enable Apple Pay. <br />By using Apple Pay, you agree to <a href=\"https://stripe.com/apple-pay/legal\" target=\"_blank\">Stripe</a> and <a href=\"https://developer.apple.com/apple-pay/acceptable-use-guidelines-for-websites/\" target=\"_blank\">Apple</a>'s terms of service.",
      "description": "If enabled, users will be able to pay with Apple Pay.",
      "type": "checkbox",
      "value": "no",
      "default": "yes",
      "tip": "If enabled, users will be able to pay with Apple Pay.",
      "placeholder": ""
    },
    "apple_pay_button": {
      "id": "apple_pay_button",
      "label": "Button Style",
      "description": "Select the button style you would like to show.",
      "type": "select",
      "value": "black",
      "default": "black",
      "tip": "Select the button style you would like to show.",
      "placeholder": "",
      "options": {
        "black": "Black",
        "white": "White"
      }
    },
    "apple_pay_button_lang": {
      "id": "apple_pay_button_lang",
      "label": "Apple Pay Button Language",
      "description": "Enter the 2 letter ISO code for the language you would like your Apple Pay Button to display in. Reference available ISO codes <a href=\"http://www.w3schools.com/tags/ref_language_codes.asp\" target=\"_blank\">here</a>.",
      "type": "text",
      "value": "en",
      "default": "en",
      "tip": "Enter the 2 letter ISO code for the language you would like your Apple Pay Button to display in. Reference available ISO codes <a href=\"http://www.w3schools.com/tags/ref_language_codes.asp\" target=\"_blank\">here</a>.",
      "placeholder": ""
    },
    "saved_cards": {
      "id": "saved_cards",
      "label": "Enable Payment via Saved Cards",
      "description": "If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Stripe servers, not on your store.",
      "type": "checkbox",
      "value": "yes",
      "default": "no",
      "tip": "If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Stripe servers, not on your store.",
      "placeholder": ""
    },
    "logging": {
      "id": "logging",
      "label": "Log debug messages",
      "description": "Save debug messages to the WooCommerce System Status log.",
      "type": "checkbox",
      "value": "yes",
      "default": "no",
      "tip": "Save debug messages to the WooCommerce System Status log.",
      "placeholder": ""
    }
  },
  "_links": {
    "self": [
      {
        "href": "https://local.wordpress.dev/wp-json/wc/v2/payment_gateways/stripe"
      }
    ],
    "collection": [
      {
        "href": "https://local.wordpress.dev/wp-json/wc/v2/payment_gateways"
      }
    ]
  }
}
```

### Notes

In WooCommerce Stripe 4.0.0, there will be multiple payment methods to support
[Stripe Sources](https://docs.stripe.com/sources). For example, there will be
[`stripe_bancontact`](https://github.com/woocommerce/woocommerce-gateway-stripe/blob/3041f46f4b1b5d25b24be25767e0387f0cdf3f96/includes/payment-methods/class-wc-gateway-stripe-bancontact.php#L59) payment method
in addition to `stripe` payment method. You can request `stripe_bancontact` with:

```
GET /wp-json/wc/v2/payment_gateways/stripe_bancontact
```

or use the following request which lists all available payment methods in a store with all the settings:

```
GET /wp-json/wc/v2/payment_gateways
```

Using the latter will saves you from multiple HTTP requests. See [list all payment gateways](https://woocommerce.github.io/woocommerce-rest-api-docs/?shell#list-all-payment-gateways).

## Update Stripe Payment Gateway

### Capability required

* `manage_woocommerce`

### Request


```
PUT /wp-json/wc/v2/payment_gateways/stripe
```

Example request:

```
curl -u consumer_key:consumer_secret -X PUT \
  'https://example.com/wp-json/wc/v2/payment_gateways/stripe' \
  -H 'Content-Type: application/json' \
  -d '{
   "enabled": true,
   "title": "Stripe",
   "settings": {
     "request_payment_api": "yes"
   }
  }'
```

### Response

```json
{
  "id": "stripe",
  "title": "Stripe",
  "description": "Pay with your credit card via Stripe. TEST MODE ENABLED. In test mode, you can use the card number 4242424242424242 with any CVC and a valid expiration date or check the documentation \"<a href=\"https://docs.stripe.com/testing\">Testing Stripe</a>\" for more card numbers.",
  "order": 4,
  "enabled": true,
  "method_title": "Stripe",
  "method_description": "Stripe works by adding credit card fields on the checkout and then sending the details to Stripe for verification. <a href=\"https://dashboard.stripe.com/register\" target=\"_blank\">Sign up</a> for a Stripe account, and <a href=\"https://dashboard.stripe.com/account/apikeys\" target=\"_blank\">get your Stripe account keys</a>.",
  "settings": {
    "title": {
      "id": "title",
      "label": "Title",
      "description": "This controls the title which the user sees during checkout.",
      "type": "text",
      "value": "Stripe",
      "default": "Credit Card (Stripe)",
      "tip": "This controls the title which the user sees during checkout.",
      "placeholder": ""
    },
    "testmode": {
      "id": "testmode",
      "label": "Enable Test Mode",
      "description": "Place the payment gateway in test mode using test API keys.",
      "type": "checkbox",
      "value": "yes",
      "default": "yes",
      "tip": "Place the payment gateway in test mode using test API keys.",
      "placeholder": ""
    },
    "test_publishable_key": {
      "id": "test_publishable_key",
      "label": "Test Publishable Key",
      "description": "Get your API keys from your stripe account.",
      "type": "text",
      "value": "pk_test_6yDxWFhi5CXpx6XFCOGG3CBn",
      "default": "",
      "tip": "Get your API keys from your stripe account.",
      "placeholder": ""
    },
    "test_secret_key": {
      "id": "test_secret_key",
      "label": "Test Secret Key",
      "description": "Get your API keys from your stripe account.",
      "type": "text",
      "value": "sk_test_A2hkmT99Tmnd9KSr9KpafY3s",
      "default": "",
      "tip": "Get your API keys from your stripe account.",
      "placeholder": ""
    },
    "publishable_key": {
      "id": "publishable_key",
      "label": "Live Publishable Key",
      "description": "Get your API keys from your stripe account.",
      "type": "text",
      "value": "",
      "default": "",
      "tip": "Get your API keys from your stripe account.",
      "placeholder": ""
    },
    "secret_key": {
      "id": "secret_key",
      "label": "Live Secret Key",
      "description": "Get your API keys from your stripe account.",
      "type": "text",
      "value": "",
      "default": "",
      "tip": "Get your API keys from your stripe account.",
      "placeholder": ""
    },
    "statement_descriptor": {
      "id": "statement_descriptor",
      "label": "Statement Descriptor",
      "description": "Extra information about a charge. This will appear on your customer’s credit card statement.",
      "type": "text",
      "value": "Local WP Dev",
      "default": "",
      "tip": "Extra information about a charge. This will appear on your customer’s credit card statement.",
      "placeholder": ""
    },
    "capture": {
      "id": "capture",
      "label": "Capture charge immediately",
      "description": "Whether or not to immediately capture the charge. When unchecked, the charge issues an authorization and will need to be captured later. Uncaptured charges expire in 7 days.",
      "type": "checkbox",
      "value": "yes",
      "default": "yes",
      "tip": "Whether or not to immediately capture the charge. When unchecked, the charge issues an authorization and will need to be captured later. Uncaptured charges expire in 7 days.",
      "placeholder": ""
    },
    "request_payment_api": {
      "id": "request_payment_api",
      "label": "Enable Payment Request API",
      "description": "If enabled, users will be able to pay using the Payment Request API if supported by the browser.",
      "type": "checkbox",
      "value": "no",
      "default": "no",
      "tip": "If enabled, users will be able to pay using the Payment Request API if supported by the browser.",
      "placeholder": ""
    },
    "apple_pay": {
      "id": "apple_pay",
      "label": "Enable Apple Pay. <br />By using Apple Pay, you agree to <a href=\"https://stripe.com/apple-pay/legal\" target=\"_blank\">Stripe</a> and <a href=\"https://developer.apple.com/apple-pay/acceptable-use-guidelines-for-websites/\" target=\"_blank\">Apple</a>'s terms of service.",
      "description": "If enabled, users will be able to pay with Apple Pay.",
      "type": "checkbox",
      "value": "no",
      "default": "yes",
      "tip": "If enabled, users will be able to pay with Apple Pay.",
      "placeholder": ""
    },
    "apple_pay_button": {
      "id": "apple_pay_button",
      "label": "Button Style",
      "description": "Select the button style you would like to show.",
      "type": "select",
      "value": "black",
      "default": "black",
      "tip": "Select the button style you would like to show.",
      "placeholder": "",
      "options": {
        "black": "Black",
        "white": "White"
      }
    },
    "apple_pay_button_lang": {
      "id": "apple_pay_button_lang",
      "label": "Apple Pay Button Language",
      "description": "Enter the 2 letter ISO code for the language you would like your Apple Pay Button to display in. Reference available ISO codes <a href=\"http://www.w3schools.com/tags/ref_language_codes.asp\" target=\"_blank\">here</a>.",
      "type": "text",
      "value": "en",
      "default": "en",
      "tip": "Enter the 2 letter ISO code for the language you would like your Apple Pay Button to display in. Reference available ISO codes <a href=\"http://www.w3schools.com/tags/ref_language_codes.asp\" target=\"_blank\">here</a>.",
      "placeholder": ""
    },
    "saved_cards": {
      "id": "saved_cards",
      "label": "Enable Payment via Saved Cards",
      "description": "If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Stripe servers, not on your store.",
      "type": "checkbox",
      "value": "yes",
      "default": "no",
      "tip": "If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Stripe servers, not on your store.",
      "placeholder": ""
    },
    "logging": {
      "id": "logging",
      "label": "Log debug messages",
      "description": "Save debug messages to the WooCommerce System Status log.",
      "type": "checkbox",
      "value": "yes",
      "default": "no",
      "tip": "Save debug messages to the WooCommerce System Status log.",
      "placeholder": ""
    }
  },
  "_links": {
    "self": [
      {
        "href": "https://local.wordpress.dev/wp-json/wc/v2/payment_gateways/stripe"
      }
    ],
    "collection": [
      {
        "href": "https://local.wordpress.dev/wp-json/wc/v2/payment_gateways"
      }
    ]
  }
}
```

### Notes

If you need to update WooCommerce Stripe setting fields, you need to wrap it in `settings`
property as shown in example request above. Fields for `title`, `description`,
`enabled`, and `order` must be set in the top level object of JSON request body.
