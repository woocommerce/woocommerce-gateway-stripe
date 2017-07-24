REST API: Settings
==================

The settings REST API allows you to view and update WooCommerce Stripe settings.
The endpoints, requests, and responses share similar structure with [WooCommerce core setting options REST API](https://woocommerce.github.io/woocommerce-rest-api-docs/?shell#setting-options).

## Setting option properties

| Name                | Type   | Description                           | Access Type |
| ------------------- | ------ | ------------------------------------- | ----------- |
| `id`                | string | A unique identifier for the setting.  | `READ-ONLY` |
| `payment_method_id` | string | Payment method ID.                    | `READ-ONLY` |
| `label`             | string | A human readable label for the setting used in interfaces. | `READ-ONLY` |
| `description`       | string | A human readable description for the setting used in interfaces. | `READ-ONLY` |
| `value`             | mixed  | Setting value. | `READ-WRITE` |
| `default`           | mixed  | Default value for the setting. | `READ-ONLY` |
| `tip`               | string | Additional help text shown to the user about the setting. | `READ-ONLY` |
| `placeholder`       | string | Placeholder text to be displayed in text inputs. | `READ-ONLY` |
| `type`              | string | Type of setting. Options: `text`, `email`, `number`, `color`, `password`, `textarea`, `select`, `multiselect`, `radio`, `image_width `and `checkbox`. | `READ-ONLY` |
| `options`           | object | Array of options (key value pairs) for inputs such as select, multiselect, and radio buttons. | `READ-ONLY` |


## List all setting options

List all WooCommerce Stripe setting options.

### Capability required

* `manage_woocommerce`

### Request

```
GET /wp-json/wc/v2/stripe/settings
```

### Response

```
Status: 200 OK
```

```json
[
	{
		"id": "woocommerce_stripe_enabled",
		"payment_method_id": "stripe",
		"label": "Enable/Disable",
		"description": "",
		"default": "no",
		"tip": "",
		"value": "yes",
		"_links": {
			"self": [
				{
					"href": "https://example.com/wp-json/wc/v2/stripe/settings/woocommerce_stripe_enabled"
				}
			],
			"collection": [
				{
					"href": "https://example.com/wp-json/wc/v2/stripe/settings"
				}
			]
		}
	},
	{
		"id": "woocommerce_stripe_title",
		"payment_method_id": "stripe",
		"label": "Description",
		"description": "This controls the title which the user sees during checkout.",
		"default": "Credit Card (Stripe)",
		"tip": "This controls the title which the user sees during checkout.",
		"value": "Credit Card (Stripe)",
		"_links": {
			"self": [
				{
					"href": "https://example.com/wp-json/wc/v2/stripe/settings/woocommerce_stripe_title"
				}
			],
			"collection": [
				{
					"href": "https://example.com/wp-json/wc/v2/stripe/settings"
				}
			]
		}
	},
	{
		"id": "woocommerce_stripe_description",
		"payment_method_id": "stripe",
		"label": "Description",
		"description": "This controls the description which the user sees during checkout.",
		"default": "Pay with your credit card via Stripe.",
		"tip": "This controls the description which the user sees during checkout.",
		"value": "Pay via Stripe",
		"_links": {
			"self": [
				{
					"href": "https://example.com/wp-json/wc/v2/stripe/settings/woocommerce_stripe_description"
				}
			],
			"collection": [
				{
					"href": "https://example.com/wp-json/wc/v2/stripe/settings"
				}
			]
		}
	}
]
```

## Get a single setting option

Get a single WooCommerce Stripe setting option.

### Capability required

* `manage_woocommerce`

### Request

```
GET /wp-json/wc/v2/stripe/settings/<setting_id>
```

### Response

```
Status: 200 OK
```

```json
{
	"id": "woocommerce_stripe_enabled",
	"payment_method_id": "stripe",
	"label": "Enable/Disable",
	"description": "",
	"default": "no",
	"tip": "",
	"value": "yes",
	"_links": {
		"self": [
			{
				"href": "https://example.com/wp-json/wc/v2/stripe/settings/woocommerce_stripe_enabled"
			}
		],
		"collection": [
			{
				"href": "https://example.com/wp-json/wc/v2/stripe/settings"
			}
		]
	}
}
```
## Update a setting option

Update a WooCommerce Stripe setting option.

### Capability required

* `manage_woocommerce`

### Request

```
PUT /wp-json/wc/v2/stripe/settings/<setting_id>
```

Example request:

```
curl -X PUT https://example.com/wp-json/wc/v2/stripe/settings/woocommerce_stripe_enabled \
	-u consumer_key:consumer_secret \
	-H "Content-Type: application/json" \
	-d '{"value": "no"}'
```

### Response

```
Status: 200 OK
```

```
{
	"id": "woocommerce_stripe_enabled",
	"payment_method_id": "stripe",
	"label": "Enable/Disable",
	"description": "",
	"default": "no",
	"tip": "",
	"value": "no",
	"_links": {
		"self": [
			{
				"href": "https://example.com/wp-json/wc/v2/stripe/settings/woocommerce_stripe_enabled"
			}
		],
		"collection": [
			{
				"href": "https://example.com/wp-json/wc/v2/stripe/settings"
			}
		]
	}
}
```
## Batch update setting options

Batch update multiple WooCommerce Stripe setting options.

### Capability required

* `manage_woocommerce`

### Request

```
POST /wp-json/wc/v2/stripe/settings/batch
```

Example request:

```
curl -X POST https://example.com/wp-json/wc/v2/stripe/settings/woocommerce_stripe_enabled \
	-u consumer_key:consumer_secret \
	-H "Content-Type: application/json" \
	-d '{
		"update": [
			{
				"id": "woocommerce_stripe_enabled",
				"value": "yes"
			},
			{
				"id": "woocommerce_stripe_title",
				"value": "Stripe"
			}
		]
	}'
```

### Response

```
Status: 200 OK
```

```json
[
	{
		"id": "woocommerce_stripe_enabled",
		"payment_method_id": "stripe",
		"label": "Enable/Disable",
		"description": "",
		"default": "no",
		"tip": "",
		"value": "yes",
		"_links": {
			"self": [
				{
					"href": "https://example.com/wp-json/wc/v2/stripe/settings/woocommerce_stripe_enabled"
				}
			],
			"collection": [
				{
					"href": "https://example.com/wp-json/wc/v2/stripe/settings"
				}
			]
		}
	},
	{
		"id": "woocommerce_stripe_title",
		"payment_method_id": "stripe",
		"label": "Description",
		"description": "This controls the title which the user sees during checkout.",
		"default": "Credit Card (Stripe)",
		"tip": "This controls the title which the user sees during checkout.",
		"value": "Stripe",
		"_links": {
			"self": [
				{
					"href": "https://example.com/wp-json/wc/v2/stripe/settings/woocommerce_stripe_title"
				}
			],
			"collection": [
				{
					"href": "https://example.com/wp-json/wc/v2/stripe/settings"
				}
			]
		}
	}
]
```
