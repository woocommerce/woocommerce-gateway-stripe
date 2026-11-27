<?php

/**
 * Unit tests for WC_Stripe_Failed_Refund_Email_Additional_Content_Update.
 */
class WC_Stripe_Failed_Refund_Email_Additional_Content_Update_Test extends WP_UnitTestCase {

	private const MIGRATION_FLAG_OPTION = 'wc_stripe_failed_refund_email_additional_content_cleared';

	private const ADMIN_OPTION = 'woocommerce_wc_stripe_failed_refund_admin_settings';

	private const CUSTOMER_OPTION = 'woocommerce_wc_stripe_failed_refund_customer_settings';

	private const LEGACY_DEFAULT = 'Hopefully they’ll be back. Read more about <a href="https://woocommerce.com/document/managing-orders/">troubleshooting failed payments</a>.';

	private const IMPROVEMENTS_DEFAULT = 'We hope they’ll be back soon! Read more about <a href="https://woocommerce.com/document/managing-orders/">troubleshooting failed payments</a>.';

	public function set_up() {
		parent::set_up();
		delete_option( self::MIGRATION_FLAG_OPTION );
		delete_option( self::ADMIN_OPTION );
		delete_option( self::CUSTOMER_OPTION );
	}

	public function tear_down() {
		delete_option( self::MIGRATION_FLAG_OPTION );
		delete_option( self::ADMIN_OPTION );
		delete_option( self::CUSTOMER_OPTION );
		parent::tear_down();
	}

	/**
	 * Saved text that is exactly a WC_Email_Failed_Order default is cleared, and the other settings are kept.
	 *
	 * @dataProvider provide_failed_order_defaults
	 *
	 * @param string $saved_content The additional content saved before the update.
	 * @return void
	 */
	public function test_clears_failed_order_default( string $saved_content ): void {
		update_option(
			self::ADMIN_OPTION,
			[
				'enabled'            => 'yes',
				'recipient'          => 'shop@example.com',
				'additional_content' => $saved_content,
			]
		);
		update_option( self::CUSTOMER_OPTION, [ 'additional_content' => $saved_content ] );

		( new WC_Stripe_Failed_Refund_Email_Additional_Content_Update() )->maybe_migrate( '11.1.0' );

		$admin_settings = get_option( self::ADMIN_OPTION );
		$this->assertSame( '', $admin_settings['additional_content'] );
		$this->assertSame( 'yes', $admin_settings['enabled'] );
		$this->assertSame( 'shop@example.com', $admin_settings['recipient'] );
		$this->assertSame( '', get_option( self::CUSTOMER_OPTION )['additional_content'] );
		$this->assertSame( 'yes', get_option( self::MIGRATION_FLAG_OPTION ) );
	}

	/**
	 * Provides the failed order defaults as they can be saved.
	 *
	 * @return array<string, array{string}>
	 */
	public function provide_failed_order_defaults(): array {
		return [
			'email improvements off'       => [ self::LEGACY_DEFAULT ],
			'email improvements on'        => [ self::IMPROVEMENTS_DEFAULT ],
			'with surrounding white space' => [ "\n" . self::IMPROVEMENTS_DEFAULT . ' ' ],
		];
	}

	/**
	 * Text the merchant wrote or changed is not touched.
	 *
	 * @return void
	 */
	public function test_keeps_custom_content(): void {
		$custom = self::IMPROVEMENTS_DEFAULT . ' Contact us at support@example.com.';
		update_option( self::CUSTOMER_OPTION, [ 'additional_content' => $custom ] );

		( new WC_Stripe_Failed_Refund_Email_Additional_Content_Update() )->maybe_migrate( '11.1.0' );

		$this->assertSame( $custom, get_option( self::CUSTOMER_OPTION )['additional_content'] );
		$this->assertSame( 'yes', get_option( self::MIGRATION_FLAG_OPTION ) );
	}

	/**
	 * A new install has no saved defaults, so nothing is changed and the update is marked as done.
	 *
	 * @return void
	 */
	public function test_new_install_only_marks_complete(): void {
		update_option( self::CUSTOMER_OPTION, [ 'additional_content' => self::LEGACY_DEFAULT ] );

		( new WC_Stripe_Failed_Refund_Email_Additional_Content_Update() )->maybe_migrate( false );

		$this->assertSame( self::LEGACY_DEFAULT, get_option( self::CUSTOMER_OPTION )['additional_content'] );
		$this->assertSame( 'yes', get_option( self::MIGRATION_FLAG_OPTION ) );
	}

	/**
	 * The update runs once, so a default the merchant saves again later is kept.
	 *
	 * @return void
	 */
	public function test_does_not_run_twice(): void {
		update_option( self::MIGRATION_FLAG_OPTION, 'yes' );
		update_option( self::CUSTOMER_OPTION, [ 'additional_content' => self::LEGACY_DEFAULT ] );

		( new WC_Stripe_Failed_Refund_Email_Additional_Content_Update() )->maybe_migrate( '11.1.0' );

		$this->assertSame( self::LEGACY_DEFAULT, get_option( self::CUSTOMER_OPTION )['additional_content'] );
	}

	/**
	 * Missing or malformed settings do not stop the update.
	 *
	 * @return void
	 */
	public function test_ignores_missing_or_malformed_settings(): void {
		update_option( self::ADMIN_OPTION, 'not-an-array' );

		( new WC_Stripe_Failed_Refund_Email_Additional_Content_Update() )->maybe_migrate( '11.1.0' );

		$this->assertSame( 'not-an-array', get_option( self::ADMIN_OPTION ) );
		$this->assertFalse( get_option( self::CUSTOMER_OPTION ) );
		$this->assertSame( 'yes', get_option( self::MIGRATION_FLAG_OPTION ) );
	}
}
