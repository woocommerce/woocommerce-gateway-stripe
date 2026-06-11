<?php
/**
 * Class WC_Stripe_REST_Payment_Intents_Controller_Test
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WC_Stripe_REST_Payment_Intents_Controller_Test extends WP_UnitTestCase {
	const ENDPOINT_URL = '/wc/v3/wc_stripe/payment_intents';

	public static function set_up_before_class() {
		parent::set_up_before_class();

		do_action( 'rest_api_init' );
	}

	private function send_request( $params = [] ) {
		$request = new WP_REST_Request(
			WP_REST_Server::READABLE,
			self::ENDPOINT_URL
		);

		if ( $params ) {
			foreach ( $params as $param_name => $param_value ) {
				$request->set_param( $param_name, $param_value );
			}
		}

		$response = rest_get_server()->dispatch( $request );

		return $response;
	}

	public function test_permission_check_denies_unauthorized_call() {
		$subscriber_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );

		wp_set_current_user( $subscriber_id );

		$response = $this->send_request();

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_permission_check_denies_authorized_call() {
		wp_set_current_user( 1 );

		$response = $this->send_request();

		$this->assertSame( 200, $response->get_status() );
	}

	public static function provide_wrong_format_params(): array {
		return [
			[ 'starting_after', '' ],
			[ 'starting_after', 'pi_3:TbL9RJlUF0dQbSB00q0FJS2' ],
			[ 'ending_before', '' ],
			[ 'ending_before', 'pi_;3TbL9RJlUF0dQbSB00q0FJS2' ],
		];
	}
	/**
	 * @dataProvider provide_wrong_format_params
	*/
	public function test_wrong_format_params( $param_name, $param_value ) {
		wp_set_current_user( 1 );

		$response = $this->send_request( [ $param_name => $param_value ] );

		$this->assertSame( 400, $response->get_status() );
	}
}
