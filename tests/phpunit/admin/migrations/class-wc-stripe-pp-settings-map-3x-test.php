<?php
/**
 * Class WC_Stripe_PP_Settings_Map_3X_Test
 *
 * @package WooCommerce_Stripe/Tests
 */

require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/pp-settings-maps/class-wc-stripe-pp-settings-map-3x.php';

/**
 * Unit tests for {@see WC_Stripe_PP_Settings_Map_3X}.
 *
 * Verifies:
 *   - Row structure (every row has the required keys).
 *   - Destination keys match canonical Woo Stripe names from stripe-settings.php.
 *   - Transformers produce expected output for the full domain of valid inputs.
 *   - DROP/INVESTIGATE/BUILD lists contain the expected PP keys for audit visibility.
 */
class WC_Stripe_PP_Settings_Map_3X_Test extends WP_UnitTestCase {

	private WC_Stripe_PP_Settings_Map_3X $map;

	public function set_up() {
		parent::set_up();
		$this->map = new WC_Stripe_PP_Settings_Map_3X();
	}

	public function test_auto_rows_have_required_fields() {
		foreach ( $this->map->get_auto_rows() as $row ) {
			$this->assertArrayHasKey( 'source_option', $row );
			$this->assertArrayHasKey( 'source_key', $row );
			$this->assertArrayHasKey( 'dest_option', $row );
			$this->assertArrayHasKey( 'dest_key', $row );
			$this->assertIsString( $row['source_option'] );
			$this->assertIsString( $row['source_key'] );
			$this->assertIsString( $row['dest_option'] );
			$this->assertIsString( $row['dest_key'] );
		}
	}

	public function test_transform_rows_have_callable_transformers() {
		foreach ( $this->map->get_transform_rows() as $row ) {
			$this->assertArrayHasKey( 'transformer', $row );
			$this->assertIsCallable( $row['transformer'] );
		}
	}

	/**
	 * All AUTO and TRANSFORM rows write into Woo Stripe's main settings blob. Asserts the
	 * destination is `woocommerce_stripe_settings` consistently — the canonical destination.
	 */
	public function test_all_rows_target_main_woo_stripe_settings_option() {
		foreach ( $this->map->get_auto_rows() as $row ) {
			$this->assertSame( 'woocommerce_stripe_settings', $row['dest_option'] );
		}
		foreach ( $this->map->get_transform_rows() as $row ) {
			$this->assertSame( 'woocommerce_stripe_settings', $row['dest_option'] );
		}
	}

	public function test_destination_keys_match_canonical_woo_stripe_names() {
		$dest_keys = [];
		foreach ( $this->map->get_auto_rows() as $row ) {
			$dest_keys[] = $row['dest_key'];
		}
		foreach ( $this->map->get_transform_rows() as $row ) {
			$dest_keys[] = $row['dest_key'];
		}

		// Sample of canonical keys (full set defined in stripe-settings.php).
		$canonical_keys = [
			'enabled',
			'title',
			'description',
			'testmode',
			'capture',
			'saved_cards',
			'logging',
			'inline_cc_form',
			'statement_descriptor',
			'short_statement_descriptor',
			'optimized_checkout_element',
			'optimized_checkout_layout',
			'express_checkout_button_height',
			'amazon_pay_button_size',
		];

		// Every destination key the map writes to must be one of Woo Stripe's known keys.
		$unknown = array_diff( array_unique( $dest_keys ), $canonical_keys );
		$this->assertEmpty( $unknown, 'Destination keys not in the canonical list: ' . implode( ', ', $unknown ) );
	}

	public function test_map_does_not_target_legacy_payment_request_button_prefix() {
		// Sanity check — make sure we never accidentally write to a deprecated key. Woo Stripe
		// migrated `payment_request_button_*` → `express_checkout_button_*` in an internal
		// migration; we must not regress that by writing to the deprecated form.
		foreach (
			array_merge( $this->map->get_auto_rows(), $this->map->get_transform_rows() )
			as $row
		) {
			$this->assertStringStartsNotWith(
				'payment_request_button_',
				$row['dest_key'],
				sprintf( 'Row writes to deprecated dest_key: %s', $row['dest_key'] )
			);
		}
	}

	/**
	 * @dataProvider mode_transformer_provider
	 */
	public function test_mode_to_testmode_transformer( $input, string $expected ) {
		$this->assertSame( $expected, WC_Stripe_PP_Settings_Map_3X::transform_mode_to_testmode( $input ) );
	}

	public function mode_transformer_provider(): array {
		return [
			'test → yes' => [ 'test', 'yes' ],
			'live → no'  => [ 'live', 'no' ],
			'empty → no' => [ '', 'no' ],
			'null → no'  => [ null, 'no' ],
			'other → no' => [ 'sandbox', 'no' ],
		];
	}

	/**
	 * @dataProvider charge_type_transformer_provider
	 */
	public function test_charge_type_to_capture_transformer( $input, string $expected ) {
		$this->assertSame( $expected, WC_Stripe_PP_Settings_Map_3X::transform_charge_type_to_capture( $input ) );
	}

	public function charge_type_transformer_provider(): array {
		return [
			'capture → yes'  => [ 'capture', 'yes' ],
			'authorize → no' => [ 'authorize', 'no' ],
			'empty → no'     => [ '', 'no' ],
			'null → no'      => [ null, 'no' ],
			'unknown → no'   => [ 'manual', 'no' ],
		];
	}

