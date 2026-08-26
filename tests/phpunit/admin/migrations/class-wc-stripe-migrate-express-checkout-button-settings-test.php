<?php

/**
 * WC_Stripe_Migrate_Express_Checkout_Button_Settings unit tests.
 */
class WC_Stripe_Migrate_Express_Checkout_Button_Settings_Test extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		// Seed the settings option so update_main_stripe_settings() does not merge in
		// gateway defaults (see WC_Stripe_Migrate_Link_Button_Locations_Test).
		add_option( WC_Stripe_Helper::SETTINGS_OPTION, [] );

		// The migration may have already run during bootstrap; reset its one-shot flag.
		delete_option( WC_Stripe_Migrate_Express_Checkout_Button_Settings::MIGRATED_OPTION );
	}

	/**
	 * The three per-method location lists collapse into one location => methods map,
	 * ordered canonically, and the per-method options are removed.
	 *
	 * @return void
	 */
	public function test_collapses_per_method_locations_into_map() {
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'express_checkout_button_locations' => [ 'product', 'cart' ],
				'link_button_locations'             => [ 'product', 'checkout' ],
				'amazon_pay_button_locations'       => [ 'product' ],
				'link_button_size'                  => 'large',
				'amazon_pay_button_size'            => 'small',
				'express_checkout_button_size'      => 'default',
			]
		);

		( new WC_Stripe_Migrate_Express_Checkout_Button_Settings() )->maybe_migrate();

		$settings = WC_Stripe_Helper::get_stripe_settings();
		$map      = $settings['express_checkout_button_locations'];

		$this->assertCount( 3, $map );
		$this->assertSame( [ 'amazon_pay', 'link', 'payment_request' ], $map['product'] );
		$this->assertSame( [ 'payment_request' ], $map['cart'] );
		$this->assertSame( [ 'link' ], $map['checkout'] );

		// Per-method options are removed; the shared size is left untouched.
		$this->assertArrayNotHasKey( 'link_button_locations', $settings );
		$this->assertArrayNotHasKey( 'amazon_pay_button_locations', $settings );
		$this->assertArrayNotHasKey( 'link_button_size', $settings );
		$this->assertArrayNotHasKey( 'amazon_pay_button_size', $settings );
		$this->assertSame( 'default', $settings['express_checkout_button_size'] );
	}

	/**
	 * The migration runs only once, leaving settings untouched on a second pass.
	 *
	 * @return void
	 */
	public function test_runs_only_once() {
		update_option( WC_Stripe_Migrate_Express_Checkout_Button_Settings::MIGRATED_OPTION, 'yes' );
		WC_Stripe_Helper::update_main_stripe_settings( [ 'link_button_locations' => [ 'cart' ] ] );

		( new WC_Stripe_Migrate_Express_Checkout_Button_Settings() )->maybe_migrate();

		$settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( [ 'cart' ], $settings['link_button_locations'] );
	}

	/**
	 * A fresh install with no legacy location options is not rewritten; the flag is still set.
	 *
	 * @return void
	 */
	public function test_skips_rewrite_when_no_legacy_locations() {
		WC_Stripe_Helper::update_main_stripe_settings( [ 'express_checkout_button_size' => 'large' ] );

		( new WC_Stripe_Migrate_Express_Checkout_Button_Settings() )->maybe_migrate();

		$settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertArrayNotHasKey( 'express_checkout_button_locations', $settings );
		$this->assertSame( 'yes', get_option( WC_Stripe_Migrate_Express_Checkout_Button_Settings::MIGRATED_OPTION ) );
	}

	/**
	 * A second pass over an already-unified map (lost one-shot flag) leaves it unchanged.
	 *
	 * @return void
	 */
	public function test_second_pass_leaves_unified_map_unchanged() {
		$map = [
			'product' => [ 'amazon_pay', 'link', 'payment_request' ],
			'cart'    => [ 'payment_request' ],
		];
		WC_Stripe_Helper::update_main_stripe_settings( [ 'express_checkout_button_locations' => $map ] );

		( new WC_Stripe_Migrate_Express_Checkout_Button_Settings() )->maybe_migrate();

		$settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( $map, $settings['express_checkout_button_locations'] );
		$this->assertSame( 'yes', get_option( WC_Stripe_Migrate_Express_Checkout_Button_Settings::MIGRATED_OPTION ) );
	}

	/**
	 * Per-method leftovers next to a unified map are dropped without rebuilding the map.
	 *
	 * @return void
	 */
	public function test_drops_leftovers_without_rebuilding_unified_map() {
		$map = [ 'cart' => [ 'link' ] ];
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'express_checkout_button_locations' => $map,
				'link_button_locations'             => [ 'product' ],
				'link_button_size'                  => 'large',
			]
		);

		( new WC_Stripe_Migrate_Express_Checkout_Button_Settings() )->maybe_migrate();

		$settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( $map, $settings['express_checkout_button_locations'] );
		$this->assertArrayNotHasKey( 'link_button_locations', $settings );
		$this->assertArrayNotHasKey( 'link_button_size', $settings );
	}
}
