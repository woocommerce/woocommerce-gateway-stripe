<?php

/**
 * Class WC_Stripe_Whats_New_Note_Test
 *
 * @package WooCommerce/Stripe/WC_Stripe_Whats_New_Note
 *
 * Class WC_Stripe_Whats_New_Note tests.
 */
class WC_Stripe_Whats_New_Note_Test extends WP_UnitTestCase {

	/**
	 * Pre-test setup.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// Notes/options created per test are rolled back by WP_UnitTestCase's
		// transaction; just reset the baseline for an explicit starting state.
		delete_option( WC_Stripe_Whats_New_Note::LAST_SEEN_VERSION_OPTION );
	}

	/**
	 * Guards the note's title, content, type, source and action wiring.
	 *
	 * @return void
	 */
	public function test_get_note() {
		$note = WC_Stripe_Whats_New_Note::get_note();

		$expected_heading = sprintf( 'WooCommerce Stripe %s: see what\'s new', WC_STRIPE_VERSION );

		$this->assertSame( $expected_heading, $note->get_title() );
		// Inbox only renders info/marketing/survey/warning — not "update".
		$this->assertSame( 'info', $note->get_type() );
		$this->assertSame( 'wc-stripe-whats-new-note', $note->get_name() );
		$this->assertSame( 'woocommerce-gateway-stripe', $note->get_source() );

		list( $action ) = $note->get_actions();
		$this->assertSame( 'view-whats-new', $action->name );
		$this->assertSame( 'See what\'s new', $action->label );
		// Must stay an external URL: the Inbox only opens links outside the
		// admin URL in a new tab; an in-admin link would open in the same tab.
		$this->assertSame( 'https://wordpress.org/plugins/woocommerce-gateway-stripe/#developers', $action->query );
	}

	/**
	 * The version gate only opens when the stored baseline is older than the running version.
	 *
	 * @dataProvider provider_is_upgrade_pending
	 *
	 * @param string|false $stored   Value to seed the last-seen option with, or false to leave it unset.
	 * @param bool         $expected Whether the gate should report a pending upgrade.
	 * @return void
	 */
	public function test_is_upgrade_pending( $stored, bool $expected ) {
		if ( false === $stored ) {
			delete_option( WC_Stripe_Whats_New_Note::LAST_SEEN_VERSION_OPTION );
		} else {
			update_option( WC_Stripe_Whats_New_Note::LAST_SEEN_VERSION_OPTION, $stored );
		}

		$this->assertSame( $expected, WC_Stripe_Whats_New_Note::is_upgrade_pending() );
	}

	/**
	 * Data provider for test_is_upgrade_pending.
	 *
	 * @return array<string, array{0: string|false, 1: bool}>
	 */
	public function provider_is_upgrade_pending(): array {
		return [
			'no baseline yet (fresh install / pre-feature)' => [ false, false ],
			'empty baseline'                                => [ '', false ],
			'older baseline -> upgraded'                    => [ '0.0.1', true ],
			'same as running version'                       => [ WC_STRIPE_VERSION, false ],
			'newer than running version'                    => [ '999.0.0', false ],
		];
	}

	/**
	 * record_install_version() banks the running version on a fresh install so the note never fires.
	 *
	 * @return void
	 */
	public function test_record_install_version_fresh_install_banks_current_version() {
		WC_Stripe_Whats_New_Note::record_install_version( false );

		$this->assertSame( WC_STRIPE_VERSION, get_option( WC_Stripe_Whats_New_Note::LAST_SEEN_VERSION_OPTION ) );
		$this->assertFalse( WC_Stripe_Whats_New_Note::is_upgrade_pending() );
	}

	/**
	 * record_install_version() banks the prior version on upgrade so the gate opens.
	 *
	 * @return void
	 */
	public function test_record_install_version_upgrade_banks_previous_version() {
		WC_Stripe_Whats_New_Note::record_install_version( '0.0.1' );

		$this->assertSame( '0.0.1', get_option( WC_Stripe_Whats_New_Note::LAST_SEEN_VERSION_OPTION ) );
		$this->assertTrue( WC_Stripe_Whats_New_Note::is_upgrade_pending() );
	}

	/**
	 * record_install_version() never rolls the baseline back below a value init() already advanced.
	 *
	 * Both run on the same admin_init pass in an undefined order; an existing baseline must win.
	 *
	 * @return void
	 */
	public function test_record_install_version_does_not_overwrite_existing_baseline() {
		update_option( WC_Stripe_Whats_New_Note::LAST_SEEN_VERSION_OPTION, '10.0.0' );

		WC_Stripe_Whats_New_Note::record_install_version( '9.0.0' );

		$this->assertSame( '10.0.0', get_option( WC_Stripe_Whats_New_Note::LAST_SEEN_VERSION_OPTION ) );
	}

	/**
	 * init() creates the note for an upgrader and advances the baseline to the running version.
	 *
	 * @return void
	 */
	public function test_init_creates_note_for_upgrader_and_advances_baseline() {
		update_option( WC_Stripe_Whats_New_Note::LAST_SEEN_VERSION_OPTION, '0.0.1' );

		WC_Stripe_Whats_New_Note::init();

		$this->assertSame( 1, $this->count_notes() );
		$this->assertSame( WC_STRIPE_VERSION, get_option( WC_Stripe_Whats_New_Note::LAST_SEEN_VERSION_OPTION ) );
	}

	/**
	 * init() shows the note once per upgrade: a second pass on the same version is a no-op.
	 *
	 * @return void
	 */
	public function test_init_shows_note_once_per_upgrade() {
		update_option( WC_Stripe_Whats_New_Note::LAST_SEEN_VERSION_OPTION, '0.0.1' );

		WC_Stripe_Whats_New_Note::init();
		WC_Stripe_Whats_New_Note::init();

		$this->assertSame( 1, $this->count_notes() );
	}

	/**
	 * init() does not create the note for a fresh install (baseline already at the running version).
	 *
	 * @return void
	 */
	public function test_init_does_not_create_note_for_fresh_install() {
		update_option( WC_Stripe_Whats_New_Note::LAST_SEEN_VERSION_OPTION, WC_STRIPE_VERSION );

		WC_Stripe_Whats_New_Note::init();

		$this->assertSame( 0, $this->count_notes() );
	}

	/**
	 * init() stays silent until a baseline is recorded, so fresh installs are never shown the note.
	 *
	 * @return void
	 */
	public function test_init_does_nothing_without_a_baseline() {
		delete_option( WC_Stripe_Whats_New_Note::LAST_SEEN_VERSION_OPTION );

		WC_Stripe_Whats_New_Note::init();

		$this->assertSame( 0, $this->count_notes() );
		$this->assertFalse( get_option( WC_Stripe_Whats_New_Note::LAST_SEEN_VERSION_OPTION ) );
	}

	/**
	 * Count the notes stored under this note's name.
	 *
	 * @return int
	 */
	private function count_notes(): int {
		$data_store = WC_Data_Store::load( 'admin-note' );

		return count( $data_store->get_notes_with_name( WC_Stripe_Whats_New_Note::NOTE_NAME ) );
	}
}
