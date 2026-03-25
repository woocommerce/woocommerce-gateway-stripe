<?php

/**
 * These tests make assertions against class WC_Stripe_Webhook_State.
 *
 * @package WooCommerce/Stripe/Webhook_State
 *
 * WC_Stripe_Webhook_State_Test class.
 */
class WC_Stripe_Webhook_State_Test extends WP_UnitTestCase {

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
	 * Webhook secret key.
	 *
	 * @var string
	 */
	private $webhook_secret;

	/**
	 * Sets up things all tests need.
	 */
	public function set_up() {
		parent::set_up();
		$this->webhook_secret = 'whsec_123';

		// Resets settings.
		$stripe_settings                        = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['webhook_secret']      = $this->webhook_secret;
		$stripe_settings['test_webhook_secret'] = $this->webhook_secret;
		unset( $stripe_settings['testmode'] );
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->wc_stripe_webhook_handler = new WC_Stripe_Webhook_Handler();
	}

	/**
	 * Tears down the stuff we set up.
	 */
	public function tear_down() {
		// Deletes all webhook options.
		delete_option( WC_Stripe_Webhook_State::OPTION_LIVE_MONITORING_BEGAN_AT );
		delete_option( WC_Stripe_Webhook_State::OPTION_LIVE_LAST_SUCCESS_AT );
		delete_option( WC_Stripe_Webhook_State::OPTION_LIVE_LAST_FAILURE_AT );
		delete_option( WC_Stripe_Webhook_State::OPTION_LIVE_LAST_ERROR );
		delete_option( WC_Stripe_Webhook_State::OPTION_LIVE_PENDING_WEBHOOKS );

		delete_option( WC_Stripe_Webhook_State::OPTION_TEST_MONITORING_BEGAN_AT );
		delete_option( WC_Stripe_Webhook_State::OPTION_TEST_LAST_SUCCESS_AT );
		delete_option( WC_Stripe_Webhook_State::OPTION_TEST_LAST_FAILURE_AT );
		delete_option( WC_Stripe_Webhook_State::OPTION_TEST_LAST_ERROR );
		delete_option( WC_Stripe_Webhook_State::OPTION_TEST_PENDING_WEBHOOKS );

		parent::tear_down();
	}

	private function cleanup_webhook_secret() {
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		unset( $stripe_settings['webhook_secret'] );
		unset( $stripe_settings['test_webhook_secret'] );
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
		$this->wc_stripe_webhook_handler = new WC_Stripe_Webhook_Handler();
	}

	private function set_valid_request_data( $overwrite_timestamp = null ) {
		$timestamp = $overwrite_timestamp ? $overwrite_timestamp : time();

		// Body
		$this->request_body = json_encode(
			[
				'type'    => 'payment_intent.succeeded',
				'created' => $timestamp,
			]
		);

		$signed_payload = $timestamp . '.' . $this->request_body;
		$signature      = hash_hmac( 'sha256', $signed_payload, $this->webhook_secret );

		// Headers
		$this->request_headers = [
			'USER-AGENT'       => 'Stripe/1.0 (+https://docs.stripe.com/webhooks)',
			'CONTENT-TYPE'     => 'application/json; charset=utf-8',
			'STRIPE-SIGNATURE' => 't=' . $timestamp . ',v1=' . $signature,
		];
	}

	private function set_testmode( $testmode = 'yes' ) {
		$stripe_settings             = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = $testmode;
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
	}

	/**
	 * This function is intended to mock WC_Stripe_Webhook_Handler check_for_webhook.
	 * We can't use check_for_webhook directly because it exits.
	 */
	private function process_webhook() {
		// Fills monitoring, last success and last failure timestamps for current mode.
		WC_Stripe_Webhook_State::get_monitoring_began_at();
		$validation_result = $this->wc_stripe_webhook_handler->validate_request( $this->request_headers, $this->request_body, $this->webhook_secret );

		if ( WC_Stripe_Webhook_State::VALIDATION_SUCCEEDED === $validation_result ) {
			$notification = json_decode( $this->request_body );
			WC_Stripe_Webhook_State::set_last_webhook_success_at( $notification->created );
			WC_Stripe_Webhook_State::set_pending_webhooks_count( 0 );
		} else {
			WC_Stripe_Webhook_State::set_last_webhook_failure_at( time() );
			WC_Stripe_Webhook_State::set_last_error_reason( $validation_result );
			WC_Stripe_Webhook_State::set_pending_webhooks_count( 3 );
		}
	}

	// Case 1 (Nominal case): Most recent = success.
	public function test_get_webhook_status_message_most_recent_success() {
		$this->set_valid_request_data();
		$expected_message = '/The most recent [mode] webhook, timestamped (.*), was processed successfully/';

		// Live
		$this->set_testmode( 'no' );
		$this->process_webhook();
		$message = WC_Stripe_Webhook_State::get_webhook_status_message();
		$this->assertMatchesRegularExpression( str_replace( '[mode]', 'live', $expected_message ), $message );
		// Test
		$this->set_testmode();
		$this->process_webhook();
		$message = WC_Stripe_Webhook_State::get_webhook_status_message();
		$this->assertMatchesRegularExpression( str_replace( '[mode]', 'test', $expected_message ), $message );
	}

	// Case 2: No webhooks received yet.
	public function test_get_webhook_status_message_no_webhooks_received() {
		$expected_message = '/No [mode] webhooks have been received since monitoring began at/';

		// Live
		$this->set_testmode( 'no' );
		$message = WC_Stripe_Webhook_State::get_webhook_status_message();
		$this->assertMatchesRegularExpression( str_replace( '[mode]', 'live', $expected_message ), $message );
		// Test
		$this->set_testmode();
		$message = WC_Stripe_Webhook_State::get_webhook_status_message();
		$this->assertMatchesRegularExpression( str_replace( '[mode]', 'test', $expected_message ), $message );
	}

