<?php
/**
 * PP 3.X → Woo Stripe settings mapping table.
 *
 * Derived from `woo-stripe-vs-payment-plugins-settings-map.md`, verified
 * against PP v3.3.106 and Woo Stripe canonical key names in
 * `includes/admin/stripe-settings.php`.
 *
 * Strategy classification (AUTO, TRANSFORM, DROP, INVESTIGATE, BUILD) follows
 * the settings-map document. Only AUTO and TRANSFORM rows have code paths;
 * DROP/INVESTIGATE/BUILD rows are returned for ledger/audit visibility.
 *
 * @package WooCommerce_Stripe/Migrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Concrete map for Payment Plugins for Stripe v3.X.
 *
 * @since 10.8.0
 */
class WC_Stripe_PP_Settings_Map_3X extends WC_Stripe_PP_Settings_Map {

	/**
	 * Stripe statement-descriptor maximum length (chars).
	 *
	 * @var int
	 */
	const STATEMENT_DESCRIPTOR_MAX_LENGTH = 22;

	public function get_auto_rows(): array {
		return [
			// Debug logging — PP default yes, Woo Stripe default no. Direct copy.
			[
				'source_option' => 'woocommerce_stripe_advanced_settings',
				'source_key'    => 'debug_log',
				'dest_option'   => 'woocommerce_stripe_settings',
				'dest_key'      => 'logging',
			],
			// Gateway enable.
			[
				'source_option' => 'woocommerce_stripe_cc_settings',
				'source_key'    => 'enabled',
				'dest_option'   => 'woocommerce_stripe_settings',
				'dest_key'      => 'enabled',
			],
			// Gateway title / description.
			[
				'source_option' => 'woocommerce_stripe_cc_settings',
				'source_key'    => 'title_text',
				'dest_option'   => 'woocommerce_stripe_settings',
				'dest_key'      => 'title',
			],
			[
				'source_option' => 'woocommerce_stripe_cc_settings',
				'source_key'    => 'description',
				'dest_option'   => 'woocommerce_stripe_settings',
				'dest_key'      => 'description',
			],
			// Saved payment methods toggle.
			[
				'source_option' => 'woocommerce_stripe_cc_settings',
				'source_key'    => 'save_card_enabled',
				'dest_option'   => 'woocommerce_stripe_settings',
				'dest_key'      => 'saved_cards',
			],
			// Optimized Checkout layout: PP `layout_type` (UPM) → Woo Stripe `optimized_checkout_layout`.
			// Both plugins support 'tabs' and 'accordion'. Direct copy.
			[
				'source_option' => 'woocommerce_stripe_upm_settings',
				'source_key'    => 'layout_type',
				'dest_option'   => 'woocommerce_stripe_settings',
				'dest_key'      => 'optimized_checkout_layout',
			],
			// UPM ↔ OCS signal. PP's Universal Payment Method gateway and Woo Stripe's
			// Optimized Checkout Suite are the same product surface — Stripe-managed
			// multi-method checkout with PMC ordering. If UPM was enabled in PP, enable OCS.
			[
				'source_option' => 'woocommerce_stripe_upm_settings',
				'source_key'    => 'enabled',
				'dest_option'   => 'woocommerce_stripe_settings',
				'dest_key'      => 'optimized_checkout_element',
			],
			// Express checkout button height — direct pixel value. Woo Stripe stores as string ('44' default).
			[
				'source_option' => 'woocommerce_stripe_express_checkout_settings',
				'source_key'    => 'button_height',
				'dest_option'   => 'woocommerce_stripe_settings',
				'dest_key'      => 'express_checkout_button_height',
			],
			// Amazon Pay button height.
			[
				'source_option' => 'woocommerce_stripe_amazonpay_settings',
				'source_key'    => 'button_height',
				'dest_option'   => 'woocommerce_stripe_settings',
				'dest_key'      => 'amazon_pay_button_size',
			],
		];
	}

	public function get_transform_rows(): array {
		return [
			// Test/live mode: PP 'test'/'live' → Woo Stripe 'yes'/'no' on `testmode`.
			[
				'source_option' => 'woocommerce_stripe_api_settings',
				'source_key'    => 'mode',
				'dest_option'   => 'woocommerce_stripe_settings',
				'dest_key'      => 'testmode',
				'transformer'   => [ self::class, 'transform_mode_to_testmode' ],
			],
			// Capture: PP 'capture'/'authorize' → Woo Stripe 'yes'/'no' on `capture`.
			[
				'source_option' => 'woocommerce_stripe_cc_settings',
				'source_key'    => 'charge_type',
				'dest_option'   => 'woocommerce_stripe_settings',
				'dest_key'      => 'capture',
				'transformer'   => [ self::class, 'transform_charge_type_to_capture' ],
			],
			// Card form style: PP 'payment_element'/'card_element'/'custom' →
			// Woo Stripe 'inline_cc_form' 'yes' (any non-payment-element) or 'no' (payment_element).
			// Note: Woo Stripe's inline=='no' means the modern Payment Element layout.
			[
				'source_option' => 'woocommerce_stripe_cc_settings',
				'source_key'    => 'form_type',
				'dest_option'   => 'woocommerce_stripe_settings',
				'dest_key'      => 'inline_cc_form',
				'transformer'   => [ self::class, 'transform_form_type_to_inline_cc_form' ],
			],
			// Statement descriptors: strip PP's dynamic variables (`{order_id}` etc.), sanitize,
			// truncate to Stripe's 22-char limit.
			[
				'source_option' => 'woocommerce_stripe_advanced_settings',
				'source_key'    => 'statement_descriptor',
				'dest_option'   => 'woocommerce_stripe_settings',
				'dest_key'      => 'statement_descriptor',
				'transformer'   => [ self::class, 'transform_statement_descriptor' ],
			],
			[
				'source_option' => 'woocommerce_stripe_advanced_settings',
				'source_key'    => 'statement_descriptor_suffix',
				'dest_option'   => 'woocommerce_stripe_settings',
				'dest_key'      => 'short_statement_descriptor',
				'transformer'   => [ self::class, 'transform_statement_descriptor' ],
			],
		];
	}

