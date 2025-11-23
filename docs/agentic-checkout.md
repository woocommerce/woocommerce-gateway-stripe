# Agentic Checkout

Agentic Checkout enables WooCommerce stores (as Stripe Connected Accounts) to receive and process orders initiated by AI agents through Stripe's Agentic Checkout Protocol (ACP).

## Table of Contents

- [Overview](#overview)
- [Requirements](#requirements)
- [How It Works](#how-it-works)
- [Setup and Configuration](#setup-and-configuration)
- [REST API Endpoints](#rest-api-endpoints)
- [Webhook Handling](#webhook-handling)
- [Manual Capture Workflow](#manual-capture-workflow)
- [Developer Hooks](#developer-hooks)
- [Troubleshooting](#troubleshooting)
- [Security](#security)

## Overview

### What is Agentic Checkout?

Agentic Checkout allows AI agents (such as ChatGPT, Google Gemini, and other AI assistants) to purchase products from your WooCommerce store on behalf of users. When a user asks an AI agent to buy something, the agent can discover your products, calculate pricing and shipping, and complete the purchase through Stripe's secure checkout process.

### Benefits for Merchants

- **Reach New Customers**: AI agents can discover and recommend your products to users
- **Seamless Integration**: Works with existing WooCommerce tax, shipping, and inventory systems
- **Secure Transactions**: All payments processed through Stripe with signature verification
- **Full Control**: Approve or decline orders before payment confirmation
- **Flexible Customization**: Extensive filter and action hooks for custom business logic

### Platform Model

- **Platform**: WooCommerce.com / Automattic's Stripe account
- **Connected Accounts**: Individual WooCommerce stores using this gateway
- **Enrollment**: Handled platform-side by Stripe/WooCommerce.com
- **Store Control**: Gated by PHP filter (opt-in required)

## Requirements

### Minimum Versions

- WordPress 6.0 or higher
- WooCommerce 8.0 or higher
- PHP 7.4 or higher

### Stripe Configuration

- Active Stripe account connected to WooCommerce
- Webhook secret configured in Stripe gateway settings
- Products must have SKUs that match Stripe Price `lookup_key` fields
- Product catalog synced to Stripe (handled by platform)

## How It Works

1. **Product Discovery**: AI agents discover your products through Stripe's catalog
2. **Checkout Initiation**: Agent creates a Checkout Session for the user
3. **Dynamic Calculations**: Stripe calls your store's REST API endpoints for:
   - Tax calculation (using WooCommerce tax engine)
   - Shipping options (using WooCommerce shipping zones)
   - Order approval (custom validation logic)
4. **Payment Processing**: Stripe confirms payment based on your responses
5. **Order Creation**: Your store receives a webhook and creates the WooCommerce order
6. **Order Fulfillment**: Order processed like any regular WooCommerce order

## Setup and Configuration

### Enable Agentic Checkout

Agentic Checkout is disabled by default. To enable it, add this filter to your theme's `functions.php` file or a custom plugin:

```php
add_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );
```

**Important**: Only enable this feature if:
- Your store is enrolled in Stripe's Agentic Checkout program
- You have configured webhook endpoints in Stripe Dashboard
- Your products have accurate SKUs matching Stripe Price lookup keys

### Configure Webhook Secret

1. Go to **WooCommerce → Settings → Payments → Stripe**
2. Scroll to **Webhook Settings**
3. Enter your webhook secret (get this from Stripe Dashboard)
4. Save changes

The webhook secret is used to verify that requests are genuinely from Stripe.

### Product Setup

Each product must have:
- A unique SKU
- SKU must match the `lookup_key` on the corresponding Stripe Price
- Accurate tax class configured
- Correct shipping class if product requires shipping

## REST API Endpoints

Agentic Checkout adds three REST API endpoints to your store. These endpoints are automatically registered when the feature is enabled and are called by Stripe during the checkout process.

### Manual Approval Hook

**Endpoint**: `POST /wc/v3/stripe/agentic/approve`

**Purpose**: Allows your store to approve or decline orders before payment is confirmed.

**Request Format**:
```json
{
  "id": "cs_test_123abc",
  "line_items": {
    "data": [
      {
        "price": {
          "lookup_key": "product-sku-123"
        },
        "quantity": 2
      }
    ]
  },
  "amount_total": 5000,
  "payment_method_details": {
    "type": "card"
  }
}
```

**Response Format (Approved)**:
```json
{
  "id": "cs_test_123abc",
  "result": {
    "type": "approved"
  }
}
```

**Response Format (Declined)**:
```json
{
  "id": "cs_test_123abc",
  "result": {
    "type": "declined",
    "declined": {
      "reason": "low_inventory"
    }
  }
}
```

**Validation Checks**:
- Products exist and SKUs are valid
- Products are purchasable
- Products are in stock
- Stock quantity is sufficient for order quantity
- Custom validation via `wc_stripe_agentic_approval_decision` filter

**Decline Reasons**:
- `product_not_found` - Product SKU doesn't exist
- `product_not_purchasable` - Product cannot be purchased
- `low_inventory` - Insufficient stock
- `validation_failed` - General validation failure
- Custom reasons via `wc_stripe_agentic_decline_reason` filter

### Tax Calculation Hook

**Endpoint**: `POST /wc/v3/stripe/agentic/compute_tax`

**Purpose**: Calculates taxes using your WooCommerce tax settings and customer location.

**Request Format**:
```json
{
  "livemode": false,
  "currency": "usd",
  "line_items_details": [
    {
      "sku_id": "product-sku-123",
      "unit_amount": 2500,
      "quantity": 2
    }
  ],
  "fulfillment_details": {
    "address": {
      "city": "San Francisco",
      "state": "CA",
      "postal_code": "94107",
      "country": "US"
    }
  },
  "billing_details": {
    "address": {
      "city": "San Francisco",
      "state": "CA",
      "postal_code": "94107",
      "country": "US"
    }
  }
}
```

**Response Format**:
```json
{
  "line_item_details": [
    {
      "sku_id": "product-sku-123",
      "amount_tax": 437
    }
  ],
  "fulfillment_details": {
    "amount_tax": 0
  },
  "total_details": {
    "amount_tax": 437
  }
}
```

**Tax Calculation**:
- Uses WooCommerce tax rates and zones
- Respects product tax classes
- Works with tax plugins (TaxJar, Avalara, etc.)
- Calculates based on shipping or billing address
- Amounts in cents (USD)

### Shipping Calculation Hook

**Endpoint**: `POST /wc/v3/stripe/agentic/compute_shipping_options`

**Purpose**: Returns available shipping methods and rates from WooCommerce.

**Request Format**:
```json
{
  "livemode": false,
  "currency": "usd",
  "line_items_details": [
    {
      "sku_id": "product-sku-123",
      "unit_amount": 2500,
      "quantity": 1
    }
  ],
  "fulfillment_details": {
    "address": {
      "city": "San Francisco",
      "state": "CA",
      "postal_code": "94107",
      "country": "US"
    }
  }
}
```

**Response Format**:
```json
{
  "fulfillment_options": [
    {
      "display_name": "Standard Shipping",
      "description": "5-7 business days",
      "shipping_amount": 1000,
      "earliest_delivery_time": 1663480800,
      "latest_delivery_time": 1663653600
    },
    {
      "display_name": "Express Shipping",
      "description": "2-3 business days",
      "shipping_amount": 2500,
      "earliest_delivery_time": 1663308000,
      "latest_delivery_time": 1663394400
    }
  ]
}
```

**Shipping Calculation**:
- Uses WooCommerce shipping zones
- Includes all enabled shipping methods
- Works with shipping plugins (ShipStation, WooCommerce Shipping, etc.)
- Skips non-shippable products (virtual/downloadable)
- Returns empty array if no shipping methods available

### Authentication

All three endpoints use Stripe webhook signature verification:
- Requests must include `Stripe-Signature` header
- Signature verified using webhook secret from settings
- Uses HMAC SHA256 with timestamp validation
- Requests must arrive within 5-minute window
- Invalid signatures receive 403 Forbidden response

## Webhook Handling

### checkout.session.completed Event

When a user completes an agentic checkout, Stripe sends a `checkout.session.completed` webhook to your store.

**Detection**: The webhook handler identifies agentic orders by:
- Checking for `agentic: true` in checkout session metadata
- Checking for `origin_context` field (may be added by Stripe)

**Order Creation Process**:

1. **Validate Webhook**: Verify Stripe signature
2. **Parse Session Data**: Extract customer, line items, payment details
3. **Create WooCommerce Order**:
   - Map line items to order products (by SKU)
   - Set customer billing/shipping addresses
   - Add shipping line item if present
   - Set payment method to "Stripe (Agentic Checkout)"
   - Store payment intent ID
4. **Set Order Status**:
   - `processing` (or `completed` for virtual products) if payment captured
   - `on-hold` if manual capture mode
   - `pending` if payment not confirmed
5. **Add Order Note**: "Order created via AI agent checkout (Agent Name)"
6. **Set Metadata**:
   - `_wc_stripe_agentic_order` = `true`
   - `_wc_stripe_checkout_session_id` = Checkout Session ID
   - `_stripe_intent_id` = Payment Intent ID (for manual capture)
7. **Trigger Actions**: Fire `wc_stripe_agentic_order_created` action
8. **Send Emails**: Standard WooCommerce order emails sent

**Error Handling**:
- If product SKU not found, webhook fails and logs error
- If order creation fails, fires `wc_stripe_agentic_order_failed` action
- All errors logged to WooCommerce logs

## Manual Capture Workflow

Agentic Checkout supports Stripe's manual capture mode for orders that require review before capturing payment.

### When to Use Manual Capture

- High-value orders requiring verification
- Orders with custom/personalized products
- Orders flagged by fraud prevention systems
- Stores with manual review processes

### How Manual Capture Works

1. **Order Placed**: AI agent completes checkout with manual capture enabled
2. **Authorization**: Stripe authorizes the payment (holds funds)
3. **Order Created**: Your store receives webhook and creates order
4. **Order Status**: Set to `on-hold` with note "Payment authorized, awaiting capture"
5. **Review Order**: Merchant reviews order in WooCommerce admin
6. **Capture Payment**: Merchant captures payment via:
   - WooCommerce order actions dropdown: "Capture charge"
   - Stripe Dashboard: Capture the Payment Intent
7. **Order Completed**: Status updated to `processing` or `completed`

### Identifying Manual Capture Orders

Manual capture orders have:
- Order status: `on-hold`
- Order meta: `_stripe_manual_capture` = `yes`
- Order meta: `_stripe_intent_id` = Payment Intent ID
- Order note: "Payment authorized, awaiting capture"

### Capturing Payment

**Via WooCommerce Admin**:
1. Go to **WooCommerce → Orders**
2. Open the order
3. Under **Order Actions** dropdown, select "Capture charge"
4. Click **Update**

**Via Stripe Dashboard**:
1. Go to **Payments → Payment Intents**
2. Find the Payment Intent (use ID from order meta)
3. Click **Capture**

### Capture Expiration

Uncaptured charges expire after 7 days. Ensure you capture or cancel orders within this timeframe.

## Developer Hooks

### Filters

#### Enable/Disable Feature

```php
/**
 * Enable or disable Agentic Checkout functionality.
 *
 * @param bool $enabled Whether agentic checkout is enabled. Default false.
 * @return bool
 */
add_filter( 'wc_stripe_agentic_checkout_enabled', function( $enabled ) {
    // Only enable for specific store IDs, user roles, etc.
    return true;
} );
```

#### Approval Decision

```php
/**
 * Override approval decision for agentic checkout orders.
 *
 * @param bool  $approved              Whether to approve the order. Default true.
 * @param array $checkout_session_data Full checkout session data from Stripe.
 * @param array $line_items            Line items being purchased.
 * @param array $payment_method_details Payment method details.
 * @return bool
 */
add_filter( 'wc_stripe_agentic_approval_decision', function( $approved, $checkout_session, $line_items, $payment_method ) {
    // Example: Decline orders over $500
    $amount_total = $checkout_session['amount_total'] ?? 0;
    if ( $amount_total > 50000 ) { // Amount in cents
        return false;
    }

    // Example: Decline orders with specific payment methods
    $card_type = $payment_method['card']['brand'] ?? '';
    if ( 'amex' === $card_type ) {
        return false;
    }

    return $approved;
}, 10, 4 );
```

#### Decline Reason

```php
/**
 * Customize decline reason for agentic checkout orders.
 *
 * @param string $reason               Default decline reason.
 * @param array  $checkout_session_data Full checkout session data.
 * @return string
 */
add_filter( 'wc_stripe_agentic_decline_reason', function( $reason, $checkout_session ) {
    // Provide custom decline reasons based on your logic
    if ( $reason === 'validation_failed' ) {
        return 'order_review_required';
    }
    return $reason;
}, 10, 2 );
```

#### Tax Calculation

```php
/**
 * Override or modify tax amounts for agentic checkout.
 *
 * @param array $tax_amounts Calculated tax amounts.
 * @param array $line_items  Line items being purchased.
 * @param array $addresses   Fulfillment and billing addresses.
 * @return array
 */
add_filter( 'wc_stripe_agentic_tax_calculation', function( $tax_amounts, $line_items, $addresses ) {
    // Example: Apply custom tax logic
    $fulfillment_address = $addresses['fulfillment'] ?? [];

    // Add custom tax for specific states
    if ( 'NY' === ( $fulfillment_address['state'] ?? '' ) ) {
        $tax_amounts['total_details']['amount_tax'] += 100; // Add $1.00 tax
    }

    return $tax_amounts;
}, 10, 3 );
```

#### Shipping Options

```php
/**
 * Modify shipping options for agentic checkout.
 *
 * @param array $shipping_options Calculated shipping options.
 * @param array $cart_items       Cart items (line items).
 * @param array $destination      Destination address.
 * @return array
 */
add_filter( 'wc_stripe_agentic_shipping_options', function( $shipping_options, $cart_items, $destination ) {
    // Example: Add expedited shipping for specific locations
    if ( 'CA' === ( $destination['state'] ?? '' ) ) {
        $shipping_options[] = [
            'display_name'      => 'Same-Day Delivery',
            'description'       => 'Delivered today',
            'shipping_amount'   => 3000, // $30.00
            'earliest_delivery_time' => strtotime( '+4 hours' ),
            'latest_delivery_time'   => strtotime( '+8 hours' ),
        ];
    }

    // Example: Filter out expensive shipping
    $shipping_options = array_filter( $shipping_options, function( $option ) {
        return $option['shipping_amount'] < 5000; // Max $50 shipping
    } );

    return $shipping_options;
}, 10, 3 );
```

#### Product Lookup

```php
/**
 * Customize product lookup by SKU for agentic checkout.
 *
 * @param WC_Product|null $product Product object or null if not found.
 * @param string          $sku     Product SKU.
 * @return WC_Product|null
 */
add_filter( 'wc_stripe_agentic_product_by_sku', function( $product, $sku ) {
    // Example: Custom SKU mapping logic
    if ( ! $product ) {
        // Try alternative SKU formats
        $alt_sku = str_replace( '-', '_', $sku );
        $product_id = wc_get_product_id_by_sku( $alt_sku );
        $product = $product_id ? wc_get_product( $product_id ) : null;
    }

    return $product;
}, 10, 2 );
```

#### Debug Mode

```php
/**
 * Enable debug mode for detailed logging.
 *
 * @param bool $debug_mode Whether debug mode is enabled. Default false.
 * @return bool
 */
add_filter( 'wc_stripe_agentic_debug_mode', function( $debug_mode ) {
    // Enable debug logging
    return true;
} );
```

### Actions

#### Order Created

```php
/**
 * Triggered when an agentic order is successfully created.
 *
 * @param WC_Order $order            Created WooCommerce order.
 * @param object   $checkout_session Stripe checkout session object.
 */
add_action( 'wc_stripe_agentic_order_created', function( $order, $checkout_session ) {
    // Example: Send notification to Slack
    $agent_name = $checkout_session->metadata->agent ?? 'Unknown Agent';
    $message = sprintf(
        'New agentic order #%d from %s - Total: %s',
        $order->get_id(),
        $agent_name,
        $order->get_formatted_order_total()
    );
    // Send to Slack...

    // Example: Add custom order note
    $order->add_order_note(
        sprintf( 'AI Agent: %s', $agent_name ),
        false,
        true
    );
    $order->save();

    // Example: Tag order in third-party system
    // update_external_crm( $order->get_id(), 'agentic-order' );
}, 10, 2 );
```

#### Order Failed

```php
/**
 * Triggered when agentic order creation fails.
 *
 * @param WP_Error $error           Error object with failure details.
 * @param object   $checkout_session Stripe checkout session object.
 */
add_action( 'wc_stripe_agentic_order_failed', function( $error, $checkout_session ) {
    // Example: Send alert email to admin
    $admin_email = get_option( 'admin_email' );
    $subject = 'Agentic Order Creation Failed';
    $message = sprintf(
        "Order creation failed for checkout session: %s\n\nError: %s",
        $checkout_session->id ?? 'unknown',
        $error->get_error_message()
    );
    wp_mail( $admin_email, $subject, $message );

    // Example: Log to external monitoring service
    // error_reporting_service()->log( $error->get_error_message() );
}, 10, 2 );
```

## Troubleshooting

### Enabling Debug Logging

To see detailed logs for all agentic checkout requests:

```php
add_filter( 'wc_stripe_agentic_debug_mode', '__return_true' );
```

View logs in **WooCommerce → Status → Logs** and select the `stripe` log file.

### Common Issues

#### Endpoints Not Accessible

**Symptom**: Stripe reports endpoints are unreachable

**Solutions**:
1. Verify filter is enabled:
   ```php
   add_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );
   ```
2. Check webhook secret is configured in Stripe settings
3. Ensure permalink structure is set (not "Plain")
4. Verify REST API is working: Visit `/wp-json/wc/v3/stripe/agentic/approve` (should return 403, not 404)
5. Check server firewall isn't blocking Stripe IPs

#### Orders Not Being Created

**Symptom**: Webhook received but no order appears in WooCommerce

**Solutions**:
1. Check WooCommerce logs: **Status → Logs → stripe**
2. Verify products have SKUs matching Stripe Price `lookup_key`
3. Enable debug mode to see detailed request/response data
4. Check webhook is properly configured in Stripe Dashboard
5. Verify webhook secret matches between Stripe and WooCommerce settings

#### Signature Verification Failures

**Symptom**: Logs show "invalid_signature" errors

**Solutions**:
1. Verify webhook secret is correctly entered in **WooCommerce → Settings → Payments → Stripe**
2. Ensure you're using the correct secret (test vs live mode)
3. Check server time is accurate (signature includes timestamp)
4. Verify webhook URL in Stripe Dashboard matches your site URL
5. Don't modify webhook payload (signature verifies exact payload)

#### Tax Calculation Errors

**Symptom**: Tax endpoint returns errors or incorrect amounts

**Solutions**:
1. Verify WooCommerce tax settings are configured: **Settings → Tax**
2. Check tax rates are set up for customer's location
3. Ensure products have correct tax class assigned
4. Test tax calculation with regular WooCommerce checkout
5. Review logs for specific error messages
6. If using tax plugin (TaxJar, Avalara), verify plugin is active and configured

#### Shipping Calculation Errors

**Symptom**: Shipping endpoint returns empty array or errors

**Solutions**:
1. Verify WooCommerce shipping zones are configured: **Settings → Shipping**
2. Check shipping zone covers customer's location
3. Ensure shipping methods are enabled
4. Verify products require shipping (not virtual/downloadable)
5. Test shipping calculation in regular WooCommerce checkout
6. Check for conflicting shipping plugins

#### Products Not Found by SKU

**Symptom**: Approval or order creation fails with "Product not found" error

**Solutions**:
1. Verify every product has a SKU assigned
2. Check SKU matches exactly with Stripe Price `lookup_key`
3. SKUs are case-sensitive - ensure exact match
4. In Stripe Dashboard, verify Price has `lookup_key` set
5. Use `wc_stripe_agentic_product_by_sku` filter for custom SKU mapping

#### Manual Capture Not Working

**Symptom**: Can't capture payment or "Capture charge" action missing

**Solutions**:
1. Verify order has `_stripe_intent_id` meta set
2. Check order status is `on-hold`
3. Ensure capture is attempted within 7 days of authorization
4. Try capturing via Stripe Dashboard instead
5. Check Stripe logs for capture errors

### Debug Information to Collect

When reporting issues, include:

1. **Plugin Versions**:
   - WordPress version
   - WooCommerce version
   - WooCommerce Stripe Gateway version

2. **Logs**:
   - WooCommerce logs (WooCommerce → Status → Logs → stripe)
   - Server error logs
   - Stripe Dashboard webhook logs

3. **Configuration**:
   - Is `wc_stripe_agentic_checkout_enabled` filter active?
   - Is webhook secret configured?
   - Test mode or live mode?

4. **Request Details**:
   - Checkout Session ID
   - Timestamp of request
   - Customer location (for tax/shipping issues)
   - Product SKUs involved

## Security

### Authentication & Authorization

All agentic checkout endpoints use Stripe webhook signature verification:

- **HMAC SHA256**: Requests signed using webhook secret
- **Timestamp Validation**: Requests must arrive within 5 minutes
- **Replay Protection**: Timestamps prevent replay attacks
- **Secret Rotation**: Rotate webhook secrets regularly in Stripe Dashboard

### Best Practices

1. **Webhook Secret Security**:
   - Store webhook secret securely in WooCommerce settings
   - Never commit webhook secrets to version control
   - Rotate secrets periodically
   - Use different secrets for test and live modes

2. **Input Validation**:
   - All product lookups validate SKU format
   - Quantities and amounts validated before processing
   - Customer addresses sanitized before storage

3. **Error Handling**:
   - Failed validations return appropriate error codes
   - Sensitive information never included in error messages
   - All errors logged for audit trail

4. **Rate Limiting**:
   - Consider implementing rate limiting for high-traffic stores
   - Monitor for unusual request patterns
   - Use Stripe Dashboard to monitor webhook deliveries

5. **Testing**:
   - Always test in Stripe test mode first
   - Use test webhook secrets for development
   - Never use production data in test mode

### Compliance

- **PCI Compliance**: Payment data never stored on your server
- **GDPR**: Customer data processed according to WooCommerce privacy settings
- **Data Retention**: Follow WooCommerce data retention policies

## Additional Resources

- [Stripe Agentic Checkout Documentation](https://stripe.com/docs/agentic-checkout)
- [WooCommerce REST API Documentation](https://woocommerce.github.io/woocommerce-rest-api-docs/)
- [Stripe Webhook Documentation](https://stripe.com/docs/webhooks)
- [WooCommerce Stripe Gateway Support](https://woocommerce.com/document/stripe/)

## Support

For issues or questions:

1. Check [Troubleshooting](#troubleshooting) section above
2. Review [WooCommerce logs](#enabling-debug-logging)
3. Check [Stripe Dashboard webhook logs](https://dashboard.stripe.com/webhooks)
4. Contact WooCommerce Support for feature assistance
5. File bug reports on [GitHub repository](https://github.com/woocommerce/woocommerce-gateway-stripe)

---

**Version**: 8.9.0
**Last Updated**: January 2025