	/**
	 * @dataProvider form_type_transformer_provider
	 */
	public function test_form_type_to_inline_cc_form_transformer( $input, string $expected ) {
		$this->assertSame( $expected, WC_Stripe_PP_Settings_Map_3X::transform_form_type_to_inline_cc_form( $input ) );
	}

	public function form_type_transformer_provider(): array {
		return [
			// payment_element is the modern Stripe layout in PP and maps to Woo Stripe's
			// non-inline (i.e., Payment Element) form.
			'payment_element → no'       => [ 'payment_element', 'no' ],
			// Card Element and custom forms are the legacy inline-style forms. Woo Stripe doesn't
			// have a true "custom" equivalent; we fall through to inline.
			'card_element → yes'         => [ 'card_element', 'yes' ],
			'custom → yes (lossy)'       => [ 'custom', 'yes' ],
			'empty → yes (safe default)' => [ '', 'yes' ],
			'unknown → yes'              => [ 'something_new', 'yes' ],
		];
	}

	/**
	 * @dataProvider statement_descriptor_transformer_provider
	 */
	public function test_statement_descriptor_transformer( $input, string $expected ) {
		$this->assertSame(
			$expected,
			WC_Stripe_PP_Settings_Map_3X::transform_statement_descriptor( $input )
		);
	}

	public function statement_descriptor_transformer_provider(): array {
		return [
			'plain text under limit'                => [ 'ACME STORE', 'ACME STORE' ],
			'plain text exactly at 22-char limit'   => [ 'AAAAAAAAAABBBBBBBBBBCC', 'AAAAAAAAAABBBBBBBBBBCC' ],
			'plain text over 22-char limit truncates'
				=> [ 'AAAAAAAAAABBBBBBBBBBCCDDD', 'AAAAAAAAAABBBBBBBBBBCC' ],
			'order_id placeholder stripped'         => [ 'ACME {order_id}', 'ACME' ],
			'multiple placeholders stripped'        => [ '{order_number} ACME {customer_id}', 'ACME' ],
			'all six PP placeholders stripped'      => [
				'{order_id}{order_number}{email}{currency}{customer_id}{name}',
				'',
			],
			'mixed text and placeholders'           => [ 'ACME #{order_number} STORE', 'ACME # STORE' ],
			'non-string input returns empty string' => [ 12345, '' ],
			'null input returns empty string'       => [ null, '' ],
			'array input returns empty string'      => [ [ 'a' => 'b' ], '' ],
		];
	}

	public function test_get_dropped_rows_includes_expected_pp_keys() {
		$dropped = $this->map->get_dropped_rows();

		// Spot-check a few keys representative of each section.
		$this->assertContains( 'force_3d_secure', $dropped, 'CC gateway DROP missing' );
		$this->assertContains( 'dispute_created', $dropped, 'Advanced settings DROP missing' );
		$this->assertContains( 'merchant_id', $dropped, 'ECE DROP missing' );
		$this->assertContains( 'allowed_countries', $dropped, 'Per-method country restriction DROP missing' );
		$this->assertContains( 'fee', $dropped, 'ACH fee pass-through (BREAKING) DROP missing' );
	}

	public function test_get_investigate_rows_includes_expected_pp_keys() {
		$investigate = $this->map->get_investigate_rows();

		$this->assertContains( 'installments', $investigate );
		$this->assertContains( 'extended_authorization', $investigate );
		$this->assertContains( 'customer_creation', $investigate, 'GDPR customer_creation INVESTIGATE missing' );
		$this->assertContains( 'guest_customer', $investigate, 'GDPR guest_customer INVESTIGATE missing' );
	}

	public function test_get_build_rows_includes_expected_pp_keys() {
		$build = $this->map->get_build_rows();

		$this->assertContains( 'button_radius', $build );
		$this->assertContains( 'radio_input', $build );
	}

	public function test_drop_investigate_build_rows_are_disjoint_from_auto_transform() {
		$row_writers = array_merge(
			array_column( $this->map->get_auto_rows(), 'source_key' ),
			array_column( $this->map->get_transform_rows(), 'source_key' )
		);

		$intersect_dropped = array_intersect( $this->map->get_dropped_rows(), $row_writers );
		$this->assertEmpty( $intersect_dropped, 'A DROP key also appears as an AUTO/TRANSFORM row: ' . implode( ',', $intersect_dropped ) );

		$intersect_investigate = array_intersect( $this->map->get_investigate_rows(), $row_writers );
		$this->assertEmpty( $intersect_investigate, 'An INVESTIGATE key also appears as an AUTO/TRANSFORM row: ' . implode( ',', $intersect_investigate ) );

		$intersect_build = array_intersect( $this->map->get_build_rows(), $row_writers );
		$this->assertEmpty( $intersect_build, 'A BUILD key also appears as an AUTO/TRANSFORM row: ' . implode( ',', $intersect_build ) );
	}

	public function test_upm_to_ocs_row_targets_optimized_checkout_element() {
		$upm_row = array_filter(
			$this->map->get_auto_rows(),
			static function ( $row ) {
				return 'woocommerce_stripe_upm_settings' === $row['source_option']
					&& 'enabled' === $row['source_key'];
			}
		);

		$this->assertCount( 1, $upm_row, 'Exactly one UPM-enabled → OCS-enabled mapping row expected' );
		$row = reset( $upm_row );
		$this->assertSame( 'optimized_checkout_element', $row['dest_key'] );
	}
}