	public function get_dropped_rows(): array {
		return [
			// CC gateway — no Woo Stripe equivalent or auto-handled by UPE.
			'link_enabled',
			'method_format',
			'force_3d_secure',
			'generic_error',
			'notice_location',
			'notice_selector',
			// Checkout form / UX — auto-handled by Appearance API or Payment Element defaults.
			'theme',
			'custom_form',
			'cards',
			'postal_enabled',
			// Advanced settings.
			'locale',
			'stripe_fee_currency',
			'capture_status',
			'refund_cancel',
			'terms_enabled',
			'dispute_created',
			'dispute_created_status',
			'dispute_closed',
			'review_created',
			'review_closed',
			'email_enabled',
			// ACH — BREAKING for US ACH merchants (fee pass-through).
			'fee',
			'business_name',
			'stripe_mandate',
			// Express checkout — auto-handled by ECE / Stripe.
			'merchant_id',
			'merchant_name',
			'notice_enabled',
			'all_browsers',
			'order_status',
			'icon',
			'branded_type',
			// UPM — managed via Stripe PMC, not configurable in WC admin.
			'live_payment_method_configuration',
			'test_payment_method_configuration',
			'live_payment_methods',
			'test_payment_methods',
			'spaced_items',
			// Per-method gateway base settings — country restrictions FRICTION.
			'order_button_text',
			'allowed_countries',
			'except_countries',
			'specific_countries',
		];
	}

	public function get_investigate_rows(): array {
		return [
			'installments',           // STRIPE-1072 — card installments for MX/BR markets.
			'extended_authorization', // 30-day auth hold — pending research.
			'customer_creation',      // GDPR data-minimization control.
			'guest_customer',         // GDPR data-minimization control.
		];
	}

	public function get_build_rows(): array {
		return [
			'button_radius',  // ECE per-button corner-radius — not currently supported in Woo Stripe.
			'radio_input',    // Radio input checkout style — not currently supported in Woo Stripe.
		];
	}

	/**
	 * PP `mode` ("test"/"live") → Woo Stripe `testmode` ("yes"/"no").
	 *
	 * @param mixed $value PP source value.
	 * @return string
	 */
	public static function transform_mode_to_testmode( $value ): string {
		return 'test' === $value ? 'yes' : 'no';
	}

	/**
	 * PP `charge_type` ("capture"/"authorize") → Woo Stripe `capture` ("yes"/"no").
	 *
	 * @param mixed $value PP source value.
	 * @return string
	 */
	public static function transform_charge_type_to_capture( $value ): string {
		return 'capture' === $value ? 'yes' : 'no';
	}

	/**
	 * PP `form_type` ("payment_element"/"card_element"/"custom") → Woo Stripe `inline_cc_form` ("yes"/"no").
	 *
	 * Note: Woo Stripe's `inline_cc_form="no"` means the modern Payment Element layout. PP's three
	 * options collapse to Woo Stripe's two: `payment_element` → 'no' (modern), anything else → 'yes' (legacy inline).
	 * PP's `custom` form options have no Woo Stripe equivalent; mapped to 'yes' (inline) as the
	 * closest behavioral analog. The migration log records the original source value for context.
	 *
	 * @param mixed $value PP source value.
	 * @return string
	 */
	public static function transform_form_type_to_inline_cc_form( $value ): string {
		return 'payment_element' === $value ? 'no' : 'yes';
	}

	/**
	 * PP statement descriptor → Woo Stripe statement descriptor.
	 *
	 * Strips PP's six lowercase dynamic-variable placeholders ({order_id}, {order_number}, {email},
	 * {currency}, {customer_id}, {name}) which Stripe would reject. Sanitizes and truncates to
	 * Stripe's 22-character limit.
	 *
	 * @param mixed $value PP source value.
	 * @return string
	 */
	public static function transform_statement_descriptor( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$stripped  = preg_replace( '/\{[a-z_]+\}/', '', $value );
		$sanitized = sanitize_text_field( (string) $stripped );
		// Collapse the runs of whitespace that placeholder removal can leave behind.
		$collapsed = trim( (string) preg_replace( '/\s+/', ' ', $sanitized ) );

		return substr( $collapsed, 0, self::STATEMENT_DESCRIPTOR_MAX_LENGTH );
	}
}
