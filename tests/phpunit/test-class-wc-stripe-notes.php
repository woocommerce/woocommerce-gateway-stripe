<?php
/**
 * Class WC_Stripe_Inbox_Notes_Test
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Inbox_Notes
 */

/**
 * Class WC_Stripe_Inbox_Notes_Note tests.
 */
class WC_Stripe_Inbox_Notes_Test extends WP_UnitTestCase {
	public function setUp() {
		parent::setUp();

		if ( version_compare( WC_VERSION, '4.4.0', '<' ) ) {
			$this->markTestSkipped( 'The used WC components are not backward compatible' );
			return;
		}

		update_option( '_wcstripe_feature_upe_settings', 'yes' );
		update_option( '_wcstripe_feature_upe', 'yes' );
		update_option(
			'woocommerce_stripe_settings',
			[
				'enabled'                         => 'yes',
				'upe_checkout_experience_enabled' => 'no',
			]
		);
	}

	public function tearDown() {
		parent::tearDown();

		delete_option( '_wcstripe_feature_upe_settings' );
		delete_option( '_wcstripe_feature_upe' );
		delete_option( 'woocommerce_stripe_settings' );
	}

	public function test_create_upe_availability_note() {
		WC_Stripe_Inbox_Notes::create_upe_availability_note();

		$note_id          = WC_Stripe_UPE_Availability_Note::NOTE_NAME;
		$admin_note_store = WC_Data_Store::load( 'admin-note' );
		$this->assertSame( 1, count( $admin_note_store->get_notes_with_name( $note_id ) ) );
	}

	public function test_create_upe_availability_note_does_not_create_note_when_upe_preview_is_disabled() {
		update_option( '_wcstripe_feature_upe_settings', 'no' );
		update_option( '_wcstripe_feature_upe', 'no' );

		WC_Stripe_Inbox_Notes::create_upe_availability_note();

		$note_id          = WC_Stripe_UPE_Availability_Note::NOTE_NAME;
		$admin_note_store = WC_Data_Store::load( 'admin-note' );
		$this->assertSame( 0, count( $admin_note_store->get_notes_with_name( $note_id ) ) );
	}

	public function test_create_upe_availability_note_does_not_create_note_when_upe_is_enbled() {
		update_option(
			'woocommerce_stripe_settings',
			[
				'enabled'                         => 'yes',
				'upe_checkout_experience_enabled' => 'yes',
			]
		);

		WC_Stripe_Inbox_Notes::create_upe_availability_note();

		$note_id          = WC_Stripe_UPE_Availability_Note::NOTE_NAME;
		$admin_note_store = WC_Data_Store::load( 'admin-note' );
		$this->assertSame( 0, count( $admin_note_store->get_notes_with_name( $note_id ) ) );
	}

	public function test_create_upe_availability_note_does_not_create_note_when_stripe_is_disabled() {
		update_option(
			'woocommerce_stripe_settings',
			[
				'enabled'                         => 'no',
				'upe_checkout_experience_enabled' => 'no',
			]
		);

		WC_Stripe_Inbox_Notes::create_upe_availability_note();

		$note_id          = WC_Stripe_UPE_Availability_Note::NOTE_NAME;
		$admin_note_store = WC_Data_Store::load( 'admin-note' );
		$this->assertSame( 0, count( $admin_note_store->get_notes_with_name( $note_id ) ) );
	}

	public function test_create_upe_availability_note_does_not_create_note_when_upe_has_been_manually_disabled() {
		update_option(
			'woocommerce_stripe_settings',
			[
				'enabled'                         => 'yes',
				'upe_checkout_experience_enabled' => 'disabled',
			]
		);

		WC_Stripe_Inbox_Notes::create_upe_availability_note();

		$admin_note_store = WC_Data_Store::load( 'admin-note' );
		$this->assertSame( 0, count( $admin_note_store->get_notes_with_name( WC_Stripe_UPE_Availability_Note::NOTE_NAME ) ) );
	}
}
