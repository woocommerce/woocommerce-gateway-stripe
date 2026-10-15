<?php
/**
 * Tests for how WC_Stripe_Webhook_Handler uses the event reconciler.
 *
 * @package WooCommerce_Stripe/Tests
 */

require_once __DIR__ . '/helpers/class-wc-stripe-webhook-request-simulator.php';
require_once __DIR__ . '/helpers/class-wc-stripe-in-memory-event-store.php';

/**
 * WC_Stripe_Webhook_Handler_Event_Reconciliation_Test class.
 */
class WC_Stripe_Webhook_Handler_Event_Reconciliation_Test extends WP_UnitTestCase {

	private const WEBHOOK_SECRET = 'whsec_reconciliation';
	private const EVENT_ID       = 'evt_1Webhook';
	private const LOCK_NAME      = 'wc_stripe_event_lock_' . self::EVENT_ID;

	/**
	 * @var WC_Stripe_In_Memory_Event_Store
	 */
	private $store;

	public function set_up() {
		parent::set_up();

		if ( ! WC_Stripe_Webhook_Request_Simulator::can_simulate_headers() ) {
			$this->markTestSkipped( 'This SAPI defines getallheaders(), so request headers cannot be simulated via $_SERVER.' );
		}

		$settings                        = WC_Stripe_Helper::get_stripe_settings();
		$settings['testmode']            = 'yes';
		$settings['webhook_secret']      = self::WEBHOOK_SECRET;
		$settings['test_webhook_secret'] = self::WEBHOOK_SECRET;
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
		update_option( WC_Stripe_Feature_Flags::EVENT_RECONCILIATION_FEATURE_FLAG_NAME, 'yes' );

		$this->store = new WC_Stripe_In_Memory_Event_Store();
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'block_http' ] );
		parent::tear_down();
	}

	/**
	 * A delivery answers 409 while another request holds the event, skips an event already processed,
	 * and processes it otherwise, always releasing its lock.
	 *
	 * @param string|null $stored_status    Status of the stored record, or null for none.
	 * @param bool        $locked           Whether another request holds the event lock.
	 * @param int         $expected_status  Expected HTTP status.
	 * @param int         $expected_process Expected number of process_webhook() calls.
	 * @dataProvider provide_delivery_cases
	 */
	public function test_delivery_respects_the_claim( ?string $stored_status, bool $locked, int $expected_status, int $expected_process ): void {
		if ( null !== $stored_status ) {
			$this->store->seed( new WC_Stripe_Event_Record( self::EVENT_ID, $stored_status, 'invoice.paid', time(), false ) );
		}
		$other_owner = $locked ? WC_Stripe_Option_Lock::acquire( self::LOCK_NAME, 600 ) : null;

		$handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->setConstructorArgs( [ new WC_Stripe_Event_Reconciler( $this->store ) ] )
			->setMethods( [ 'process_webhook' ] )
			->getMock();
		$handler->expects( $this->exactly( $expected_process ) )->method( 'process_webhook' );

		$body   = wp_json_encode(
			[
				'id'       => self::EVENT_ID,
				'type'     => 'invoice.paid',
				'created'  => time(),
				'livemode' => false,
			]
		);
		$status = WC_Stripe_Webhook_Request_Simulator::dispatch( $handler, $body, $this->sign( $body ) );

		$this->assertSame( $expected_status, $status );
		$this->assertSame( $other_owner, $this->read_lock(), 'Only the lock of another request may remain.' );
	}

	/**
	 * @return array
	 */
	public function provide_delivery_cases(): array {
		return [
			'new event is processed'        => [ null, false, 200, 1 ],
			'pending event is processed'    => [ WC_Stripe_Event_Store_Interface::STATUS_PENDING, false, 200, 1 ],
			'processed event is skipped'    => [ WC_Stripe_Event_Store_Interface::STATUS_PROCESSED, false, 200, 0 ],
			'event locked elsewhere is 409' => [ null, true, 409, 0 ],
		];
	}

	/**
	 * With the feature disabled, a held lock does not stop processing.
	 */
	public function test_delivery_ignores_the_lock_while_disabled(): void {
		update_option( WC_Stripe_Feature_Flags::EVENT_RECONCILIATION_FEATURE_FLAG_NAME, 'no' );
		WC_Stripe_Option_Lock::acquire( self::LOCK_NAME, 600 );

		$handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->setConstructorArgs( [ new WC_Stripe_Event_Reconciler( $this->store ) ] )
			->setMethods( [ 'process_webhook' ] )
			->getMock();
		$handler->expects( $this->once() )->method( 'process_webhook' );

		$body = wp_json_encode(
			[
				'id'      => self::EVENT_ID,
				'type'    => 'invoice.paid',
				'created' => time(),
			]
		);

		$this->assertSame( 200, WC_Stripe_Webhook_Request_Simulator::dispatch( $handler, $body, $this->sign( $body ) ) );
		$this->assertSame( [], $this->store->records );
	}

	/**
	 * A deferred job that throws abandons its event, so the stuck-event sweep does not process it again.
	 */
	public function test_failed_deferred_job_abandons_the_event(): void {
		$this->store->seed(
			new WC_Stripe_Event_Record( self::EVENT_ID, WC_Stripe_Event_Store_Interface::STATUS_PROCESSING, 'payment_intent.succeeded', time(), false ),
			time() - 2 * HOUR_IN_SECONDS
		);
		$reconciler   = new WC_Stripe_Event_Reconciler( $this->store );
		$handler      = new WC_Stripe_Webhook_Handler( $reconciler );
		$notification = (object) [
			'id'       => self::EVENT_ID,
			'type'     => 'payment_intent.succeeded',
			'created'  => time(),
			'livemode' => false,
		];

		try {
			// The order no longer exists.
			$handler->process_deferred_webhook(
				'payment_intent.succeeded',
				[
					'order_id'  => 0,
					'intent_id' => 'pi_1Deleted',
				],
				$notification
			);
			$this->fail( 'The deferred job should rethrow, so Action Scheduler marks it failed.' );
		} catch ( Exception $e ) {
			$this->assertStringContainsString( 'order_id', $e->getMessage() );
		}

		$this->assertSame( WC_Stripe_Event_Store_Interface::STATUS_ABANDONED, $this->store->get( self::EVENT_ID )->status );

		$reconciler->requeue_stuck_events();

		$this->assertFalse( as_has_scheduled_action( WC_Stripe_Event_Reconciler::PROCESS_ACTION, [ self::EVENT_ID ] ) );
	}

	/**
	 * @return WP_Error
	 */
	public function block_http() {
		return new WP_Error( 'no_http_in_tests', 'Outbound HTTP is blocked in this test.' );
	}

	private function sign( string $body ): array {
		$timestamp = time();
		return [
			'USER-AGENT'       => 'Stripe/1.0 (+https://docs.stripe.com/webhooks)',
			'CONTENT-TYPE'     => 'application/json; charset=utf-8',
			'STRIPE-SIGNATURE' => 't=' . $timestamp . ',v1=' . hash_hmac( 'sha256', $timestamp . '.' . $body, self::WEBHOOK_SECRET ),
		];
	}

	/**
	 * @return string|null
	 */
	private function read_lock() {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_NAME ) );
	}
}