	// Case 3: Failure after success.
	public function test_get_webhook_status_message_failure_after_success() {
		$this->set_valid_request_data();
		$expected_message = '/Warning: The most recent [mode] webhook, received at (.*), could not be processed. Reason: (.*) \(The last [mode] webhook to process successfully was timestamped (.*).\). There are approximately (\d+) webhooks pending./';
		// Live
		$this->set_testmode( 'no' );
		// Process successful webhook.
		$this->process_webhook();
		// Fail next webhook.
		$this->request_headers = [];
		$this->process_webhook();
		$message = WC_Stripe_Webhook_State::get_webhook_status_message();
		$this->assertMatchesRegularExpression( str_replace( '[mode]', 'live', $expected_message ), $message );

		// Test
		$this->set_testmode();
		$this->set_valid_request_data();
		// Process successful webhook.
		$this->process_webhook();
		// Fail next webhook.
		$this->request_headers = [];
		$this->process_webhook();
		$message = WC_Stripe_Webhook_State::get_webhook_status_message();
		$this->assertMatchesRegularExpression( str_replace( '[mode]', 'test', $expected_message ), $message );
	}

	// Case 4: Failure with no prior success.
	public function test_get_webhook_status_message_failure_with_no_prior_success() {
		$this->set_valid_request_data();
		$expected_message = '/Warning: The most recent [mode] webhook, received at (.*), could not be processed. Reason: (.*) \(No [mode] webhooks have been processed successfully since monitoring began at (.*).\). There are approximately (\d+) webhooks pending./';
		// Live
		$this->set_testmode( 'no' );
		// Fail webhook.
		$this->request_headers = [];
		$this->process_webhook();
		$message = WC_Stripe_Webhook_State::get_webhook_status_message();
		$this->assertMatchesRegularExpression( str_replace( '[mode]', 'live', $expected_message ), $message );

		// Test
		$this->set_testmode();
		// Fail webhook.
		$this->process_webhook();
		$message = WC_Stripe_Webhook_State::get_webhook_status_message();
		$this->assertMatchesRegularExpression( str_replace( '[mode]', 'test', $expected_message ), $message );

		// Test that when pending webhooks count is 1 the message is singular.
		$expected_message = '/Warning: (.*).\). There is at least 1 webhook pending./';
		WC_Stripe_Webhook_State::set_pending_webhooks_count( 1 );
		$message = WC_Stripe_Webhook_State::get_webhook_status_message();
		$this->assertMatchesRegularExpression( $expected_message, $message );
	}


	// Test user agent validation ignored
	public function test_skip_user_agent_validation() {
		// Run test without cleaning up webhook secret.
		add_filter(
			'wc_stripe_webhook_is_user_agent_valid',
			function () {
				return false;
			}
		);

		$this->set_valid_request_data();
		$this->process_webhook();
		$this->assertEquals( 'No error', WC_Stripe_Webhook_State::get_last_error_reason() );
	}

	// Test failure reason: empty secret.
	public function test_get_error_reason_empty_secret() {
		$this->cleanup_webhook_secret();
		$this->webhook_secret = '';

		$this->set_valid_request_data();
		$this->process_webhook();
		$this->assertEquals( 'The webhook secret is not set in the store. Please configure the webhooks', WC_Stripe_Webhook_State::get_last_error_reason() );
	}

	/**
	 * Test the error reason returned by `get_last_error_reason()` after processing a webhook
	 * with various request configurations.
	 *
	 * @param string $scenario       One of: no_errors, empty_headers, empty_body,
	 *                                invalid_signature, timestamp_mismatch, signature_mismatch.
	 * @param string $expected       Exact string or regex pattern for the expected error reason.
	 * @param bool   $exact_match    When true use assertEquals; otherwise assertMatchesRegularExpression.
	 * @return void
	 * @dataProvider provide_test_get_error_reason
	 */
	public function test_get_error_reason( string $scenario, string $expected, bool $exact_match ) {
		$this->set_valid_request_data();

		switch ( $scenario ) {
			case 'empty_headers':
				$this->request_headers = [];
				break;
			case 'empty_body':
				$this->request_body = '';
				break;
			case 'invalid_signature':
				$this->request_headers['STRIPE-SIGNATURE'] = 'foo';
				break;
			case 'timestamp_mismatch':
				$this->set_valid_request_data( time() - 600 ); // 10 minutes ago.
				break;
			case 'signature_mismatch':
				$this->request_headers['STRIPE-SIGNATURE'] = 't=' . time() . ',v1=0';
				break;
		}

		$this->process_webhook();

		if ( $exact_match ) {
			$this->assertEquals( $expected, WC_Stripe_Webhook_State::get_last_error_reason() );
		} else {
			$this->assertMatchesRegularExpression( $expected, WC_Stripe_Webhook_State::get_last_error_reason() );
		}
	}

	/**
	 * Data provider for `test_get_error_reason`.
	 *
	 * @return array
	 */
	public function provide_test_get_error_reason(): array {
		return [
			'no errors'          => [ 'no_errors', 'No error', true ],
			'empty headers'      => [ 'empty_headers', '/missing expected headers/', false ],
			'empty body'         => [ 'empty_body', '/missing expected body/', false ],
			'invalid signature'  => [ 'invalid_signature', '/signature was missing or was incorrectly formatted/', false ],
			'timestamp mismatch' => [ 'timestamp_mismatch', '/timestamp in the webhook differed more than five minutes/', false ],
			'signature mismatch' => [ 'signature_mismatch', '/was not signed with the expected signing secret/', false ],
		];
	}
}
