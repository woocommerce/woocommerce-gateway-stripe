<?php
/**
 * Class WC_Stripe_Subscriptions_Legacy_SEPA_Tokens_Update_Test
 */

/**
 * WC_Stripe_Subscriptions_Legacy_SEPA_Tokens_Update unit tests.
 */
class WC_Stripe_Subscriptions_Legacy_SEPA_Tokens_Update_Test extends WP_UnitTestCase {

	/**
	 * Logger mock.
	 *
	 * @var MockObject|WC_Logger
	 */
	private $logger_mock;

	/**
	 * @var WC_Stripe_Subscriptions_Legacy_SEPA_Tokens_Update
	 */
	private $updater;

	public function set_up() {
		parent::set_up();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/class-wc-stripe-subscriptions-legacy-sepa-tokens-update.php';

		$this->logger_mock = $this->getMockBuilder( 'WC_Logger' )
								   ->disableOriginalConstructor()
								   ->setMethods( [ 'add' ] )
								   ->getMock();
		$this->updater     = $this->getMockBuilder( 'WC_Stripe_Subscriptions_Legacy_SEPA_Tokens_Update' )
								   ->setConstructorArgs( [ $this->logger_mock ] )
								   ->setMethods( [ 'init', 'schedule_repair' ] )
								   ->getMock();
	}

	/**
	 * For the repair to be scheduled, WC_Subscriptions must be active and UPE must be enabled.
	 *
	 * We can't mock the check for WC_Subscriptions, so we'll only test the UPE check.
	 */
	public function test_updater_gets_initiated_on_right_conditions() {
		update_option( 'woocommerce_stripe_settings', [ 'upe_checkout_experience_enabled' => 'yes' ] );

		$this->updater
			 ->expects( $this->once() )
			 ->method( 'init' );

		$this->updater
			 ->expects( $this->once() )
			 ->method( 'schedule_repair' );

		$this->updater->maybe_update();
	}

	public function test_updater_doesn_not_get_initiated_when_legacy_is_enabled() {
		// update_option( 'woocommerce_stripe_settings', [ 'upe_checkout_experience_enabled' => 'yes' ] );

		$this->updater
			 ->expects( $this->never() )
			 ->method( 'init' );

		$this->updater
			 ->expects( $this->never() )
			 ->method( 'schedule_repair' );

		$this->updater->maybe_update();
	}
}
