<?php
/**
 * These tests make assertions against class WC_Stripe_Webhook_State.
 *
 * @package WooCommerce_Stripe/Tests/Webhook_State
 */

/**
 * WC_Stripe_Webhook_State_Test class.
 */
class WC_Stripe_Webhook_State_Test extends WP_UnitTestCase {

    /**
	 * System under test.
	 *
	 * @var WC_Stripe_Webhook_State
	 */
    private $wc_stripe_webhook_state;

    /**
     * Webhook handler class.
     *
     * @var WC_Stripe_Webhook_Handler
     */
    private $wc_stripe_webhook_handler;

    /**
     * Request headers.
     *
     * @var array
     */
    private $request_headers;

    /**
     * Request body.
     *
     * @var string
     */
    private $request_body;

    /**
	 * Sets up things all tests need.
	 */
    public function setUp() {
		parent::setUp();

        $this->wc_stripe_webhook_state = WC_Stripe_Webhook_State::class;
        $this->wc_stripe_webhook_handler = new WC_Stripe_Webhook_Handler;
    }

    /**
	 * Tears down the stuff we set up.
	 */
    public function tearDown() {
		parent::tearDown();

        $stripe_settings = get_option( 'woocommerce_stripe_settings', array() );
        // Deletes testmode setting
        unset( $stripe_settings['testmode'] );
        update_option( 'woocommerce_stripe_settings', $stripe_settings );
        // Deletes all webhook options
        delete_option( $this->wc_stripe_webhook_state::OPTION_LIVE_MONITORING_BEGAN_AT );
        delete_option( $this->wc_stripe_webhook_state::OPTION_LIVE_LAST_SUCCESS_AT );
        delete_option( $this->wc_stripe_webhook_state::OPTION_LIVE_LAST_FAILURE_AT );
        delete_option( $this->wc_stripe_webhook_state::OPTION_LIVE_LAST_ERROR );

        delete_option( $this->wc_stripe_webhook_state::OPTION_TEST_MONITORING_BEGAN_AT );
        delete_option( $this->wc_stripe_webhook_state::OPTION_TEST_LAST_SUCCESS_AT );
        delete_option( $this->wc_stripe_webhook_state::OPTION_TEST_LAST_FAILURE_AT );
        delete_option( $this->wc_stripe_webhook_state::OPTION_TEST_LAST_ERROR );
    }

    private function set_valid_request_data() {
        // Headers
        $this->request_headers = [
            'USER_AGENT'       => 'Stripe/1.0 (+https://stripe.com/docs/webhooks)',
            'CONTENT_TYPE'     => 'application/json; charset=utf-8',
            'STRIPE_SIGNATURE' => 't=' . time() . ',v1=1,v0=0',
        ];

        // Body
        $this->request_body = json_encode(
            [
                'type'    => 'payment_intent.succeeded',
                'created' => time(),
            ]
        );
    }

    private function set_testmode() {
        $stripe_settings = get_option( 'woocommerce_stripe_settings', array() );
        $stripe_settings['testmode'] = 'yes';
        update_option( 'woocommerce_stripe_settings', $stripe_settings );
    }

    /**
     * This function is intended to mock WC_Stripe_Webhook_Handler check_for_webhook.
     * We can't use check_for_webhook directly because it exits.
     */
    private function process_webhook() {
        // Fills monitoring, last success and last failure timestamps for current mode.
        $this->wc_stripe_webhook_state::get_monitoring_began_at();
        $validation_result = $this->wc_stripe_webhook_handler->validate_request( $this->request_headers, $this->request_body );

		if ( $this->wc_stripe_webhook_state::VALIDATION_SUCCEEDED === $validation_result ) {
			$notification = json_decode( $this->request_body );
			$this->wc_stripe_webhook_state::set_last_webhook_success_at( $notification->created );
		} else {
			$this->wc_stripe_webhook_state::set_last_webhook_failure_at( current_time( 'timestamp', true ) );
			$this->wc_stripe_webhook_state::set_last_error_reason( $validation_result );
		}
    }

    // Case 1 (Nominal case): Most recent = success.
    public function test_get_webhook_status_message_most_recent_success() {
        $this->set_valid_request_data();
        $expected_message = '/The most recent [mode] webhook, timestamped (.*), was processed successfully/';

        // Live
        $this->process_webhook();
        $message = $this->wc_stripe_webhook_state::get_webhook_status_message();
        $this->assertRegExp( str_replace( '[mode]', 'live', $expected_message ), $message );
        // Test
        $this->set_testmode();
        $this->process_webhook();
        $message = $this->wc_stripe_webhook_state::get_webhook_status_message();
        $this->assertRegExp( str_replace( '[mode]', 'test', $expected_message ), $message );
    }

    // Case 2: No webhooks received yet.
    public function test_get_webhook_status_message_no_webhooks_received() {
        $expected_message = '/No [mode] webhooks have been received since monitoring began at/';

        // Live
        $message = $this->wc_stripe_webhook_state::get_webhook_status_message();
        $this->assertRegExp( str_replace( '[mode]', 'live', $expected_message ), $message );
        // Test
        $this->set_testmode();
        $message = $this->wc_stripe_webhook_state::get_webhook_status_message();
        $this->assertRegExp( str_replace( '[mode]', 'test', $expected_message ), $message );
    }

    // Case 3: Failure after success.
    public function test_get_webhook_status_message_failure_after_success() {
        $this->set_valid_request_data();
        $expected_message = '/Warning: The most recent [mode] webhook, received at (.*), could not be processed. Reason: (.*) \(The last [mode] webhook to process successfully was timestamped/';
        // Live
        // Process successful webhook.
        $this->process_webhook();
        // Fail next webhook.
        $this->request_headers = [];
        $this->process_webhook();
        $message = $this->wc_stripe_webhook_state::get_webhook_status_message();
        $this->assertRegExp( str_replace( '[mode]', 'live', $expected_message ), $message );

        // Test
        $this->set_testmode();
        $this->set_valid_request_data();
        // Process successful webhook.
        $this->process_webhook();
        // Fail next webhook.
        $this->request_headers = [];
        $this->process_webhook();
        $message = $this->wc_stripe_webhook_state::get_webhook_status_message();
        $this->assertRegExp( str_replace( '[mode]', 'test', $expected_message ), $message );
    }

    public function test_get_webhook_status_message_failure_with_no_prior_success() {
        $this->set_valid_request_data();
        $expected_message = '/Warning: The most recent [mode] webhook, received at (.*), could not be processed. Reason: (.*) \(No [mode] webhooks have been processed successfully since monitoring began at/';
        // Live
        // Fail webhook.
        $this->request_headers = [];
        $this->process_webhook();
        $message = $this->wc_stripe_webhook_state::get_webhook_status_message();
        $this->assertRegExp( str_replace( '[mode]', 'live', $expected_message ), $message );

        // Test
        $this->set_testmode();
        // Fail webhook.
        $this->process_webhook();
        $message = $this->wc_stripe_webhook_state::get_webhook_status_message();
        $this->assertRegExp( str_replace( '[mode]', 'test', $expected_message ), $message );
    }

    // - TODO: Add failure message tests

}