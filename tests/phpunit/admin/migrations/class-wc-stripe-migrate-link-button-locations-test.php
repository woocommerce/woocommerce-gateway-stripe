<?php

/**
 * WC_Stripe_Migrate_Link_Button_Locations unit tests.
 */
class WC_Stripe_Migrate_Link_Button_Locations_Test extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		// Seed the settings option so update_settings() does not hit the
		// "option does not exist yet" branch of the pre_update_option_woocommerce_stripe_settings
		// filter, which would otherwise merge in gateway defaults (including link_button_locations)
		// and break these assertions.
		add_option( WC_Stripe_Helper::SETTINGS_OPTION, [] );
	}

	public function test_copies_payment_request_locations_when_link_setting_is_unset() {
		WC_Stripe::get_instance()->update_settings(
			[ 'express_checkout_button_locations' => [ 'checkout' ] ]
		);

		$migration = new WC_Stripe_Migrate_Link_Button_Locations();
		$migration->maybe_migrate();

		$settings = WC_Stripe::get_instance()->get_settings();
		$this->assertSame( [ 'checkout' ], $settings['link_button_locations'] );
	}

	public function test_copies_empty_payment_request_locations_when_link_setting_is_unset() {
		// Empty string is how the settings UI represents "all locations cleared".
		WC_Stripe::get_instance()->update_settings(
			[ 'express_checkout_button_locations' => '' ]
		);

		$migration = new WC_Stripe_Migrate_Link_Button_Locations();
		$migration->maybe_migrate();

		$settings = WC_Stripe::get_instance()->get_settings();
		$this->assertSame( '', $settings['link_button_locations'] );
	}

	public function test_does_not_overwrite_existing_link_locations() {
		WC_Stripe::get_instance()->update_settings(
			[
				'express_checkout_button_locations' => [ 'checkout' ],
				'link_button_locations'             => [ 'cart' ],
			]
		);

		$migration = new WC_Stripe_Migrate_Link_Button_Locations();
		$migration->maybe_migrate();

		$settings = WC_Stripe::get_instance()->get_settings();
		$this->assertSame( [ 'cart' ], $settings['link_button_locations'] );
	}

	public function test_skips_when_payment_request_locations_are_unset() {
		WC_Stripe::get_instance()->update_settings( [] );

		$migration = new WC_Stripe_Migrate_Link_Button_Locations();
		$migration->maybe_migrate();

		$settings = WC_Stripe::get_instance()->get_settings();
		$this->assertArrayNotHasKey( 'link_button_locations', $settings );
	}
}
