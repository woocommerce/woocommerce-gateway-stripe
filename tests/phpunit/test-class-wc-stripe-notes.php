<?php
/**
 * Class WC_Stripe_Inbox_Notes_Test
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Inbox_Notes
 */

/**
 * Class WC_Stripe_Inbox_Notes_Note tests.
 */
class WC_Stripe_Inbox_Notes_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	public $stripe_connect_mock;
	public $stripe_connect_original;

	public function set_up() {
		parent::set_up();

		// overriding the `WC_Stripe_Connect` in woocommerce_gateway_stripe(),
		// because the method we're calling is static and we don't really have a way of injecting it all the way down to this class.
		$this->stripe_connect_mock = $this->createPartialMock( WC_Stripe_Connect::class, [ 'is_connected' ] );
		$this->stripe_connect_mock->expects( $this->any() )->method( 'is_connected' )->willReturn( true );
		$this->stripe_connect_original        = woocommerce_gateway_stripe()->connect;
		woocommerce_gateway_stripe()->connect = $this->stripe_connect_mock;

		$this->stripe_api = $this->createMock( WC_Stripe_API::class );
		WC_Stripe_API::set_instance( $this->stripe_api );

		if ( version_compare( WC_VERSION, '4.4.0', '<' ) ) {
			$this->markTestSkipped( 'The used WC components are not backward compatible' );
			return;
		}

		update_option( '_wcstripe_feature_upe', 'yes' );
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'enabled'                         => 'yes',
				'upe_checkout_experience_enabled' => 'no',
			]
		);
	}

	public function tear_down() {
		woocommerce_gateway_stripe()->connect = $this->stripe_connect_original;
		delete_option( '_wcstripe_feature_upe' );
		WC_Stripe_Helper::delete_main_stripe_settings();

		parent::tear_down();
	}

	public function test_create_upe_availability_note() {
		WC_Stripe_Helper::update_main_stripe_settings( [ 'enabled' => 'yes' ] );
		WC_Stripe_Inbox_Notes::create_upe_notes();
		$admin_note_store = WC_Data_Store::load( 'admin-note' );
		$this->assertSame( 1, count( $admin_note_store->get_notes_with_name( WC_Stripe_UPE_Availability_Note::NOTE_NAME ) ) );
	}

	public function test_create_upe_stripelink_note() {
		$upe_helper = new UPE_Test_Helper();
		$upe_helper->enable_upe();
		$upe_helper->reload_payment_gateways();

		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'enabled'                         => 'yes',
				'upe_checkout_experience_enabled' => 'yes',
			]
		);
		$this->mock_payment_method_configurations( [ WC_Stripe_Payment_Methods::LINK, WC_Stripe_Payment_Methods::CARD ] );
		$this->set_stripe_account_data( [ 'country' => 'US' ] );
		WC_Stripe_Inbox_Notes::create_upe_notes();
		$admin_note_store = WC_Data_Store::load( 'admin-note' );
		$this->assertSame( 1, count( $admin_note_store->get_notes_with_name( WC_Stripe_UPE_StripeLink_Note::NOTE_NAME ) ) );
	}

	public function test_create_upe_notes_does_not_create_note_when_upe_preview_is_disabled() {
		update_option( '_wcstripe_feature_upe', 'no' );

		WC_Stripe_Inbox_Notes::create_upe_notes();

		$admin_note_store = WC_Data_Store::load( 'admin-note' );
		$this->assertSame( 0, count( $admin_note_store->get_notes_with_name( WC_Stripe_UPE_Availability_Note::NOTE_NAME ) ) );
	}

	public function test_create_upe_notes_does_not_create_availability_note_when_upe_is_enbled() {
		$upe_helper = new UPE_Test_Helper();
		$upe_helper->enable_upe();
		$upe_helper->reload_payment_gateways();

		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'enabled'                         => 'yes',
				'upe_checkout_experience_enabled' => 'yes',
			]
		);

		$this->set_stripe_account_data( [ 'country' => 'US' ] );
		$this->mock_payment_method_configurations( [ WC_Stripe_Payment_Methods::LINK, WC_Stripe_Payment_Methods::CARD ] );
		WC_Stripe_Inbox_Notes::create_upe_notes();

		$admin_note_store = WC_Data_Store::load( 'admin-note' );
		$this->assertSame( 0, count( $admin_note_store->get_notes_with_name( WC_Stripe_UPE_Availability_Note::NOTE_NAME ) ) );
		$this->assertSame( 1, count( $admin_note_store->get_notes_with_name( WC_Stripe_UPE_StripeLink_Note::NOTE_NAME ) ) );
	}

	public function test_create_upe_notes_does_not_create_note_when_stripe_is_disabled() {
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'enabled'                         => 'no',
				'upe_checkout_experience_enabled' => 'no',
			]
		);

		WC_Stripe_Inbox_Notes::create_upe_notes();

		$admin_note_store = WC_Data_Store::load( 'admin-note' );
		$this->assertSame( 0, count( $admin_note_store->get_notes_with_name( WC_Stripe_UPE_Availability_Note::NOTE_NAME ) ) );
		$this->assertSame( 0, count( $admin_note_store->get_notes_with_name( WC_Stripe_UPE_StripeLink_Note::NOTE_NAME ) ) );
	}

	public function test_create_upe_notes_does_not_create_note_when_upe_has_been_manually_disabled() {
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'enabled'                         => 'yes',
				'upe_checkout_experience_enabled' => 'disabled',
			]
		);

		WC_Stripe_Inbox_Notes::create_upe_notes();

		$admin_note_store = WC_Data_Store::load( 'admin-note' );
		$this->assertSame( 0, count( $admin_note_store->get_notes_with_name( WC_Stripe_UPE_Availability_Note::NOTE_NAME ) ) );
		$this->assertSame( 0, count( $admin_note_store->get_notes_with_name( WC_Stripe_UPE_StripeLink_Note::NOTE_NAME ) ) );
	}

	public function test_create_stripelink_note_unavailable_if_cc_not_enabled() {
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'enabled'                         => 'yes',
				'upe_checkout_experience_enabled' => 'yes',
			]
		);

		$this->mock_payment_method_configurations( [ 'test' ] );
		WC_Stripe_Inbox_Notes::create_upe_notes();

		$admin_note_store = WC_Data_Store::load( 'admin-note' );
		$this->assertSame( 0, count( $admin_note_store->get_notes_with_name( WC_Stripe_UPE_StripeLink_Note::NOTE_NAME ) ) );
	}

	public function test_create_stripelink_note_unavailable_link_enabled() {
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'enabled'                         => 'yes',
				'upe_checkout_experience_enabled' => 'yes',
				'testmode'                        => 'yes',
			]
		);

		$this->mock_payment_method_configurations( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::LINK ] );

		WC_Stripe_Inbox_Notes::create_upe_notes();

		$admin_note_store = WC_Data_Store::load( 'admin-note' );
		$this->assertSame( 0, count( $admin_note_store->get_notes_with_name( WC_Stripe_UPE_StripeLink_Note::NOTE_NAME ) ) );
	}
}
