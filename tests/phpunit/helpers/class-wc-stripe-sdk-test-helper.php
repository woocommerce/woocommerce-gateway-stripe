<?php
/**
 * Helper class for mocking the Stripe SDK in tests.
 *
 * @package WooCommerce\Tests
 */

/**
 * Creates a mock StripeClient that returns configurable responses for checkout session operations.
 */
class WC_Stripe_SDK_Test_Helper {

	/**
	 * Create a mock StripeClient with configurable checkout session responses.
	 *
	 * @param array $config {
	 *     @type \Stripe\Checkout\Session|null $create_response   Response for create().
	 *     @type \Stripe\Checkout\Session|null $retrieve_response Response for retrieve().
	 *     @type \Stripe\Checkout\Session|null $update_response   Response for update().
	 *     @type \Stripe\Exception\ApiErrorException|null $create_exception   Exception for create().
	 *     @type \Stripe\Exception\ApiErrorException|null $retrieve_exception Exception for retrieve().
	 *     @type \Stripe\Exception\ApiErrorException|null $update_exception   Exception for update().
	 * }
	 * @return \Stripe\StripeClient&\PHPUnit\Framework\MockObject\MockObject
	 */
	public static function create_mock_sdk( \PHPUnit\Framework\TestCase $test_case, array $config = [] ) {
		$session_service = $test_case->getMockBuilder( \Stripe\Service\Checkout\SessionService::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'create', 'retrieve', 'update' ] )
			->getMock();

		if ( isset( $config['create_response'] ) ) {
			$session_service->method( 'create' )->willReturn( $config['create_response'] );
		} elseif ( isset( $config['create_exception'] ) ) {
			$session_service->method( 'create' )->willThrowException( $config['create_exception'] );
		}

		if ( isset( $config['retrieve_response'] ) ) {
			$session_service->method( 'retrieve' )->willReturn( $config['retrieve_response'] );
		} elseif ( isset( $config['retrieve_exception'] ) ) {
			$session_service->method( 'retrieve' )->willThrowException( $config['retrieve_exception'] );
		}

		if ( isset( $config['update_response'] ) ) {
			$session_service->method( 'update' )->willReturn( $config['update_response'] );
		} elseif ( isset( $config['update_exception'] ) ) {
			$session_service->method( 'update' )->willThrowException( $config['update_exception'] );
		}

		$checkout_service           = new stdClass();
		$checkout_service->sessions = $session_service;

		$mock_client = $test_case->getMockBuilder( \Stripe\StripeClient::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_client->checkout = $checkout_service;

		return $mock_client;
	}

	/**
	 * Create a \Stripe\Checkout\Session from an array of properties.
	 *
	 * @param array $data Checkout session properties.
	 * @return \Stripe\Checkout\Session
	 */
	public static function create_checkout_session_object( array $data = [] ): \Stripe\Checkout\Session {
		$defaults = [
			'id'            => 'cs_test_' . wp_generate_password( 24, false ),
			'object'        => 'checkout.session',
			'client_secret' => 'cs_secret_test_' . wp_generate_password( 24, false ),
			'status'        => 'open',
			'mode'          => 'payment',
		];

		return \Stripe\Checkout\Session::constructFrom( array_merge( $defaults, $data ) );
	}
}
