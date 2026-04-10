<?php
/**
 * Test helper for injecting mock Stripe SDK clients.
 *
 * @package WooCommerce/Stripe/Tests
 */
class WC_Stripe_SDK_Test_Helper {
	/**
	 * Inject a mock SDK client that returns a given response for common service calls.
	 *
	 * @param array|object $data The data to convert into a Stripe object response.
	 */
	public static function inject_sdk_payment_method_response( $data ) {
		$data = (array) $data;
		$obj  = \Stripe\Util\Util::convertToStripeObject( $data, [] );

		$mock_service = new class( $obj ) {
			private $response;

			public function __construct( $response ) {
				$this->response = $response;
			}

			public function __call( $name, $args ) {
				return $this->response;
			}
		};

		$services = [
			'paymentMethods'              => $mock_service,
			'sources'                     => $mock_service,
			'customers'                   => $mock_service,
			'paymentMethodConfigurations' => $mock_service,
		];

		$mock_client = new class( $services ) extends \Stripe\StripeClient {
			private $services;

			public function __construct( $services ) {
				$this->services = $services;
			}

			public function __get( $name ) {
				return $this->services[ $name ] ?? null;
			}
		};

		WC_Stripe_API::set_sdk_for_testing( $mock_client );
	}

	/**
	 * Reset the SDK client to null.
	 */
	public static function reset() {
		WC_Stripe_API::set_sdk_for_testing( null );
	}
}
