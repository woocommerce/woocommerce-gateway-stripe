<?php
/**
 * Unit tests for the UPE payment gateway
 */
class WC_Stripe_UPE_Payment_Gateway_Test extends WP_UnitTestCase {

	const UPE_AVAILABLE_METHODS = [
		WC_Stripe_UPE_Payment_Method_CC::class,
		WC_Stripe_UPE_Payment_Method_Giropay::class,
		WC_Stripe_UPE_Payment_Method_Eps::class,
		WC_Stripe_UPE_Payment_Method_Bancontact::class,
		WC_Stripe_UPE_Payment_Method_Ideal::class,
		WC_Stripe_UPE_Payment_Method_Sepa::class,
	];
	/**
	 * Mock UPE Gateway
	 *
	 * @var WC_Stripe_UPE_Payment_Gateway
	 */
	private $mock_gateway;

	/**
	 * Array mapping Stripe IDs to mock WC_Stripe_UPE_Payment_Methods.
	 *
	 * @var array
	 */
	private $mock_payment_methods;

	/**
	 * Mocked value of return_url.
	 *
	 * @var string
	 */
	private $mock_return_url;

	/**
	 * Initial setup.
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_payment_methods = [];
		foreach ( self::UPE_AVAILABLE_METHODS as $payment_method_class ) {
			$mock_payment_method = $this->getMockBuilder( $payment_method_class )
				->setMethods( [ 'is_subscription_item_in_cart' ] )
				->getMock();
			$this->mock_payment_methods[ $mock_payment_method->get_id() ] = $mock_payment_method;
		}

		$this->mock_gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setMethods(
				[
					'get_return_url',
					'get_upe_enabled_payment_method_ids',
				]
			)
			->getMock();

		$this->mock_gateway
			->expects( $this->any() )
			->method( 'get_return_url' )
			->will(
				$this->returnValue( $this->return_url )
			);
	}

	/**
	 * Helper function to set $_POST vars for saved payment method.
	 */
	private function set_postvars_for_saved_payment_method() {
		$token = WC_Helper_Token::create_token( 'pm_mock' );
		$_POST = [
			'payment_method' => WC_Stripe_UPE_Payment_Gateway::ID,
			'wc-' . WC_Stripe_UPE_Payment_Gateway::ID . '-payment-token' => (string) $token->get_id(),
		];
	}

	/**
	 * Helper function to mock subscriptions for internal UPE payment methods.
	 */
	private function set_cart_contains_subscription_items( $cart_contains_subscriptions ) {
		foreach ( $this->mock_payment_methods as $mock_payment_method ) {
			$mock_payment_method->expects( $this->any() )
				->method( 'is_subscription_item_in_cart' )
				->will(
					$this->returnValue( $cart_contains_subscriptions )
				);
		}
	}

	public function test_payment_fields_outputs_fields() {
		$this->set_cart_contains_subscription_items( false );
		$this->mock_gateway->payment_fields();
		$this->expectOutputRegex( '/<div id="wc-stripe-upe-element"><\/div>/' );
	}
}
