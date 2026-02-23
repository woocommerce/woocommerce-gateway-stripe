<?php

namespace WooCommerce\Stripe\Tests\PaymentMethods;

use Automattic\WooCommerce\Enums\OrderStatus;
use Exception;
use WC_Stripe_Order_Helper;
use WooCommerce\Stripe\Tests\Helpers\OC_Test_Helper;
use WC_Stripe_Database_Cache;
use WooCommerce\Stripe\Tests\Helpers\PMC_Test_Helper;
use WooCommerce\Stripe\Tests\Helpers\UPE_Test_Helper;
use WC_Data_Exception;
use WC_Order;
use WC_Stripe_Co_Branded_CC_Compatibility;
use WC_Stripe_Customer;
use WC_Stripe_Exception;
use WC_Stripe_Feature_Flags;
use WC_Stripe_Helper;
use WC_Stripe_Intent_Controller;
use WC_Stripe_Intent_Status;
use WC_Stripe_Payment_Methods;
use WC_Stripe_UPE_Payment_Gateway;
use WC_Stripe_UPE_Payment_Method_ACH;
use WC_Stripe_UPE_Payment_Method_ACSS;
use WC_Stripe_UPE_Payment_Method_Affirm;
use WC_Stripe_UPE_Payment_Method_Afterpay_Clearpay;
use WC_Stripe_UPE_Payment_Method_Alipay;
use WC_Stripe_UPE_Payment_Method_Amazon_Pay;
use WC_Stripe_UPE_Payment_Method_Bancontact;
use WC_Stripe_UPE_Payment_Method_BLIK;
use WC_Stripe_UPE_Payment_Method_Boleto;
use WC_Stripe_UPE_Payment_Method_Cash_App_Pay;
use WC_Stripe_UPE_Payment_Method_CC;
use WC_Stripe_UPE_Payment_Method_Eps;
use WC_Stripe_UPE_Payment_Method_Ideal;
use WC_Stripe_UPE_Payment_Method_Klarna;
use WC_Stripe_UPE_Payment_Method_Link;
use WC_Stripe_UPE_Payment_Method_Multibanco;
use WC_Stripe_UPE_Payment_Method_Oxxo;
use WC_Stripe_UPE_Payment_Method_P24;
use WC_Stripe_UPE_Payment_Method_Sepa;
use WC_Stripe_UPE_Payment_Method_Wechat_Pay;
use WC_Subscriptions_Helpers;
use MockAction;
use WC_Stripe_API;
use WooCommerce\Stripe\Tests\Helpers\WC_Helper_Order;
use WooCommerce\Stripe\Tests\Helpers\WC_Helper_Token;
use WooCommerce\Stripe\Tests\WC_Mock_Stripe_API_Unit_Test_Case;

/**
 * Unit tests for the UPE payment gateway
 */
class WC_Stripe_UPE_Payment_Gateway_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	/**
	 * Mock UPE Gateway
	 *
	 * @var WC_Stripe_UPE_Payment_Gateway
	 */
	private $mock_gateway;

	/**
	 * Mock WC Stripe Customer
	 *
	 * @var WC_Stripe_Customer
	 */
	private $mock_stripe_customer;

	/**
	 * Array of available payment methods.
	 *
	 * @var array
	 */
	private $available_payment_methods;

	/**
	 * Mocked value of return_url.
	 *
	 * @var string
	 */
	const MOCK_RETURN_URL = 'test_url';

	/**
	 * Base template for Stripe card payment method.
	 */
	const MOCK_CARD_PAYMENT_METHOD_TEMPLATE = [
		'type'                          => WC_Stripe_Payment_Methods::CARD,
		WC_Stripe_Payment_Methods::CARD => [
			'brand'     => 'visa',
			'networks'  => [ 'preferred' => 'visa' ],
			'exp_month' => '7',
			'funding'   => 'credit',
			'last4'     => '4242',
		],
	];

	/**
	 * Base template for SEPA Direct Debit payment method.
	 */
	const MOCK_SEPA_PAYMENT_METHOD_TEMPLATE = [
		'type'                                => WC_Stripe_Payment_Methods::SEPA_DEBIT,
		'object'                              => 'payment_method',
		WC_Stripe_Payment_Methods::SEPA_DEBIT => [
			'last4'       => '7061',
			'fingerprint' => 'fp_mock',
		],
	];

	/**
	 * Base template for Stripe payment intent.
	 */
	const MOCK_CARD_PAYMENT_INTENT_TEMPLATE = [
		'id'                   => 'pi_mock',
		'object'               => 'payment_intent',
		'status'               => WC_Stripe_Intent_Status::SUCCEEDED,
		'last_payment_error'   => [],
		'client_secret'        => 'cs_mock',
		'charges'              => [
			'total_count' => 1,
			'data'        => [
				[
					'id'                     => 'ch_mock',
					'captured'               => true,
					'payment_method_details' => [],
					'status'                 => 'succeeded',
				],
			],
		],
		'payment_method_types' => [
			WC_Stripe_Payment_Methods::CARD,
			WC_Stripe_Payment_Methods::LINK,
		],
	];

	/**
	 * Base template for Wallet payment intent.
	 */
	const MOCK_WECHAT_PAY_PAYMENT_INTENT_TEMPLATE = [
		'id'                 => 'pi_mock',
		'object'             => 'payment_intent',
		'status'             => 'succeeded',
		'last_payment_error' => [],
		'client_secret'      => 'cs_mock',
		'charges'            => [
			'total_count' => 1,
			'data'        => [
				[
					'id'                     => 'ch_mock',
					'captured'               => true,
					'payment_method_details' => [],
					'status'                 => 'succeeded',
				],
			],
		],
	];

	/**
	 * Base template for Stripe payment intent.
	 */
	const MOCK_CARD_SETUP_INTENT_TEMPLATE = [
		'object'           => 'setup_intent',
		'status'           => WC_Stripe_Intent_Status::SUCCEEDED,
		'client_secret'    => 'cs_mock',
		'last_setup_error' => [],
	];

	/**
	 * Initial setup.
	 */
	public function set_up() {
		parent::set_up();

		update_option( WC_Stripe_Feature_Flags::AMAZON_PAY_FEATURE_FLAG_NAME, 'yes' );

		$upe_helper = new UPE_Test_Helper();
		$upe_helper->enable_upe();
		$upe_helper->reload_payment_gateways();

		$stripe_settings                               = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['sepa_tokens_for_ideal']      = 'yes';
		$stripe_settings['sepa_tokens_for_bancontact'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->mock_gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setConstructorArgs( [] )
			->onlyMethods(
				[
					'create_and_confirm_intent_for_off_session',
					'generate_payment_request',
					'get_latest_charge_from_intent',
					'get_return_url',
					'get_stripe_customer_id',
					'has_subscription',
					'maybe_process_pre_orders',
					'mark_order_as_pre_ordered',
					'is_pre_order_item_in_cart',
					'is_pre_order_product_charged_upfront',
					'prepare_order_source',
					'stripe_request',
					'get_stripe_customer_from_order',
					'display_order_fee',
					'display_order_payout',
					'get_intent_from_order',
					'has_pre_order_charged_upon_release',
					'has_pre_order',
					'update_saved_payment_method',
				]
			)
			->getMock();

		$this->mock_gateway
			->method( 'get_return_url' )
			->will(
				$this->returnValue( self::MOCK_RETURN_URL )
			);

		$this->mock_gateway->intent_controller = $this->getMockBuilder( WC_Stripe_Intent_Controller::class )
			->onlyMethods( [ 'create_and_confirm_payment_intent', 'update_and_confirm_payment_intent', 'create_and_confirm_setup_intent' ] )
			->getMock();

		$this->mock_stripe_customer = $this->getMockBuilder( WC_Stripe_Customer::class )
			->disableOriginalConstructor()
			->onlyMethods(
				[
					'create_customer',
					'update_customer',
				]
			)
			->getMock();

		$this->mock_stripe_customer
			->method( 'create_customer' )
			->will(
				$this->returnValue( 'cus_mock' )
			);
		$this->mock_stripe_customer
			->method( 'update_customer' )
			->will(
				$this->returnValue( 'cus_mock' )
			);

		$order_helper = $this->createPartialMock(
			WC_Stripe_Order_Helper::class,
			[ 'lock_order_payment', 'unlock_order_payment' ]
		);

		$order_helper
			->method( 'lock_order_payment' )
			->will(
				$this->returnValue( false )
			);

		$order_helper->method( 'unlock_order_payment' );

		WC_Stripe_Order_Helper::set_instance( $order_helper );
	}

	public function tear_down() {
		delete_option( WC_Stripe_Feature_Flags::AMAZON_PAY_FEATURE_FLAG_NAME );

		// The tests in this file do not mock ALL the calls to the Stripe API, and as we use mocked API keys they trigger the 401 rate-limiter,
		// this is not a problem for these tests as they don't depend on the reponses.
		//
		// TODO: Remove this once we've mocked all calls to the Stripe API (either using the pre_http_request filter, or by using a mocked WC_Stripe_API class).
		WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );

		parent::tear_down();
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
		return $token;
	}

	/**
	 * Convert response array to object.
	 */
	private function array_to_object( $array ) {
		return json_decode( wp_json_encode( $array ) );
	}

	/**
	 * Helper function to get amount, description, and metadata for Stripe requests.
	 *
	 * @param WC_Order $order Test WC Order.
	 *
	 * @return array
	 */
	private function get_order_details( $order ) {
		$total        = $order->get_total();
		$currency     = $order->get_currency();
		$order_id     = $order->get_id();
		$order_number = $order->get_order_number();
		$order_key    = $order->get_order_key();
		$total_tax    = $order->get_total_tax();
		$amount       = WC_Stripe_Helper::get_stripe_amount( $total, $currency );
		$description  = "Test Blog - Order $order_number";
		$metadata     = [
			'customer_name'              => 'Jeroen Sormani',
			'customer_email'             => 'admin@example.org',
			'site_url'                   => 'http://example.org',
			'order_id'                   => $order_number,
			'order_key'                  => $order_key,
			'payment_type'               => 'single',
			'signature'                  => sprintf( '%d:%s', $order->get_id(), md5( implode( '-', [ absint( $order->get_id() ), $order->get_order_key(), $order->get_customer_id(), $amount ] ) ) ),
			'tax_amount'                 => WC_Stripe_Helper::get_stripe_amount( $total_tax, strtolower( $currency ) ),
			'is_legacy_checkout_enabled' => 'no',
			'is_oc_enabled'              => 'no',
			'pmc_enabled'                => 'no',
		];
		return [ $amount, $description, $metadata, strtolower( $currency ) ];
	}

	/**
	 * Helper method to create a mock express checkout payment method.
	 *
	 * @param string $payment_method_id      The payment method ID.
	 * @param string $express_payment_method The express payment method type.
	 * @return object The mock express checkout payment method.
	 */
	private function get_mock_express_checkout_payment_method( string $payment_method_id, string $express_payment_method ): object {
		return (object) [
			'id'              => $payment_method_id,
			'object'          => 'payment_method',
			'billing_details' => [
				'address' => [
					'city'        => 'San Francisco',
					'country'     => 'US',
					'line1'       => '60 29th Street 343',
					'line2'       => '',
					'postal_code' => '94110',
					'state'       => 'CA',
				],
				'email'   => 'test.express.checkout@example.com',
				'name'    => 'Test Express Checkout',
				'phone'   => '+1234567890',
				'tax_id'  => null,
			],
			'type'            => 'card',
			'card'            => [
				'brand'       => 'visa',
				'last4'       => '4242',
				'country'     => 'US',
				'exp_month'   => '12',
				'exp_year'    => '2025',
				'funding'     => 'credit',
				'fingerprint' => 'FingerMOCK',
				'wallet'      => [
					'type'                  => $express_payment_method,
					$express_payment_method => [
						'type' => $express_payment_method,
					],
				],
			],
		];
	}

	/**
	 * @dataProvider get_upe_available_payment_methods_provider
	 */
	public function test_get_upe_available_payment_methods( $country, $available_payment_methods ) {
		$this->mock_payment_method_configurations( $available_payment_methods );
		$this->set_stripe_account_data( [ 'country' => $country ] ); // TODO: Verify if the country is actually changing in the gateway.
		$this->assertSame( $available_payment_methods, $this->mock_gateway->get_upe_available_payment_methods(), "Available payment methods are not the same for $country" );
	}

	/**
	 * Data provider for {@see test_get_upe_available_payment_methods()}.
	 *
	 * @return array[]
	 */
	public function get_upe_available_payment_methods_provider(): array {
		return [
			[
				'US',
				[
					WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_ACH::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Alipay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Amazon_Pay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Klarna::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Affirm::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Afterpay_Clearpay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Eps::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Bancontact::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Boleto::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Ideal::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Oxxo::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_P24::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Multibanco::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Wechat_Pay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Cash_App_Pay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_ACSS::STRIPE_ID,
				],
			],
			[
				'NON_US',
				[
					WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Alipay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Eps::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Bancontact::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Boleto::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Ideal::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Oxxo::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_P24::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_ACSS::STRIPE_ID,
				],
			],
			[ // TODO: Fix each payment method's `is_available_for_account_country` function to match supported countries.
				'PL',
				[
					WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Alipay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_BLIK::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Klarna::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Eps::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Bancontact::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Boleto::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Ideal::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Oxxo::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_P24::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Multibanco::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_ACSS::STRIPE_ID,
				],
			],
		];
	}

	/**
	 * Tests for `get_upe_enabled_at_checkout_payment_method_ids`.
	 *
	 * @param array $available_methods The available payment methods.
	 * @param bool $oc_enabled Whether the OC feature is enabled.
	 * @param array $expected The expected payment method IDs.
	 * @return void
	 *
	 * @dataProvider provide_test_get_upe_enabled_at_checkout_payment_method_ids
	 */
	public function test_get_upe_enabled_at_checkout_payment_method_ids( $available_methods, $oc_enabled, $expected ) {
		$this->mock_gateway->oc_enabled = $oc_enabled;

		$this->mock_payment_method_configurations( $available_methods );

		$actual = $this->mock_gateway->get_upe_enabled_at_checkout_payment_method_ids();

		// Clean up.
		$this->mock_gateway->oc_enabled = false;

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider for `test_get_upe_enabled_at_checkout_payment_method_ids`.
	 *
	 * @return array[]
	 */
	public function provide_test_get_upe_enabled_at_checkout_payment_method_ids() {
		return [
			'Default'    => [
				'available methods' => [
					WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
				],
				'OC enabled'        => false,
				'expected'          => [
					WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
				],
			],
			'OC enabled' => [
				'available methods (ignored)' => [
					WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
				],
				'OC enabled'                  => true,
				'expected'                    => [
					WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
				],
			],
		];
	}

	/**
	 * CLASSIC CHECKOUT TESTS.
	 */

	/**
	 * Test payment fields HTML output.
	 */
	public function test_payment_fields_outputs_fields() {
		$this->mock_gateway->payment_fields();
		$this->expectOutputRegex( '/<div class="wc-stripe-upe-element" data-payment-method-type="card"><\/div>/' );
	}

	/**
	 * Test basic checkout process_payment flow with deferred intent.
	 *
	 * @dataProvider provide_process_payment_deferred_intent_returns_valid_response
	 */
	public function test_process_payment_deferred_intent_returns_valid_response( $post_vars ) {
		$customer_id = 'cus_mock';
		$order       = WC_Helper_Order::create_order();
		$currency    = $order->get_currency();
		$order_id    = $order->get_id();

		$mock_intent = (object) wp_parse_args(
			[
				'payment_method' => 'pm_mock',
				'charges'        => (object) [
					'data' => [
						(object) [
							'id'       => $order_id,
							'captured' => 'yes',
							'status'   => 'succeeded',
						],
					],
				],
			],
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE
		);

		// Set the appropriate POST flag to trigger a deferred intent request.
		$_POST = $post_vars;

		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'update_saved_payment_method' );

		$response = $this->mock_gateway->process_payment( $order_id );

		$this->assertEquals( 'success', $response['result'] );
		$this->assertEquals( self::MOCK_RETURN_URL, $response['redirect'] );
	}

	/**
	 * Provider for `test_process_payment_deferred_intent_returns_valid_response`.
	 */
	public function provide_process_payment_deferred_intent_returns_valid_response() {
		return [
			'with-payment-method'     => [
				[
					'payment_method'               => 'stripe',
					'wc-stripe-payment-method'     => 'pm_mock',
					'wc-stripe-confirmation-token' => '',
					'wc-stripe-is-deferred-intent' => '1',
				],
			],
			'with-confirmation-token' => [
				[
					'payment_method'               => 'stripe',
					'wc-stripe-payment-method'     => '',
					'wc-stripe-confirmation-token' => 'ctoken_mock',
					'wc-stripe-is-deferred-intent' => '1',
				],
			],
		];
	}

	/**
	 * Test SCA/3DS checkout process_payment flow with deferred intent.
	 */
	public function test_process_payment_deferred_intent_with_required_action_returns_valid_response() {
		$customer_id = 'cus_mock';
		$order       = WC_Helper_Order::create_order();
		$order_id    = $order->get_id();

		$mock_intent = (object) wp_parse_args(
			[
				'status'         => WC_Stripe_Intent_Status::REQUIRES_ACTION,
				'data'           => [
					(object) [
						'id'       => $order_id,
						'captured' => 'yes',
						'status'   => 'succeeded',
					],
				],
				'payment_method' => 'pm_mock',
				'charges'        => (object) [
					'total_count' => 0, // Intents requiring SCA verification respond with no charges.
					'data'        => [],
				],
			],
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE
		);

		// Set the appropriate POST flag to trigger a deferred intent request.
		$_POST = [
			'payment_method'               => 'stripe',
			'wc-stripe-payment-method'     => 'pm_mock',
			'wc-stripe-is-deferred-intent' => '1',
		];

		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		// We only use this when handling mandates.
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( null );

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'update_saved_payment_method' );

		$response = $this->mock_gateway->process_payment( $order_id );

		$this->assertEquals( 'success', $response['result'] );
		$this->assertMatchesRegularExpression( "/#wc-stripe-confirm-pi:{$order_id}:{$mock_intent->client_secret}/", $response['redirect'] );
	}

	/**
	 * Test Wallet checkout process_payment flow with deferred intent.
	 *
	 * @param string $payment_method Payment method to test.
	 * @param bool $free_order Whether the order is free.
	 * @param bool $saved_token Whether the payment method is saved.
	 * @dataProvider provide_process_payment_deferred_intent_with_required_action_for_wallet_returns_valid_response
	 * @throws WC_Data_Exception When setting order payment method fails.
	 */
	public function test_process_payment_deferred_intent_with_required_action_for_wallet_returns_valid_response( $payment_method, $free_order = false, $saved_token = false ) {
		$customer_id = 'cus_mock';
		$order       = WC_Helper_Order::create_order( 1, null, [ 'total' => $free_order ? 0 : 50 ] );
		$order_id    = $order->get_id();

		// Set payment gateway.
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Method_Wechat_Pay::STRIPE_ID );
		$order->save();

		$mock_intent = (object) wp_parse_args(
			[
				'status'               => WC_Stripe_Intent_Status::REQUIRES_ACTION,
				'object'               => 'payment_intent',
				'data'                 => [
					(object) [
						'id'       => $order_id,
						'captured' => 'yes',
						'status'   => 'succeeded',
					],
				],
				'payment_method'       => 'pm_mock',
				'payment_method_types' => [ $payment_method ],
				'charges'              => (object) [
					'total_count' => 0, // Intents requiring SCA verification respond with no charges.
					'data'        => [],
				],
			],
			self::MOCK_WECHAT_PAY_PAYMENT_INTENT_TEMPLATE
		);

		// Set the appropriate POST flag to trigger a deferred intent request.
		$_POST = [
			'payment_method'               => 'stripe_' . $payment_method,
			'wc-stripe-payment-method'     => 'pm_mock',
			'wc-stripe-is-deferred-intent' => '1',
		];

		if ( $saved_token ) {
			$token = WC_Helper_Token::create_token( 'pm_mock' );
			$token->set_gateway_id( 'stripe_' . $payment_method );
			$token->save();

			$_POST[ 'wc-stripe_' . $payment_method . '-payment-token' ] = (string) $token->get_id();
		}

		$this->mock_gateway->intent_controller
			->expects( $free_order ? $this->never() : $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willReturn( $mock_intent );

		$create_and_confirm_setup_intent_num_calls = $free_order && ! ( $saved_token && WC_Stripe_Payment_Methods::CASHAPP_PAY === $payment_method ) ? 1 : 0;
		$this->mock_gateway->intent_controller
			->expects( $this->exactly( $create_and_confirm_setup_intent_num_calls ) )
			->method( 'create_and_confirm_setup_intent' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		// We only use this when handling mandates.
		$this->mock_gateway
			->expects( $saved_token ? $this->never() : $this->once() )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( null );

		$this->mock_gateway
			->expects( $saved_token ? $this->once() : $this->never() )
			->method( 'update_saved_payment_method' );

		$response   = $this->mock_gateway->process_payment( $order_id );
		$return_url = self::MOCK_RETURN_URL;

		if ( $saved_token ) {
			$expected_redirect_url = '/' . self::MOCK_RETURN_URL . '/';
		} else {
			$expected_redirect_url = "/#wc-stripe-wallet-{$order_id}:{$payment_method}:{$mock_intent->object}:{$mock_intent->client_secret}:{$return_url}/";
		}

		$this->assertEquals( 'success', $response['result'] );
		$this->assertMatchesRegularExpression( $expected_redirect_url, $response['redirect'] );
	}

	/**
	 * Provider for `test_process_payment_deferred_intent_with_required_action_for_wallet_returns_valid_response`.
	 *
	 * @return array
	 */
	public function provide_process_payment_deferred_intent_with_required_action_for_wallet_returns_valid_response() {
		return [
			'wechat pay / default amount'  => [
				'payment method' => WC_Stripe_Payment_Methods::WECHAT_PAY,
			],
			'cashapp / default amount'     => [
				'payment method' => WC_Stripe_Payment_Methods::CASHAPP_PAY,
			],
			'cashapp / free'               => [
				'payment method' => WC_Stripe_Payment_Methods::CASHAPP_PAY,
				'free order'     => true,
			],
			'cashapp / free / saved token' => [
				'payment method' => WC_Stripe_Payment_Methods::CASHAPP_PAY,
				'free order'     => true,
				'saved token'    => true,
			],
		];
	}

	/**
	 * Exception handling of the process_payment flow with deferred intent.
	 *
	 * @dataProvider provide_process_payment_deferred_intent_handles_exception
	 */
	public function test_process_payment_deferred_intent_handles_exception( $post_vars ) {
		$payment_intent_id = 'pi_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$currency          = $order->get_currency();
		$order_id          = $order->get_id();

		$mock_intent = (object) [
			'charges' => (object) [
				'data' => [
					(object) [
						'id'       => $order_id,
						'captured' => 'yes',
						'status'   => 'succeeded',
					],
				],
			],
		];

		$_POST = $post_vars;

		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willThrowException( new WC_Stripe_Exception( "It's a trap!" ) );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'update_saved_payment_method' );

		$response = $this->mock_gateway->process_payment( $order_id );

		$this->assertEquals( 'failure', $response['result'] );

		$processed_order = wc_get_order( $order_id );
		$this->assertEquals( OrderStatus::FAILED, $processed_order->get_status() );
	}

	/**
	 * Provider for `test_process_payment_deferred_intent_handles_exception`.
	 */
	public function provide_process_payment_deferred_intent_handles_exception() {
		return [
			'with-payment-method'     => [
				[
					'payment_method'               => 'stripe',
					'wc-stripe-payment-method'     => 'pm_mock',
					'wc-stripe-confirmation-token' => '',
					'wc-stripe-is-deferred-intent' => '1',
				],
			],
			'with-confirmation-token' => [
				[
					'payment_method'               => 'stripe',
					'wc-stripe-payment-method'     => '',
					'wc-stripe-confirmation-token' => 'ctoken_mock',
					'wc-stripe-is-deferred-intent' => '1',
				],
			],
		];
	}

	/**
	 * @dataProvider provide_process_payment_deferred_intent_bails_with_empty_payment_type
	 */
	public function test_process_payment_deferred_intent_bails_with_empty_payment_type( $post_vars ) {
		$payment_intent_id = 'pi_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$currency          = $order->get_currency();
		$order_id          = $order->get_id();

		$mock_intent = (object) [
			'charges' => (object) [
				'data' => [
					(object) [
						'id'       => $order_id,
						'captured' => 'yes',
						'status'   => 'succeeded',
					],
				],
			],
		];

		$_POST = $post_vars;

		$this->mock_gateway->intent_controller
			->expects( $this->never() )
			->method( 'create_and_confirm_payment_intent' );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'update_saved_payment_method' );

		$response = $this->mock_gateway->process_payment( $order_id );

		$this->assertEquals( 'failure', $response['result'] );

		$processed_order = wc_get_order( $order_id );
		$this->assertEquals( OrderStatus::FAILED, $processed_order->get_status() );
	}

	/**
	 * Provider for `test_process_payment_deferred_intent_bails_with_empty_payment_type`.
	 */
	public function provide_process_payment_deferred_intent_bails_with_empty_payment_type() {
		return [
			'with-payment-method'     => [
				[
					'payment_method'               => '',
					'wc-stripe-payment-method'     => 'pm_mock',
					'wc-stripe-confirmation-token' => '',
					'wc-stripe-is-deferred-intent' => '1',
				],
			],
			'with-confirmation-token' => [
				[
					'payment_method'               => '',
					'wc-stripe-payment-method'     => '',
					'wc-stripe-confirmation-token' => 'ctoken_mock',
					'wc-stripe-is-deferred-intent' => '1',
				],
			],
		];
	}

	/**
	 * @dataProvider provide_process_payment_deferred_intent_bails_with_invalid_payment_type
	 */
	public function test_process_payment_deferred_intent_bails_with_invalid_payment_type( $post_vars ) {
		$payment_intent_id = 'pi_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$currency          = $order->get_currency();
		$order_id          = $order->get_id();

		$mock_intent = (object) [
			'charges' => (object) [
				'data' => [
					(object) [
						'id'       => $order_id,
						'captured' => 'yes',
						'status'   => 'succeeded',
					],
				],
			],
		];

		$_POST = $post_vars;

		$this->mock_gateway->intent_controller
			->expects( $this->never() )
			->method( 'create_and_confirm_payment_intent' );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'update_saved_payment_method' );

		$response = $this->mock_gateway->process_payment( $order_id );

		$this->assertEquals( 'failure', $response['result'] );

		$processed_order = wc_get_order( $order_id );
		$this->assertEquals( OrderStatus::FAILED, $processed_order->get_status() );
	}

	/**
	 * Provider for `test_process_payment_deferred_intent_bails_with_invalid_payment_type`.
	 */
	public function provide_process_payment_deferred_intent_bails_with_invalid_payment_type() {
		return [
			'with-payment-method'     => [
				[
					'payment_method'               => 'some_invalid_type',
					'wc-stripe-payment-method'     => 'pm_mock',
					'wc-stripe-confirmation-token' => '',
					'wc-stripe-is-deferred-intent' => '1',
				],
			],
			'with-confirmation-token' => [
				[
					'payment_method'               => 'some_invalid_type',
					'wc-stripe-payment-method'     => '',
					'wc-stripe-confirmation-token' => 'ctoken_mock',
					'wc-stripe-is-deferred-intent' => '1',
				],
			],
		];
	}

	/**
	 * Test basic redirect payment processed correctly.
	 */
	public function test_process_redirect_payment_returns_valid_response() {
		$payment_intent_id = 'pi_mock';
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();

		list( $amount, $description, $metadata, $currency ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['currency']           = $currency;
		$payment_intent_mock['last_payment_error'] = [];
		$payment_intent_mock['payment_method']     = $payment_method_mock;
		$payment_intent_mock['latest_charge']      = 'ch_mock';

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( "payment_intents/$payment_intent_id?expand[]=payment_method" )
			->will(
				$this->returnValue(
					$this->array_to_object( $payment_intent_mock )
				)
			);

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => $payment_method_mock,
		];
		$this->mock_gateway
			->expects( $this->exactly( 3 ) )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $payment_intent_id, false );

		$final_order  = wc_get_order( $order_id );
		$note         = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 2,
			]
		)[1];
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( OrderStatus::PROCESSING, $final_order->get_status() );
		$this->assertEquals( 'Credit / Debit Card', $final_order->get_payment_method_title() );
		$this->assertEquals( $payment_intent_id, $order_helper->get_stripe_intent_id( $final_order ) );
		$this->assertTrue( (bool) $order_helper->get_stripe_upe_redirect_processed( $final_order ) );
		$this->assertMatchesRegularExpression( '/Charge ID: ch_mock/', $note->content );
	}

	/**
	 * Test redirect payment processed only runs once.
	 */
	public function test_process_redirect_payment_only_runs_once() {
		$payment_intent_id = 'pi_mock';
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();

		list( $amount, $description, $metadata, $currency ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['currency']           = $currency;
		$payment_intent_mock['last_payment_error'] = [];
		$payment_intent_mock['payment_method']     = $payment_method_mock;
		$payment_intent_mock['latest_charge']      = 'ch_mock';

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( "payment_intents/$payment_intent_id?expand[]=payment_method" )
			->will(
				$this->returnValue(
					$this->array_to_object( $payment_intent_mock )
				)
			);

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => $payment_method_mock,
		];
		$this->mock_gateway
			->expects( $this->exactly( 3 ) )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $payment_intent_id, false );

		$success_order = wc_get_order( $order_id );
		$note          = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 2,
			]
		)[1];
		$order_helper  = WC_Stripe_Order_Helper::get_instance();

		// assert successful order processing
		$this->assertEquals( OrderStatus::PROCESSING, $success_order->get_status() );
		$this->assertEquals( 'Credit / Debit Card', $success_order->get_payment_method_title() );
		$this->assertEquals( $payment_intent_id, $order_helper->get_stripe_intent_id( $success_order ) );
		$this->assertTrue( (bool) $order_helper->get_stripe_upe_redirect_processed( $success_order ) );
		$this->assertMatchesRegularExpression( '/Charge ID: ch_mock/', $note->content );

		// simulate an order getting marked as failed as if from a webhook
		$order->set_status( OrderStatus::FAILED );
		$order->save();

		// attempt to reprocess the order and confirm status is unchanged
		$this->mock_gateway->process_upe_redirect_payment( $order_id, $payment_intent_id, false );

		$final_order = wc_get_order( $order_id );

		$this->assertEquals( OrderStatus::FAILED, $final_order->get_status() );
	}

	/**
	 * Test locking for process redirect payment.
	 */
	public function test_process_redirect_payment_locks_order() {
		$payment_intent_id = 'pi_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();

		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$order_helper = $this->createPartialMock(
			WC_Stripe_Order_Helper::class,
			[ 'lock_order_payment', 'unlock_order_payment' ]
		);

		$order_helper->expects( $this->once() )
			->method( 'lock_order_payment' )
			->will(
				$this->returnValue( true )
			);

		$order_helper->expects( $this->once() )
			->method( 'unlock_order_payment' );

		WC_Stripe_Order_Helper::set_instance( $order_helper );

		// Expect the process to bail early.
		$this->mock_gateway->expects( $this->never() )
			->method( 'stripe_request' )
			->with( "payment_intents/$payment_intent_id?expand[]=payment_method" );

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $payment_intent_id, false );
	}

	/**
	 * Test checkout flow with setup intents.
	 */
	public function test_checkout_without_payment_uses_setup_intents() {
		$setup_intent_id   = 'seti_mock';
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();

		$order->set_total( 0 );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$setup_intent_mock                   = self::MOCK_CARD_SETUP_INTENT_TEMPLATE;
		$setup_intent_mock['id']             = $setup_intent_id;
		$setup_intent_mock['payment_method'] = $payment_method_mock;
		$setup_intent_mock['latest_charge']  = [];

		$this->mock_gateway->expects( $this->any() )
			->method( 'get_stripe_customer_from_order' )
			->with( wc_get_order( $order_id ) )
			->will(
				$this->returnValue( $this->mock_stripe_customer )
			);
		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( "setup_intents/$setup_intent_id?expand[]=payment_method&expand[]=latest_attempt" )
			->will(
				$this->returnValue(
					$this->array_to_object( $setup_intent_mock )
				)
			);

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $setup_intent_id, true );

		$final_order  = wc_get_order( $order_id );
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( OrderStatus::PROCESSING, $final_order->get_status() );
		$this->assertEquals( $customer_id, $order_helper->get_stripe_customer_id( $final_order ) );
		$this->assertEquals( $payment_method_id, $order_helper->get_stripe_source_id( $final_order ) );
		$this->assertEquals( 'Credit / Debit Card', $final_order->get_payment_method_title() );
	}

	/**
	 * Test checkout flow while saving payment method.
	 */
	public function test_checkout_saves_payment_method_to_order() {
		$payment_intent_id = 'pi_mock';
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();

		list( $amount, $description, $metadata, $currency ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['currency']           = $currency;
		$payment_intent_mock['last_payment_error'] = [];
		$payment_intent_mock['payment_method']     = $payment_method_mock;
		$payment_intent_mock['latest_charge']      = 'ch_mock';

		$this->mock_gateway->expects( $this->any() )
			->method( 'get_stripe_customer_from_order' )
			->with( wc_get_order( $order_id ) )
			->will(
				$this->returnValue( $this->mock_stripe_customer )
			);
		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( "payment_intents/$payment_intent_id?expand[]=payment_method" )
			->will(
				$this->returnValue(
					$this->array_to_object( $payment_intent_mock )
				)
			);

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => $payment_method_mock,
		];
		$this->mock_gateway
			->expects( $this->exactly( 3 ) )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $payment_intent_id, true );

		$final_order  = wc_get_order( $order_id );
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( OrderStatus::PROCESSING, $final_order->get_status() );
		$this->assertEquals( $payment_intent_id, $order_helper->get_stripe_intent_id( $final_order ) );
		$this->assertEquals( $customer_id, $order_helper->get_stripe_customer_id( $final_order ) );
		$this->assertEquals( $payment_method_id, $order_helper->get_stripe_source_id( $final_order ) );
	}

	/**
	 * Test checkout flow while saving payment method with SEPA generated payment method.
	 */
	public function test_checkout_saves_sepa_generated_payment_method_to_order() {
		$payment_intent_id           = 'pi_mock';
		$payment_method_id           = 'pm_mock';
		$generated_payment_method_id = 'pm_gen_mock';
		$customer_id                 = 'cus_mock';
		$order                       = WC_Helper_Order::create_order();
		$order_id                    = $order->get_id();

		list( $amount, $description, $metadata, $currency ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$payment_method_mock             = self::MOCK_SEPA_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']       = $payment_method_id;
		$payment_method_mock['customer'] = $customer_id;

		$generated_payment_method_mock       = $payment_method_mock;
		$generated_payment_method_mock['id'] = $generated_payment_method_id;

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['currency']           = $currency;
		$payment_intent_mock['last_payment_error'] = [];
		$payment_intent_mock['payment_method']     = $payment_method_mock;
		$payment_intent_mock['latest_charge']      = 'ch_mock';

		$this->mock_gateway->expects( $this->any() )
			->method( 'get_stripe_customer_from_order' )
			->with( wc_get_order( $order_id ) )
			->will(
				$this->returnValue( $this->mock_stripe_customer )
			);
		$this->mock_gateway->expects( $this->exactly( 2 ) )
			->method( 'stripe_request' )
			->willReturnOnConsecutiveCalls(
				$this->array_to_object( $payment_intent_mock ),
				$this->array_to_object( $generated_payment_method_mock )
			);

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => [
				'type'                                => WC_Stripe_Payment_Methods::BANCONTACT,
				WC_Stripe_Payment_Methods::BANCONTACT => [
					'generated_sepa_debit' => $generated_payment_method_id,
				],
			],
		];
		$this->mock_gateway
			->expects( $this->exactly( 3 ) )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $payment_intent_id, true );

		$final_order  = wc_get_order( $order_id );
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( OrderStatus::PROCESSING, $final_order->get_status() );
		$this->assertEquals( $payment_intent_id, $order_helper->get_stripe_intent_id( $final_order ) );
		$this->assertEquals( $customer_id, $order_helper->get_stripe_customer_id( $final_order ) );
		$this->assertEquals( $generated_payment_method_id, $order_helper->get_stripe_source_id( $final_order ) );
	}

	/**
	 * Test checkout flow while saving payment method with SEPA generated payment method AND setup intents.
	 */
	public function test_setup_intent_checkout_saves_sepa_generated_payment_method_to_order() {
		$setup_intent_id             = 'seti_mock';
		$payment_method_id           = 'pm_mock';
		$generated_payment_method_id = 'pm_gen_mock';
		$customer_id                 = 'cus_mock';
		$order                       = WC_Helper_Order::create_order();
		$order_id                    = $order->get_id();

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );

		$order->set_total( 0 );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$payment_method_mock             = self::MOCK_SEPA_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']       = $payment_method_id;
		$payment_method_mock['customer'] = $customer_id;

		$generated_payment_method_mock       = $payment_method_mock;
		$generated_payment_method_mock['id'] = $generated_payment_method_id;

		$setup_intent_mock                   = self::MOCK_CARD_SETUP_INTENT_TEMPLATE;
		$setup_intent_mock['id']             = $setup_intent_id;
		$setup_intent_mock['payment_method'] = $payment_method_mock;
		$setup_intent_mock['latest_charge']  = [];
		$setup_intent_mock['latest_attempt'] = [
			'payment_method_details' => [
				'type'                                => WC_Stripe_Payment_Methods::BANCONTACT,
				WC_Stripe_Payment_Methods::BANCONTACT => [
					'generated_sepa_debit' => $generated_payment_method_id,
				],
			],
		];

		$this->mock_gateway->expects( $this->any() )
			->method( 'get_stripe_customer_from_order' )
			->with( wc_get_order( $order_id ) )
			->will(
				$this->returnValue( $this->mock_stripe_customer )
			);
		$this->mock_gateway->expects( $this->exactly( 2 ) )
			->method( 'stripe_request' )
			->willReturnOnConsecutiveCalls(
				$this->array_to_object( $setup_intent_mock ),
				$this->array_to_object( $generated_payment_method_mock )
			);

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $setup_intent_id, true );

		$final_order  = wc_get_order( $order_id );
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( OrderStatus::PROCESSING, $final_order->get_status() );
		$this->assertEquals( $customer_id, $order_helper->get_stripe_customer_id( $final_order ) );
		$this->assertEquals( $generated_payment_method_id, $order_helper->get_stripe_source_id( $final_order ) );
	}

	/**
	 * Test errors on intent throw exceptions.
	 */
	public function test_intent_error_throws_exception() {
		$payment_intent_id = 'pi_mock';
		$setup_intent_id   = 'seti_mock';
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['last_payment_error'] = [ 'message' => 'Uh-oh, something went wrong...' ];

		$setup_intent_mock                     = self::MOCK_CARD_SETUP_INTENT_TEMPLATE;
		$setup_intent_mock['id']               = $setup_intent_id;
		$setup_intent_mock['last_setup_error'] = [ 'message' => 'Uh-oh, something went wrong...' ];

		$this->mock_gateway->expects( $this->exactly( 2 ) )
			->method( 'stripe_request' )
			->willReturnOnConsecutiveCalls(
				$this->array_to_object( $payment_intent_mock ),
				$this->array_to_object( $setup_intent_mock )
			);

		$exception = null;
		try {
			$this->mock_gateway->process_order_for_confirmed_intent( $order, $payment_intent_id, false );
		} catch ( WC_Stripe_Exception $e ) {
			// Test exception thrown.
			$exception = $e;
		}
		$this->assertMatchesRegularExpression( '/not able to process this payment./', $exception->getMessage() );

		$exception = null;
		$order->set_total( 0 );
		$order->save();
		try {
			$this->mock_gateway->process_order_for_confirmed_intent( $order, $setup_intent_id, false );
		} catch ( WC_Stripe_Exception $e ) {
			// Test exception thrown.
			$exception = $e;
		}
		$this->assertMatchesRegularExpression( '/not able to process this payment./', $exception->getMessage() );
	}

	/**
	 * Test order status corresponds with charge status.
	 */
	public function test_process_response_updates_order_by_charge_status() {
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$charge_mock                           = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE['charges']['data'][0];
		$charge_mock['payment_method_details'] = $payment_method_mock;

		// Test no charge captured.
		$charge_mock['captured'] = false;
		$charge_mock['id']       = 'ch_mock_1';
		$this->mock_gateway->process_response( $this->array_to_object( $charge_mock ), wc_get_order( $order_id ) );
		$test_order = wc_get_order( $order_id );

		$this->assertEquals( 'no', $test_order->get_meta( '_stripe_charge_captured', true ) );
		$this->assertEquals( $charge_mock['id'], $test_order->get_transaction_id() );
		$this->assertEquals( OrderStatus::ON_HOLD, $test_order->get_status() );

		// Test charge succeeds.
		$charge_mock['captured'] = true;
		$charge_mock['id']       = 'ch_mock_2';
		$this->mock_gateway->process_response( $this->array_to_object( $charge_mock ), wc_get_order( $order_id ) );
		$test_order = wc_get_order( $order_id );

		$this->assertEquals( 'yes', $test_order->get_meta( '_stripe_charge_captured', true ) );
		$this->assertEquals( OrderStatus::PROCESSING, $test_order->get_status() );

		// Test charge pending.
		$charge_mock['status'] = 'pending';
		$charge_mock['id']     = 'ch_mock_3';
		$this->mock_gateway->process_response( $this->array_to_object( $charge_mock ), wc_get_order( $order_id ) );
		$test_order = wc_get_order( $order_id );

		$this->assertEquals( 'yes', $test_order->get_meta( '_stripe_charge_captured', true ) );
		$this->assertEquals( $charge_mock['id'], $test_order->get_transaction_id() );
		$this->assertEquals( OrderStatus::ON_HOLD, $test_order->get_status() );

		// Test charge failed.
		$charge_mock['status'] = 'failed';
		$charge_mock['id']     = 'ch_mock_4';
		$exception             = null;
		try {
			$this->mock_gateway->process_response( $this->array_to_object( $charge_mock ), wc_get_order( $order_id ) );
		} catch ( WC_Stripe_Exception $e ) {
			// Test that exception is thrown.
			$exception = $e;
		}

		$note = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];
		$this->assertMatchesRegularExpression( '/Payment processing failed./', $note->content );
		$this->assertMatchesRegularExpression( '/Payment processing failed./', $exception->getLocalizedMessage() );
	}

	/**
	 * Test that the wc_gateway_stripe_process_payment_charge action is triggered when process_response() is called for synchronous payment paths.
	 */
	public function test_process_response_triggers_wc_gateway_stripe_process_payment_charge_action() {
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$charge_mock                           = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE['charges']['data'][0];
		$charge_mock['payment_method_details'] = $payment_method_mock;
		$charge_mock['captured']               = true;
		$charge_mock['status']                 = 'succeeded';
		$charge_mock['id']                     = 'ch_mock_success';

		$mock_action_process_payment = new MockAction();
		add_action(
			'wc_gateway_stripe_process_payment_charge',
			[ &$mock_action_process_payment, 'action' ]
		);

		$this->mock_gateway->process_response( $this->array_to_object( $charge_mock ), wc_get_order( $order_id ) );

		$final_order = wc_get_order( $order_id );

		// Test the action was called only once.
		$this->assertEquals( 1, $mock_action_process_payment->get_call_count() );

		// Test the order was processed successfully.
		$this->assertEquals( OrderStatus::PROCESSING, $final_order->get_status() );
		$this->assertEquals( 'yes', $final_order->get_meta( '_stripe_charge_captured', true ) );
		$this->assertEquals( $charge_mock['id'], $final_order->get_transaction_id() );
	}

	/**
	 * TESTS FOR SAVED PAYMENTS.
	 */

	/**
	 * Test basic checkout with saved payment method.
	 */
	public function test_process_payment_with_saved_method_returns_valid_response() {
		$token = $this->set_postvars_for_saved_payment_method();

		// Set the appropriate POST flag to trigger a deferred intent request.
		$_POST['wc-stripe-is-deferred-intent'] = '1';
		$_POST['payment_method']               = 'stripe';
		$_POST['wc-stripe-payment-method']     = 'pm_mock';

		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();
		$payment_intent_id = 'pi_mock';
		$payment_method_id = $token->get_token();
		$customer_id       = 'cus_mock';

		list( $amount ) = $this->get_order_details( $order );

		$payment_intent_mock = (object) array_merge(
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE,
			[
				'id'             => $payment_intent_id,
				'amount'         => $amount,
				'payment_method' => $payment_method_id,
				'charges'        => (object) [
					'data' => [
						(object) [
							'id'       => 'ch_mock',
							'captured' => true,
							'status'   => 'succeeded',
						],
					],
				],
			]
		);

		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willReturn( $payment_intent_mock );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'update_saved_payment_method' )
			->with(
				$this->equalTo( $payment_method_id ),
				$this->callback(
					function ( $passed_order ) use ( $order ) {
						return $order->get_id() === $passed_order->get_id();
					}
				)
			);

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => $payment_intent_mock,
		];
		$this->mock_gateway
			->expects( $this->exactly( 2 ) )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

		$response     = $this->mock_gateway->process_payment( $order_id );
		$final_order  = wc_get_order( $order_id );
		$note         = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( 'success', $response['result'] );
		$this->assertEquals( OrderStatus::PROCESSING, $final_order->get_status() );
		$this->assertEquals( $payment_intent_id, $order_helper->get_stripe_intent_id( $final_order ) );
		$this->assertEquals( $customer_id, $order_helper->get_stripe_customer_id( $final_order ) );
		$this->assertEquals( $payment_method_id, $order_helper->get_stripe_source_id( $final_order ) );
		$this->assertMatchesRegularExpression( '/Charge ID: ch_mock/', $note->content );
	}

	/**
	 * Test SCA 3DS flow with saved payment method.
	 */
	public function test_sca_checkout_with_saved_payment_method_redirects_client() {
		$token = $this->set_postvars_for_saved_payment_method();

		// Set the appropriate POST flag to trigger a deferred intent request.
		$_POST['wc-stripe-is-deferred-intent'] = '1';
		$_POST['payment_method']               = 'stripe';
		$_POST['wc-stripe-payment-method']     = 'pm_mock';

		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();
		$payment_intent_id = 'pi_mock';
		$payment_method_id = $token->get_token();
		$customer_id       = 'cus_mock';

		list( $amount ) = $this->get_order_details( $order );

		$payment_intent_mock = (object) array_merge(
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE,
			[
				'id'             => $payment_intent_id,
				'amount'         => $amount,
				'payment_method' => $payment_method_id,
				'status'         => WC_Stripe_Intent_Status::REQUIRES_ACTION,
				'charges'        => (object) [
					'data' => [
						(object) [
							'id'       => 'ch_mock',
							'captured' => true,
							'status'   => 'succeeded',
						],
					],
				],
			]
		);

		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willReturn( $payment_intent_mock );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'update_saved_payment_method' )
			->with(
				$this->equalTo( $payment_method_id ),
				$this->callback(
					function ( $passed_order ) use ( $order ) {
						return $order->get_id() === $passed_order->get_id();
					}
				)
			);

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => $payment_intent_mock,
		];
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

		$response      = $this->mock_gateway->process_payment( $order_id );
		$final_order   = wc_get_order( $order_id );
		$client_secret = $payment_intent_mock->client_secret;
		$order_helper  = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( 'success', $response['result'] );
		$this->assertEquals( OrderStatus::PENDING, $final_order->get_status() ); // Order status should be pending until 3DS is completed.
		$this->assertEquals( $payment_intent_id, $order_helper->get_stripe_intent_id( $final_order ) );
		$this->assertEquals( $customer_id, $order_helper->get_stripe_customer_id( $final_order ) );
		$this->assertEquals( $payment_method_id, $order_helper->get_stripe_source_id( $final_order ) );
		$this->assertMatchesRegularExpression( "/#wc-stripe-confirm-pi:$order_id:$client_secret/", $response['redirect'] );
	}

	/**
	 * Test error state with fatal test during checkout with saved payment method.
	 */
	public function test_checkout_with_saved_payment_method_non_retryable_error_throws_exception() {
		$token = $this->set_postvars_for_saved_payment_method();

		// Set the appropriate POST flag to trigger a deferred intent request.
		$_POST['wc-stripe-is-deferred-intent'] = '1';
		$_POST['payment_method']               = 'stripe';
		$_POST['wc-stripe-payment-method']     = 'pm_mock';

		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();
		$payment_intent_id = 'pi_mock';
		$payment_method_id = $token->get_token();
		$customer_id       = 'cus_mock';

		$failed_payment_intent_mock = (object) [
			'error' => (object) [
				'type'           => 'completely_fatal_error',
				'code'           => '666',
				'message'        => 'Oh my god',
				'payment_intent' => (object) [
					'id'     => $payment_intent_id,
					'object' => 'payment_intent',
				],
			],
		];

		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willReturn( $failed_payment_intent_mock );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'update_saved_payment_method' )
			->with(
				$this->equalTo( $payment_method_id ),
				$this->callback(
					function ( $passed_order ) use ( $order ) {
						return $order->get_id() === $passed_order->get_id();
					}
				)
			);

		$response     = $this->mock_gateway->process_payment( $order_id );
		$final_order  = wc_get_order( $order_id );
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( 'failure', $response['result'] );
		$this->assertEquals( OrderStatus::FAILED, $final_order->get_status() );
		$this->assertEquals( $payment_intent_id, $order_helper->get_stripe_intent_id( $final_order ) );
		$this->assertEquals( $payment_method_id, $order_helper->get_stripe_source_id( $final_order ) );
	}

	/**
	 * Tests retryable error during checkout using saved payment method.
	 */
	public function test_checkout_with_saved_payment_method_retries_error_when_possible() {
		$token = $this->set_postvars_for_saved_payment_method();

		// Set the appropriate POST flag to trigger a deferred intent request.
		$_POST['wc-stripe-is-deferred-intent'] = '1';
		$_POST['payment_method']               = 'stripe';
		$_POST['wc-stripe-payment-method']     = 'pm_mock';

		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();
		$payment_intent_id = 'pi_mock';
		$payment_method_id = $token->get_token();
		$customer_id       = 'cus_mock';

		list( $amount ) = $this->get_order_details( $order );

		$successful_payment_intent_mock = (object) array_merge(
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE,
			[
				'id'             => $payment_intent_id,
				'amount'         => $amount,
				'payment_method' => $payment_method_id,
				'charges'        => (object) [
					'data' => [
						(object) [
							'id'       => 'ch_mock',
							'captured' => true,
							'status'   => 'succeeded',
						],
					],
				],
			]
		);

		$failed_payment_intent_mock = (object) [
			'error' => (object) [
				'type'           => 'api_connection_error',
				'code'           => '501',
				'message'        => 'Owie server hurty',
				'payment_intent' => (object) [
					'id'     => $payment_intent_id,
					'object' => 'payment_intent',
				],
			],
		];

		$this->mock_gateway->intent_controller
			->expects( $this->exactly( 3 ) )
			->method( 'create_and_confirm_payment_intent' )
			->willReturnOnConsecutiveCalls(
				$failed_payment_intent_mock,
				$failed_payment_intent_mock,
				$successful_payment_intent_mock
			);

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'update_saved_payment_method' )
			->with(
				$this->equalTo( $payment_method_id ),
				$this->callback(
					function ( $passed_order ) use ( $order ) {
						return $order->get_id() === $passed_order->get_id();
					}
				)
			);

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => $failed_payment_intent_mock,
		];
		$this->mock_gateway
			->expects( $this->exactly( 4 ) )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

		$response     = $this->mock_gateway->process_payment( $order_id );
		$final_order  = wc_get_order( $order_id );
		$note         = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( 'success', $response['result'] );
		$this->assertEquals( OrderStatus::PROCESSING, $final_order->get_status() );
		$this->assertEquals( $payment_intent_id, $order_helper->get_stripe_intent_id( $final_order ) );
		$this->assertEquals( $customer_id, $order_helper->get_stripe_customer_id( $final_order ) );
		$this->assertEquals( $payment_method_id, $order_helper->get_stripe_source_id( $final_order ) );
		$this->assertMatchesRegularExpression( '/Charge ID: ch_mock/', $note->content );
	}

	/**
	 * Tests that retryable error fails after 6 attempts.
	 */
	public function test_checkout_with_saved_payment_method_fails_after_six_attempts() {
		$token = $this->set_postvars_for_saved_payment_method();

		// Set the appropriate POST flag to trigger a deferred intent request.
		$_POST['wc-stripe-is-deferred-intent'] = '1';
		$_POST['payment_method']               = 'stripe';
		$_POST['wc-stripe-payment-method']     = 'pm_mock';

		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();
		$payment_intent_id = 'pi_mock';
		$payment_method_id = $token->get_token();
		$customer_id       = 'cus_mock';

		list( $amount ) = $this->get_order_details( $order );

		$successful_payment_intent_mock = (object) array_merge(
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE,
			[
				'id'             => $payment_intent_id,
				'amount'         => $amount,
				'payment_method' => $payment_method_id,
				'charges'        => (object) [
					'data' => [
						(object) [
							'id'       => 'ch_mock',
							'captured' => true,
							'status'   => 'succeeded',
						],
					],
				],
			]
		);

		$failed_payment_intent_mock = (object) [
			'error' => (object) [
				'type'           => 'invalid_request_error',
				'code'           => '404',
				'message'        => 'No such customer',
				'payment_intent' => (object) [
					'id'     => $payment_intent_id,
					'object' => 'payment_intent',
				],
			],
		];

		$this->mock_gateway->intent_controller
			->expects( $this->exactly( 6 ) )
			->method( 'create_and_confirm_payment_intent' )
			->willReturnOnConsecutiveCalls(
				$failed_payment_intent_mock,
				$failed_payment_intent_mock,
				$failed_payment_intent_mock,
				$failed_payment_intent_mock,
				$failed_payment_intent_mock,
				$failed_payment_intent_mock
			);

		$this->mock_gateway
			->expects( $this->any() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'update_saved_payment_method' )
			->with(
				$this->equalTo( $payment_method_id ),
				$this->callback(
					function ( $passed_order ) use ( $order ) {
						return $order->get_id() === $passed_order->get_id();
					}
				)
			);

		$response    = $this->mock_gateway->process_payment( $order_id );
		$final_order = wc_get_order( $order_id );

		$this->assertEquals( 'failure', $response['result'] );
		$this->assertEquals( OrderStatus::FAILED, $final_order->get_status() );
		$this->assertEquals( '', WC_Stripe_Order_Helper::get_instance()->get_stripe_customer_id( $final_order ) );
	}

	/**
	 * TESTS FOR SUBSCRIPTIONS.
	 */

	/**
	 * Test successful subscription renewal.
	 */
	public function test_subscription_renewal_is_successful() {
		$this->set_postvars_for_saved_payment_method();

		$payment_intent_id = 'pi_mock';
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();
		$prepared_source   = (object) [
			'token_id'       => false,
			'customer'       => $customer_id,
			'source'         => $payment_method_id,
			'source_object'  => (object) [],
			'payment_method' => null,
		];

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->update_meta_data( '_stripe_lock_payment', ( time() + MINUTE_IN_SECONDS ) ); // To assist with comparing expected order objects, set an existing lock.
		$order->save();

		$order = wc_get_order( $order_id );

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['last_payment_error'] = [];
		$payment_intent_mock['payment_method']     = $payment_method_mock;
		$payment_intent_mock['latest_charge']      = 'ch_mock';

		// Arrange: Make sure to check that an action we care about was called
		// by hooking into it.
		$mock_action_process_payment = new MockAction();
		add_action(
			'wc_gateway_stripe_process_payment_charge',
			[ &$mock_action_process_payment, 'action' ]
		);

		$this->mock_gateway->expects( $this->any() )
			->method( 'prepare_order_source' )
			->will(
				$this->returnValue( $prepared_source )
			);

		$this->mock_gateway->expects( $this->once() )
			->method( 'create_and_confirm_intent_for_off_session' )
			->with(
				$order,
				$prepared_source,
				$amount
			)
			->will(
				$this->returnValue(
					$this->array_to_object( $payment_intent_mock )
				)
			);

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => $payment_method_mock,
		];
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

		$this->mock_gateway->process_subscription_payment( $amount, $order, false, false );

		$final_order = wc_get_order( $order_id );
		$note        = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];

		$this->assertEquals( OrderStatus::PROCESSING, $final_order->get_status() );
		$this->assertMatchesRegularExpression( '/Charge ID: ch_mock/', $note->content );
		// Assert: Our hook was called once.
		$this->assertEquals( 1, $mock_action_process_payment->get_call_count() );
		// Assert: Only our hook was called.
		$this->assertEquals( [ 'wc_gateway_stripe_process_payment_charge' ], $mock_action_process_payment->get_tags() );
	}

	/**
	 * Tests subscription renewal when authorization on payment method is required.
	 */
	public function test_subscription_renewal_checks_payment_method_authorization() {
		$this->set_postvars_for_saved_payment_method();

		$payment_intent_id = 'pi_mock';
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();
		$prepared_source   = (object) [
			'token_id'       => false,
			'customer'       => $customer_id,
			'source'         => $payment_method_id,
			'source_object'  => (object) [],
			'payment_method' => null,
		];

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->update_meta_data( '_stripe_lock_payment', ( time() + MINUTE_IN_SECONDS ) ); // To assist with comparing expected order objects, set an existing lock.
		$order->save();

		$order = wc_get_order( $order_id );

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['last_payment_error'] = [ 'message' => 'Transaction requires authentication.' ];
		$payment_intent_mock['payment_method']     = $payment_method_mock;
		$payment_intent_mock['last_charge']        = 'ch_mock';

		$error_response = [
			'error' => [
				'code'           => 'authentication_required',
				'message'        => 'Transaction requires authentication.',
				'payment_intent' => $payment_intent_mock,
			],
		];

		// Arrange: Make sure to check that an action we care about was called
		// by hooking into it.
		$mock_action_process_payment = new MockAction();
		add_action(
			'wc_gateway_stripe_process_payment_authentication_required',
			[ &$mock_action_process_payment, 'action' ]
		);

		$this->mock_gateway->expects( $this->any() )
			->method( 'prepare_order_source' )
			->will(
				$this->returnValue( $prepared_source )
			);

		$this->mock_gateway->expects( $this->once() )
			->method( 'create_and_confirm_intent_for_off_session' )
			->with(
				$order,
				$prepared_source,
				$amount
			)
			->will(
				$this->returnValue(
					$this->array_to_object( $error_response )
				)
			);

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => $payment_intent_mock,
		];
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

		$this->mock_gateway->process_subscription_payment( $amount, $order, false, false );

		$final_order = wc_get_order( $order_id );
		$note        = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];

		$this->assertEquals( OrderStatus::FAILED, $final_order->get_status() );
		$this->assertEquals( 'ch_mock', $final_order->get_transaction_id() );
		$this->assertMatchesRegularExpression( '/pending/i', $note->content );
		// Assert: Our hook was called once.
		$this->assertEquals( 1, $mock_action_process_payment->get_call_count() );
		// Assert: Only our hook was called.
		$this->assertEquals( [ 'wc_gateway_stripe_process_payment_authentication_required' ], $mock_action_process_payment->get_tags() );
	}

	/**
	 * TESTS FOR PRE-ORDERS.
	 */

	/**
	 * Pre-order payment is successful.
	 */
	public function test_pre_order_payment_is_successful() {
		$payment_intent_id = 'pi_mock';
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();

		list( $amount, $description, $metadata, $currency ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['currency']           = $currency;
		$payment_intent_mock['last_payment_error'] = [];
		$payment_intent_mock['payment_method']     = $payment_method_mock;
		$payment_intent_mock['latest_charge']      = 'ch_mock';

		// Mock order has pre-order product.
		$this->mock_gateway->expects( $this->any() )
			->method( 'has_pre_order' )
			->with( $order_id )
			->will( $this->returnValue( true ) );

		$this->mock_gateway->expects( $this->once() )
			->method( 'is_pre_order_item_in_cart' )
			->will( $this->returnValue( true ) );

		$this->mock_gateway->expects( $this->once() )
			->method( 'is_pre_order_product_charged_upfront' )
			->will( $this->returnValue( true ) );

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( "payment_intents/$payment_intent_id?expand[]=payment_method" )
			->will(
				$this->returnValue(
					$this->array_to_object( $payment_intent_mock )
				)
			);
		$this->mock_gateway->expects( $this->any() )
			->method( 'get_stripe_customer_from_order' )
			->with( wc_get_order( $order_id ) )
			->will(
				$this->returnValue( $this->mock_stripe_customer )
			);

		$this->mock_gateway->expects( $this->any() )
			->method( 'has_pre_order_charged_upon_release' )
			->with( wc_get_order( $order_id ) )
			->will( $this->returnValue( true ) );

		$this->mock_gateway->expects( $this->once() )
			->method( 'mark_order_as_pre_ordered' );

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => $payment_method_mock,
		];
		$this->mock_gateway
			->expects( $this->exactly( 2 ) )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $payment_intent_id, false );

		$final_order  = wc_get_order( $order_id );
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( 'Credit / Debit Card', $final_order->get_payment_method_title() );
		$this->assertEquals( $payment_method_id, $order_helper->get_stripe_source_id( $final_order ) );
		$this->assertEquals( $customer_id, $order_helper->get_stripe_customer_id( $final_order ) );
		$this->assertEquals( $payment_intent_id, $order_helper->get_stripe_intent_id( $final_order ) );
		$this->assertTrue( (bool) $order_helper->get_stripe_upe_redirect_processed( $final_order ) );
	}

	/**
	 * Pre-order with no required payment uses setup intents.
	 */
	public function test_pre_order_without_payment_uses_setup_intents() {
		$setup_intent_id   = 'seti_mock';
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();

		$order->set_total( 0 );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$setup_intent_mock                   = self::MOCK_CARD_SETUP_INTENT_TEMPLATE;
		$setup_intent_mock['id']             = $setup_intent_id;
		$setup_intent_mock['payment_method'] = $payment_method_mock;
		$setup_intent_mock['latest_charge']  = [];

		$this->mock_gateway->expects( $this->any() )
			->method( 'get_stripe_customer_from_order' )
			->with( wc_get_order( $order_id ) )
			->will(
				$this->returnValue( $this->mock_stripe_customer )
			);

		// Mock order has pre-order product.
		$this->mock_gateway->expects( $this->once() )
			->method( 'has_pre_order' )
			->will( $this->returnValue( true ) );

		$this->mock_gateway->expects( $this->once() )
			->method( 'is_pre_order_item_in_cart' )
			->will( $this->returnValue( true ) );

		$this->mock_gateway->expects( $this->once() )
			->method( 'is_pre_order_product_charged_upfront' )
			->will( $this->returnValue( false ) );

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( "setup_intents/$setup_intent_id?expand[]=payment_method&expand[]=latest_attempt" )
			->will(
				$this->returnValue(
					$this->array_to_object( $setup_intent_mock )
				)
			);

		$this->mock_gateway->expects( $this->once() )
			->method( 'mark_order_as_pre_ordered' );

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $setup_intent_id, true );

		$final_order  = wc_get_order( $order_id );
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( $payment_method_id, $order_helper->get_stripe_source_id( $final_order ) );
		$this->assertEquals( $customer_id, $order_helper->get_stripe_customer_id( $final_order ) );
		$this->assertTrue( (bool) $order_helper->get_stripe_upe_redirect_processed( $final_order ) );
	}

	/**
	 * Test if `display_order_fee` and `display_order_payout` are called when viewing an order on the admin panel.
	 *
	 * @return void
	 */
	public function test_fees_actions_are_called_on_order_admin_page() {
		$order = WC_Helper_Order::create_order();

		$this->mock_gateway->expects( $this->once() )
			->method( 'display_order_fee' )
			->with( $order->get_id() );

		$this->mock_gateway->expects( $this->once() )
			->method( 'display_order_payout' )
			->with( $order->get_id() );

		do_action( 'woocommerce_admin_order_totals_after_total', $order->get_id() );
	}
	/**
	 * Test for `process_payment` when the order has an existing payment intent attached.
	 *
	 * @return void
	 * @throws Exception If test fails.
	 */
	public function test_process_payment_deferred_intent_with_existing_intent() {
		$customer_id = 'cus_mock';
		$order       = WC_Helper_Order::create_order();
		$currency    = $order->get_currency();
		$order_id    = $order->get_id();

		list( $amount ) = $this->get_order_details( $order );

		$mock_intent = (object) wp_parse_args(
			[
				'payment_method'       => 'pm_mock',
				'payment_method_types' => [ WC_Stripe_Payment_Methods::CARD ],
				'charges'              => (object) [
					'data' => [
						(object) [
							'id'       => $order_id,
							'captured' => 'yes',
							'status'   => 'succeeded',
						],
					],
				],
				'status'               => WC_Stripe_Intent_Status::REQUIRES_ACTION,
				'amount'               => $amount,
			],
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE
		);

		$mock_payment_method = (object) self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;

		// Set the appropriate POST flag to trigger a deferred intent request.
		$_POST = [
			'payment_method'               => 'stripe',
			'wc-stripe-payment-method'     => 'pm_mock',
			'wc-stripe-is-deferred-intent' => '1',
		];

		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->exactly( 2 ) )
			->method( 'get_intent_from_order' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->exactly( 2 ) )
			->method( 'stripe_request' )
			->withConsecutive(
				[ 'payment_methods/pm_mock' ],
				[ 'payment_intents/' . $mock_intent->id ]
			)
			->willReturnOnConsecutiveCalls(
				$mock_payment_method,
				$mock_intent
			);

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$response = $this->mock_gateway->process_payment( $order_id );

		$this->assertEquals( 'success', $response['result'] );
		$this->assertMatchesRegularExpression( "/#wc-stripe-confirm-pi:{$order_id}:{$mock_intent->client_secret}/", $response['redirect'] );
	}


	/**
	 * Test that a successful payment intent is reused instead of creating a new one.
	 * This prevents duplicate charges when the shopper retries a payment after
	 * a successful charge but failed order completion.
	 *
	 * @return void
	 * @throws Exception If test fails.
	 */
	public function test_process_payment_reuses_successful_payment_intent() {
		$customer_id = 'cus_mock';
		$order       = WC_Helper_Order::create_order();
		$order_id    = $order->get_id();

		$mock_intent = (object) wp_parse_args(
			[
				'id'                   => 'pi_mock',
				'payment_method'       => 'pm_mock',
				'payment_method_types' => [ WC_Stripe_Payment_Methods::CARD ],
				'charges'              => (object) [
					'data' => [
						(object) [
							'id'       => $order_id,
							'captured' => 'yes',
							'status'   => 'succeeded',
						],
					],
				],
				'status'               => WC_Stripe_Intent_Status::SUCCEEDED,
			],
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE
		);

		$mock_payment_method = (object) self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;

		// Set the appropriate POST flag to trigger a deferred intent request.
		$_POST = [
			'payment_method'               => 'stripe',
			'wc-stripe-payment-method'     => 'pm_mock',
			'wc-stripe-is-deferred-intent' => '1',
		];

		// Mock that we find an existing successful intent on the order
		$this->mock_gateway
			->expects( $this->exactly( 1 ) )
			->method( 'get_intent_from_order' )
			->willReturn( $mock_intent );

		// Mock both the payment method retrieval and payment intent retrieval
		$this->mock_gateway
			->expects( $this->exactly( 2 ) )
			->method( 'stripe_request' )
			->withConsecutive(
				[ 'payment_methods/pm_mock' ],
				[ "payment_intents/{$mock_intent->id}", null, null, 'POST' ]
			)
			->willReturnOnConsecutiveCalls(
				$mock_payment_method,
				$mock_intent
			);

		// We should never try to create a new intent since we have a successful one
		$this->mock_gateway->intent_controller
			->expects( $this->never() )
			->method( 'create_and_confirm_payment_intent' );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$response = $this->mock_gateway->process_payment( $order_id );

		// Verify the response indicates success
		$this->assertEquals( 'success', $response['result'] );
	}

	/**
	 * Test that a failed payment intent is not reused and a new one is created instead.
	 *
	 * @return void
	 * @throws Exception If test fails.
	 */
	public function test_process_payment_creates_new_intent_when_existing_intent_failed() {
		$customer_id = 'cus_mock';
		$order       = WC_Helper_Order::create_order();
		$order_id    = $order->get_id();

		$mock_payment_method = (object) self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		list( $amount )      = $this->get_order_details( $order );

		// Create a mock failed payment intent that would be attached to the order
		$mock_failed_intent = (object) wp_parse_args(
			[
				'id'                   => 'pi_mock_failed',
				'payment_method'       => 'pm_mock',
				'status'               => WC_Stripe_Intent_Status::CANCELED,
				'payment_method_types' => [ WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID ],
				'charges'              => (object) [
					'data' => [],
				],
				'amount'               => $amount,
			],
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE
		);

		// Create a mock successful payment intent that will be created
		$mock_success_intent = (object) wp_parse_args(
			[
				'id'                   => 'pi_mock_new',
				'payment_method'       => 'pm_mock',
				'status'               => WC_Stripe_Intent_Status::SUCCEEDED,
				'payment_method_types' => [ WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID ],
				'charges'              => (object) [
					'data' => [
						(object) [
							'id'       => 'ch_mock',
							'captured' => true,
							'status'   => 'succeeded',
						],
					],
				],
			],
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE
		);

		// Set the appropriate POST flag to trigger a deferred intent request
		$_POST = [
			'payment_method'               => 'stripe',
			'wc-stripe-payment-method'     => 'pm_mock',
			'wc-stripe-is-deferred-intent' => '1',
		];

		// Save the failed intent ID to the order
		WC_Stripe_Order_Helper::get_instance()->update_stripe_intent_id( $order, $mock_failed_intent->id );
		$order->save();

		// Mock that we find an existing failed intent on the order
		$this->mock_gateway
			->expects( $this->exactly( 2 ) )
			->method( 'get_intent_from_order' )
			->willReturn( $mock_failed_intent );

		// Mock both the payment method retrieval and payment intent retrieval
		$this->mock_gateway
			->expects( $this->exactly( 2 ) )
			->method( 'stripe_request' )
			->withConsecutive(
				[ 'payment_methods/pm_mock' ],
				[ "payment_intents/{$mock_failed_intent->id}", null, null, 'POST' ]
			)
			->willReturnOnConsecutiveCalls(
				$mock_payment_method,
				$mock_failed_intent
			);

		// We should create a new intent since the existing one failed
		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willReturn( $mock_success_intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$response = $this->mock_gateway->process_payment( $order_id );

		// Verify the response indicates success
		$this->assertEquals( 'success', $response['result'] );
	}

	/**
	 * Test for `process_payment` with a co-branded credit card and preferred brand set.
	 *
	 * @return void
	 * @throws Exception If test fails.
	 */
	public function test_process_payment_deferred_intent_with_co_branded_cc_and_preferred_brand() {
		if ( ! WC_Stripe_Co_Branded_CC_Compatibility::is_wc_supported() ) {
			$this->markTestSkipped( 'Test requires WooCommerce ' . WC_Stripe_Co_Branded_CC_Compatibility::MIN_WC_VERSION . ' or newer.' );
		}

		$token = $this->set_postvars_for_saved_payment_method();

		// Set the appropriate POST flag to trigger a deferred intent request.
		$_POST['wc-stripe-is-deferred-intent'] = '1';
		$_POST['payment_method']               = 'stripe';
		$_POST['wc-stripe-payment-method']     = 'pm_mock';

		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();
		$payment_intent_id = 'pi_mock';
		$payment_method_id = $token->get_token();
		$customer_id       = 'cus_mock';

		list( $amount ) = $this->get_order_details( $order );

		$payment_intent_mock = (object) array_merge(
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE,
			[
				'id'             => $payment_intent_id,
				'amount'         => $amount,
				'payment_method' => $payment_method_id,
				'charges'        => (object) [
					'data' => [
						(object) [
							'id'       => 'ch_mock',
							'captured' => true,
							'status'   => 'succeeded',
						],
					],
				],
			]
		);

		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willReturn( $payment_intent_mock );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$charge = [
			'id'       => 'ch_mock',
			'captured' => true,
			'status'   => 'succeeded',
		];
		$this->mock_gateway
			->expects( $this->exactly( 2 ) )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'update_saved_payment_method' )
			->with(
				$this->equalTo( $payment_method_id ),
				$this->callback(
					function ( $passed_order ) use ( $order ) {
						return $order->get_id() === $passed_order->get_id();
					}
				)
			);

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'stripe_request' )
			->with(
				"payment_methods/$payment_method_id",
			)
			->will(
				$this->returnValue(
					$this->array_to_object( self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE )
				)
			);

		$response    = $this->mock_gateway->process_payment( $order_id );
		$final_order = wc_get_order( $order_id );
		$note        = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];

		$this->assertEquals( 'success', $response['result'] );
		$this->assertEquals( $payment_method_id, WC_Stripe_Order_Helper::get_instance()->get_stripe_source_id( $final_order ) );
		$this->assertMatchesRegularExpression( '/Charge ID: ch_mock/', $note->content );
	}

	/**
	 * Data provider for {@see test_process_payment_with_express_checkout_payment_method()}.
	 *
	 * @return array
	 */
	public function provide_test_process_payment_with_express_checkout_payment_method(): array {
		return [
			'Amazon Pay with OC enabled'  => [
				'express_payment_method'     => WC_Stripe_Payment_Methods::AMAZON_PAY,
				'payment_method_id'          => '',
				'confirmation_token_id'      => 'ctoken_mock789',
				'optimized_checkout_enabled' => true,
			],
			'Amazon Pay with OC disabled' => [
				'express_payment_method'     => WC_Stripe_Payment_Methods::AMAZON_PAY,
				'payment_method_id'          => '',
				'confirmation_token_id'      => 'ctoken_mock789',
				'optimized_checkout_enabled' => false,
			],
			'Apple Pay with OC enabled'   => [
				'express_payment_method'     => WC_Stripe_Payment_Methods::APPLE_PAY,
				'payment_method_id'          => 'pm_mock321',
				'confirmation_token_id'      => '',
				'optimized_checkout_enabled' => true,
			],
			'Apple Pay with OC disabled'  => [
				'express_payment_method'     => WC_Stripe_Payment_Methods::APPLE_PAY,
				'payment_method_id'          => 'pm_mock321',
				'confirmation_token_id'      => '',
				'optimized_checkout_enabled' => false,
			],
			'Google Pay with OC enabled'  => [
				'express_payment_method'     => WC_Stripe_Payment_Methods::GOOGLE_PAY,
				'payment_method_id'          => 'pm_mock123',
				'confirmation_token_id'      => '',
				'optimized_checkout_enabled' => true,
			],
			'Google Pay with OC disabled' => [
				'express_payment_method'     => WC_Stripe_Payment_Methods::GOOGLE_PAY,
				'payment_method_id'          => 'pm_mock123',
				'confirmation_token_id'      => '',
				'optimized_checkout_enabled' => false,
			],
			'Link with OC enabled'        => [
				'express_payment_method'     => WC_Stripe_Payment_Methods::LINK,
				'payment_method_id'          => '',
				'confirmation_token_id'      => 'ctoken_mock789',
				'optimized_checkout_enabled' => true,
			],
			'Link with OC disabled'       => [
				'express_payment_method'     => WC_Stripe_Payment_Methods::LINK,
				'payment_method_id'          => '',
				'confirmation_token_id'      => 'ctoken_mock789',
				'optimized_checkout_enabled' => false,
			],
		];
	}

	/**
	 * Test for `process_payment` with an express checkout payment method.
	 *
	 * @param string $express_payment_method     The express payment method.
	 * @param string $payment_method_id          The payment method ID.
	 * @param string $confirmation_token_id      The confirmation token ID.
	 * @param bool   $optimized_checkout_enabled Whether optimized checkout is enabled.
	 * @return void
	 *
	 * @dataProvider provide_test_process_payment_with_express_checkout_payment_method
	 */
	public function test_process_payment_with_express_checkout_payment_method( string $express_payment_method, string $payment_method_id, string $confirmation_token_id, bool $optimized_checkout_enabled ): void {
		$order         = WC_Helper_Order::create_order();
		$order_id      = $order->get_id();
		$customer_id   = 'cus_mock1234567890';
		$stripe_amount = WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $order->get_currency() );

		$_POST['payment_method']               = 'stripe';
		$_POST['wc-stripe-confirmation-token'] = $confirmation_token_id;
		$_POST['wc-stripe-payment-method']     = $payment_method_id;
		$_POST['wc-stripe-is-deferred-intent'] = '1';
		$_POST['express_payment_type']         = $express_payment_method;

		$this->mock_gateway->oc_enabled = $optimized_checkout_enabled;

		$payment_method_pre_http_filter = null;
		if ( '' !== $payment_method_id ) {
			$payment_method_pre_http_filter = function ( $result, $args, $url ) use ( $payment_method_id, $express_payment_method ) {
				if ( 'payment_methods/' . $payment_method_id === $url ) {
					return $this->get_mock_express_checkout_payment_method( $payment_method_id, $express_payment_method );
				}
				return $result;
			};
			add_filter( 'pre_http_request', $payment_method_pre_http_filter, 10, 3 );
		}

		$mock_intent = (object) [
			'id'                   => 'pi_mock1234567890',
			'object'               => 'payment_intent',
			'amount'               => $stripe_amount,
			'amount_received'      => $stripe_amount,
			'currency'             => strtolower( $order->get_currency() ),
			'customer'             => 'cus_mock1234567890',
			'description'          => 'Test Store - Order ' . $order_id,
			'latest_charge'        => 'ch_mock1234567890',
			'payment_method'       => '' === $payment_method_id ? 'pm_mock1234' : $payment_method_id,
			'payment_method_types' => [ WC_Stripe_Payment_Methods::AMAZON_PAY === $express_payment_method ? WC_Stripe_Payment_Methods::AMAZON_PAY : WC_Stripe_Payment_Methods::CARD ],
			'status'               => 'succeeded',
			'created'              => time(),
		];

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->with(
				$this->callback(
					function ( $payment_information ) use ( $payment_method_id, $express_payment_method, $confirmation_token_id, $order_id ) {
						if ( '' === $confirmation_token_id ) {
							$this->assertArrayNotHasKey( 'confirmation_token', $payment_information );
						} else {
							$this->assertArrayHasKey( 'confirmation_token', $payment_information );
							$this->assertEquals( $confirmation_token_id, $payment_information['confirmation_token'] );
						}
						if ( '' === $payment_method_id ) {
							$this->assertArrayNotHasKey( 'payment_method', $payment_information );
						} else {
							$this->assertEquals( $payment_method_id, $payment_information['payment_method'] );
						}
						$this->assertInstanceOf( WC_Order::class, $payment_information['order'] );
						$this->assertEquals( $order_id, $payment_information['order']->get_id() );
						$expected_selected_payment_type = WC_Stripe_Payment_Methods::AMAZON_PAY === $express_payment_method ? WC_Stripe_Payment_Methods::AMAZON_PAY : WC_Stripe_Payment_Methods::CARD;
						$this->assertEquals( $expected_selected_payment_type, $payment_information['selected_payment_type'] );
						$this->assertIsArray( $payment_information['payment_method_types'] );
						$this->assertContains( $expected_selected_payment_type, $payment_information['payment_method_types'] );

						return true;
					}
				)
			)
			->willReturn( $mock_intent );

		$mock_charge = (object) [
			'id'       => 'ch_mock1234567890',
			'captured' => true,
			'status'   => 'succeeded',
		];

		$this->mock_gateway
			->expects( $this->exactly( 2 ) )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $mock_charge );

		$response = $this->mock_gateway->process_payment( $order_id );

		if ( null !== $payment_method_pre_http_filter ) {
			remove_filter( 'pre_http_request', $payment_method_pre_http_filter, 10 );
		}

		$this->assertIsArray( $response );
		$this->assertEquals( 'success', $response['result'] );
	}

	/**
	 * Test for `filter_saved_payment_methods_list`
	 *
	 * @param bool $saved_cards Whether saved cards are enabled.
	 * @param array $item The list of saved payment methods.
	 * @param array $expected The expected list of saved payment methods.
	 * @return void
	 * @dataProvider provide_test_filter_saved_payment_methods_list
	 */
	public function test_filter_saved_payment_methods_list( $saved_cards, $item, $expected ) {
		$payment_token                   = $this->getMockBuilder( 'WC_Payment_Token_CC' )
			->disableOriginalConstructor()
			->getMock();
		$this->mock_gateway->saved_cards = $saved_cards;
		$list                            = $this->mock_gateway->filter_saved_payment_methods_list( $item, $payment_token );
		$this->assertSame( $expected, $list );
	}

	/**
	 * Provider for `test_filter_saved_payment_methods_list`
	 *
	 * @return array
	 */
	public function provide_test_filter_saved_payment_methods_list() {
		$item = [
			'brand'     => 'visa',
			'exp_month' => '7',
			'exp_year'  => '2099',
			'last4'     => '4242',
		];
		return [
			'Saved cards enabled'  => [
				'saved cards' => true,
				'item'        => $item,
				'expected'    => $item,
			],
			'Saved cards disabled' => [
				'saved cards' => false,
				'item'        => $item,
				'expected'    => [],
			],
		];
	}

	/**
	 * Test test_set_payment_method_title_for_order.
	 *
	 */
	public function test_set_payment_method_title_for_order() {
		$order = WC_Helper_Order::create_order();

		// Subscriptions - note that orders are used here as subscriptions. Subscriptions inherit all order methods so should suffice for testing.
		$mock_subscription_0 = WC_Helper_Order::create_order();
		$mock_subscription_1 = WC_Helper_Order::create_order();

		WC_Subscriptions_Helpers::$wcs_get_subscriptions_for_order = [ $mock_subscription_0, $mock_subscription_1 ];

		/**
		 * SEPA
		 */
		$this->mock_gateway->set_payment_method_title_for_order( $order, WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID );

		$this->assertEquals( 'stripe_sepa_debit', $order->get_payment_method() );
		$this->assertEquals( 'SEPA Direct Debit', $order->get_payment_method_title() );

		$this->assertEquals( 'stripe_sepa_debit', $mock_subscription_0->get_payment_method() );
		$this->assertEquals( 'stripe_sepa_debit', $mock_subscription_0->get_payment_method() );

		/**
		 * iDEAL
		 */
		$this->mock_gateway->set_payment_method_title_for_order( $order, WC_Stripe_UPE_Payment_Method_Ideal::STRIPE_ID );

		$this->assertEquals( 'stripe_ideal', $order->get_payment_method() );
		$this->assertEquals( 'iDEAL', $order->get_payment_method_title() );

		// iDEAL subscriptions should be set to SEPA as it's the processing payment method of subscription payments for iDEAL.
		$this->assertEquals( 'stripe_sepa_debit', $mock_subscription_0->get_payment_method() );
		$this->assertEquals( 'stripe_sepa_debit', $mock_subscription_0->get_payment_method() );

		/**
		 * Cards
		 */
		$this->mock_gateway->set_payment_method_title_for_order( $order, WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID );

		// Cards should be set to `stripe`.
		$this->assertEquals( 'stripe', $order->get_payment_method() );
		$this->assertEquals( 'Credit / Debit Card', $order->get_payment_method_title() );

		$this->assertEquals( 'stripe', $mock_subscription_0->get_payment_method() );
		$this->assertEquals( 'stripe', $mock_subscription_0->get_payment_method() );

		/**
		 * Link
		 */
		$this->mock_gateway->set_payment_method_title_for_order( $order, WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID );
		// Cards should be set to `stripe`.
		$this->assertEquals( 'stripe', $order->get_payment_method() );
		$this->assertEquals( 'Link', $order->get_payment_method_title() );

		$this->assertEquals( 'stripe', $mock_subscription_0->get_payment_method() );
		$this->assertEquals( 'stripe', $mock_subscription_0->get_payment_method() );
	}

	/**
	 * Test test_set_payment_method_title_for_order with ECE wallet PM.
	 */
	public function test_set_payment_method_title_for_order_ECE_title() {
		$order = WC_Helper_Order::create_order();

		// GOOGLE PAY
		$mock_ece_payment_method = (object) [
			'card' => (object) [
				'brand'  => 'visa',
				'wallet' => (object) [
					'type' => 'google_pay',
				],
			],
		];

		$this->mock_gateway->set_payment_method_title_for_order( $order, WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID, $mock_ece_payment_method );
		$this->assertEquals( 'Google Pay (Stripe)', $order->get_payment_method_title() );

		// APPLE PAY
		$mock_ece_payment_method->card->wallet->type = 'apple_pay';
		$this->mock_gateway->set_payment_method_title_for_order( $order, WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID, $mock_ece_payment_method );
		$this->assertEquals( 'Apple Pay (Stripe)', $order->get_payment_method_title() );

		// INVALID
		$mock_ece_payment_method->card->wallet->type = 'invalid';
		$this->mock_gateway->set_payment_method_title_for_order( $order, WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID, $mock_ece_payment_method );

		// Invalid wallet type should default to Credit / Debit Card.
		$this->assertEquals( 'Credit / Debit Card', $order->get_payment_method_title() );

		// NO WALLET
		unset( $mock_ece_payment_method->card->wallet->type );
		$this->mock_gateway->set_payment_method_title_for_order( $order, WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID, $mock_ece_payment_method );

		// No wallet type should default to Credit / Debit Card.
		$this->assertEquals( 'Credit / Debit Card', $order->get_payment_method_title() );
	}

	/**
	 * Test for `filter_my_account_my_orders_actions`.
	 *
	 * @dataProvider payment_method_titles_provider
	 */
	public function test_filter_my_account_my_orders_actions( $payment_method_title ) {
		add_filter(
			'woocommerce_is_order_received_page',
			function () {
				return true;
			}
		);

		$order = WC_Helper_Order::create_order();
		$order->set_payment_method_title( $payment_method_title );
		$order->set_status( OrderStatus::PENDING );

		$actions = [
			'pay'    => [
				'url'        => $order->get_checkout_payment_url(),
				'name'       => 'Pay',
				'aria-label' => sprintf( 'Pay for order %s', $order->get_order_number() ),
			],
			'view'   => [
				'url'        => $order->get_view_order_url(),
				'name'       => 'View',
				'aria-label' => sprintf( 'View order %s', $order->get_order_number() ),
			],
			'cancel' => [
				'url'        => $order->get_cancel_order_url( wc_get_page_permalink( 'myaccount' ) ),
				'name'       => 'Cancel',
				'aria-label' => sprintf( 'Cancel order %s', $order->get_order_number() ),
			],
		];

		$actual = $this->mock_gateway->filter_my_account_my_orders_actions( $actions, $order );

		$this->assertEquals(
			[
				'view' => [
					'url'        => $order->get_view_order_url(),
					'name'       => 'View',
					'aria-label' => sprintf( 'View order %s', $order->get_order_number() ),
				],
			],
			$actual
		);
	}

	/**
	 * Data provider for `test_filter_my_account_my_orders_actions`.
	 *
	 * @return array
	 */
	public function payment_method_titles_provider() {
		return [
			'Bacs' => [ WC_Stripe_Payment_Methods::BACS_DEBIT_LABEL ],
		];
	}

	/**
	 * Test that a failed payment intent is not reused and a new one is created instead.
	 *
	 * @param bool $pmc_enabled Whether the payment method configurations are enabled.
	 * @param bool $setting_enabled Whether the optimized checkout setting is enabled.
	 * @param bool $expected The expected result of the `is_oc_enabled` method.
	 * @return void
	 *
	 * @dataProvider provide_test_is_oc_enabled
	 */
	public function test_is_oc_enabled( $pmc_enabled, $setting_enabled, $expected ) {
		if ( $pmc_enabled ) {
			PMC_Test_Helper::enable_pmc();

			// Mock the payment method configuration for the test, to avoid it being disabled by default.
			PMC_Test_Helper::cache_mocked_configuration();
		}

		if ( $setting_enabled ) {
			OC_Test_Helper::enable_oc();
		}

		$gateway = new WC_Stripe_UPE_Payment_Gateway();
		$actual  = $gateway->is_oc_enabled();

		// Clean up
		PMC_Test_Helper::disable_pmc();
		PMC_Test_Helper::delete_cached_configuration();
		OC_Test_Helper::disable_oc();

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider for `test_is_oc_enabled`.
	 *
	 * @return array[]
	 */
	public function provide_test_is_oc_enabled() {
		return [
			'Disabled (all disabled)' => [
				'pmc enabled'   => false,
				'setting value' => false,
				'expected'      => false,
			],
			'Disabled (pmc enabled)'  => [
				'pmc enabled'   => true,
				'setting value' => false,
				'expected'      => false,
			],
			'Enabled'                 => [
				'pmc enabled'   => true,
				'setting value' => true,
				'expected'      => true,
			],
		];
	}

	/**
	 * Test for `get_payment_method_instance`.
	 *
	 * @return void
	 */
	public function test_get_payment_method_instance() {
		$actual = $this->mock_gateway->get_payment_method_instance( WC_Stripe_Payment_Methods::CARD );
		$this->assertInstanceOf( WC_Stripe_UPE_Payment_Method_CC::class, $actual );
	}

	/**
	 * Test for `add_bnpl_debug_metadata`.
	 *
	 * @return void
	 */
	public function test_add_bnpl_debug_metadata() {
		$init_oc_enabled  = $this->mock_gateway->oc_enabled;
		$init_pmc_enabled = $this->mock_gateway->settings['pmc_enabled'] ?? null;

		$this->mock_gateway->oc_enabled              = true;
		$this->mock_gateway->settings['pmc_enabled'] = true;

		$order = WC_Helper_Order::create_order();

		$result = apply_filters( 'wc_stripe_intent_metadata', [], $order );

		// Reset all variables and filters.
		$this->mock_gateway->oc_enabled = $init_oc_enabled;
		if ( null === $init_pmc_enabled ) {
			unset( $this->mock_gateway->settings['pmc_enabled'] );
		} else {
			$this->mock_gateway->settings['pmc_enabled'] = $init_pmc_enabled ? 'yes' : 'no';
		}

		$this->assertArrayHasKey( 'is_legacy_checkout_enabled', $result );
		$this->assertArrayHasKey( 'is_oc_enabled', $result );
		$this->assertEquals( 'yes', $result['is_oc_enabled'] );
		$this->assertArrayHasKey( 'pmc_enabled', $result );
		$this->assertEquals( 'yes', $result['pmc_enabled'] );
	}

	/**
	 * Test that get_customer_id_for_order() correctly creates or updates customers with billing details.
	 *
	 * For guest users, billing details are retrieved from the order object.
	 * For logged-in users, billing details come from user meta (user email and user meta fields),
	 * with the order parameter available as a fallback when user data is missing.
	 *
	 * @dataProvider provide_get_customer_id_for_order_billing_details_test_cases
	 *
	 * @param string $scenario_name Description of the test scenario.
	 * @param bool   $is_guest Whether the order is for a guest user.
	 * @param string $existing_stripe_customer_id Existing Stripe customer ID for the user (empty for new customer).
	 * @param string $expected_customer_id Expected Stripe customer ID to be returned.
	 * @param string $api_url_pattern Pattern to match the API URL.
	 * @param array  $billing_data Billing data to set on the order (and user meta for logged-in users).
	 * @param array  $expected_customer_data Expected customer data in the API request.
	 *
	 * @return void
	 */
	public function test_get_customer_id_for_order_retrieves_billing_details_from_order( string $scenario_name, bool $is_guest, string $existing_stripe_customer_id, string $expected_customer_id, string $api_url_pattern, array $billing_data, array $expected_customer_data ) {
		// Create user if needed.
		$user_id = 0;
		$customer_id = 0;
		$user_email = '';
		if ( ! $is_guest ) {
			// For logged-in users, the code uses user email and user meta, not order data.
			// Set user email to match expected data, and set user meta to match order billing data.
			$user_email = $billing_data['email'];
			$user_id = wp_create_user( 'testuser_' . uniqid(), 'password', $user_email );
			$customer_id = $user_id;
			if ( ! empty( $existing_stripe_customer_id ) ) {
				update_user_option( $user_id, '_stripe_customer_id', $existing_stripe_customer_id );
			}
			// Set user meta to match the order billing data.
			// For logged-in users, user meta takes precedence over order data.
			update_user_meta( $user_id, 'billing_first_name', $billing_data['first_name'] );
			update_user_meta( $user_id, 'billing_last_name', $billing_data['last_name'] );
			update_user_meta( $user_id, 'billing_address_1', $billing_data['address_1'] );
			update_user_meta( $user_id, 'billing_address_2', $billing_data['address_2'] ?? '' );
			update_user_meta( $user_id, 'billing_city', $billing_data['city'] );
			update_user_meta( $user_id, 'billing_state', $billing_data['state'] );
			update_user_meta( $user_id, 'billing_postcode', $billing_data['postcode'] );
			update_user_meta( $user_id, 'billing_country', $billing_data['country'] );
		}

		// Create an order with specific billing details.
		$order = WC_Helper_Order::create_order( $customer_id );
		$order->set_billing_email( $billing_data['email'] );
		$order->set_billing_first_name( $billing_data['first_name'] );
		$order->set_billing_last_name( $billing_data['last_name'] );
		$order->set_billing_address_1( $billing_data['address_1'] );
		$order->set_billing_address_2( $billing_data['address_2'] ?? '' );
		$order->set_billing_city( $billing_data['city'] );
		$order->set_billing_state( $billing_data['state'] );
		$order->set_billing_postcode( $billing_data['postcode'] );
		$order->set_billing_country( $billing_data['country'] );
		$order->save();

		// Ensure no customer ID is set on the order.
		$order_helper = WC_Stripe_Order_Helper::get_instance();
		$order_helper->delete_stripe_customer_id( $order );

		// Mock the API request to verify billing details are used.
		$api_called = false;
		$captured_args = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) use ( &$api_called, &$captured_args, $expected_customer_data, $api_url_pattern, $expected_customer_id ) {
				if ( preg_match( $api_url_pattern, $url ) ) {
					$api_called = true;
					$captured_args = $parsed_args;

					// Return a mock successful response.
					return [
						'response' => [
							'code'    => 200,
							'message' => 'OK',
						],
						'headers'  => [ 'Content-Type' => 'application/json' ],
						'body'     => wp_json_encode(
							[
								'id'    => $expected_customer_id,
								'email' => $expected_customer_data['email'],
								'name'  => $expected_customer_data['name'],
							]
						),
					];
				}

				return $preempt;
			},
			10,
			3
		);

		// Create a mock gateway instance with specific methods mocked.
		// The mock inherits all methods from WC_Stripe_UPE_Payment_Gateway, including the private method we'll test.
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->onlyMethods( [ 'get_stripe_customer_id', 'get_user_from_order', 'is_valid_pay_for_order_endpoint' ] )
			->getMock();

		// Use reflection to access the private method on the mock instance.
		// The mock inherits the method from the parent class, so reflection works correctly.
		$reflection = new \ReflectionClass( $gateway );
		$method     = $reflection->getMethod( 'get_customer_id_for_order' );
		$method->setAccessible( true );

		$gateway->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->with( $order )
			->willReturn( '' ); // No customer ID on order.

		if ( ! $is_guest ) {
			$user = get_user_by( 'id', $user_id );
		} else {
			$user = new \WP_User();
			$user->ID = 0;
		}

		$gateway->expects( $this->once() )
			->method( 'get_user_from_order' )
			->with( $order )
			->willReturn( $user );

		$gateway->expects( $this->any() )
			->method( 'is_valid_pay_for_order_endpoint' )
			->willReturn( false );

		// Call the method.
		$result_customer_id = $method->invoke( $gateway, $order );

		// Verify the API was called and billing details were used.
		$this->assertTrue( $api_called, "Stripe API should have been called to {$scenario_name}." );
		$this->assertEquals( $expected_customer_id, $result_customer_id );

		// Verify the request body contains the expected billing details.
		// The body is passed as an array to wp_safe_remote_post, so we check it directly.
		if ( $captured_args && isset( $captured_args['body'] ) ) {
			$request_body = $captured_args['body'];
			// Ensure we have an array (wp_safe_remote_post receives body as array).
			$this->assertIsArray( $request_body, 'Request body should be an array.' );

			// Verify that the order object is NOT included in the API request (main purpose of this PR).
			$this->assertArrayNotHasKey( 'order', $request_body, 'Order object should not be included in the API request.' );

			// Verify billing details from the order are used in the customer creation/update request.
			$this->assertEquals( $expected_customer_data['email'], $request_body['email'] ?? '', 'Billing email should match order billing email.' );
			$this->assertEquals( $expected_customer_data['name'], $request_body['name'] ?? '', 'Billing name should match order billing name.' );

			// Verify address details are present and match the order.
			$this->assertArrayHasKey( 'address', $request_body, 'Request should include address data.' );
			$this->assertEquals( $expected_customer_data['address']['line1'], $request_body['address']['line1'] ?? '', 'Billing address line1 should match order.' );
			if ( ! empty( $expected_customer_data['address']['line2'] ) ) {
				$this->assertEquals( $expected_customer_data['address']['line2'], $request_body['address']['line2'] ?? '', 'Billing address line2 should match order.' );
			} else {
				// When line2 is empty, verify it's either not present or empty in the request body.
				$this->assertTrue(
					null === $request_body['address']['line2'] || '' === $request_body['address']['line2'],
					'Billing address line2 should be empty or not present when order has no line2.'
				);
			}

			$this->assertEquals( $expected_customer_data['address']['city'], $request_body['address']['city'] ?? '', 'Billing city should match order.' );
			$this->assertEquals( $expected_customer_data['address']['state'], $request_body['address']['state'] ?? '', 'Billing state should match order.' );
			$this->assertEquals( $expected_customer_data['address']['postal_code'], $request_body['address']['postal_code'] ?? '', 'Billing postal code should match order.' );
			$this->assertEquals( $expected_customer_data['address']['country'], $request_body['address']['country'] ?? '', 'Billing country should match order.' );
		}

		// Cleanup.
		if ( $user_id > 0 ) {
			wp_delete_user( $user_id );
		}
		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Data provider for test_get_customer_id_for_order_retrieves_billing_details_from_order.
	 *
	 * @return array Test cases.
	 */
	public function provide_get_customer_id_for_order_billing_details_test_cases() {
		return [
			'creating customer for guest user' => [
				'scenario_name'            => 'create customer',
				'is_guest'                 => true,
				'existing_stripe_customer_id' => '',
				'expected_customer_id'     => 'cus_test123',
				'api_url_pattern'          => '#/v1/customers$#',
				'billing_data'             => [
					'email'      => 'test-billing@example.com',
					'first_name' => 'TestFirstName',
					'last_name'  => 'TestLastName',
					'address_1'  => '123 Test Street',
					'address_2'  => 'Apt 4B',
					'city'       => 'TestCity',
					'state'      => 'CA',
					'postcode'   => '90210',
					'country'    => 'US',
				],
				'expected_customer_data'   => [
					'email'       => 'test-billing@example.com',
					'name'        => 'TestFirstName TestLastName',
					'description' => 'Name: TestFirstName TestLastName, Guest',
					'address'     => [
						'line1'       => '123 Test Street',
						'line2'       => 'Apt 4B',
						'city'        => 'TestCity',
						'state'       => 'CA',
						'postal_code' => '90210',
						'country'     => 'US',
					],
				],
			],
			'updating customer for logged-in user' => [
				'scenario_name'            => 'update customer',
				'is_guest'                 => false,
				'existing_stripe_customer_id' => 'cus_existing123',
				'expected_customer_id'     => 'cus_existing123',
				'api_url_pattern'          => '#/v1/customers/cus_existing123$#',
				'billing_data'             => [
					'email'      => 'updated-billing@example.com',
					'first_name' => 'UpdatedFirstName',
					'last_name'  => 'UpdatedLastName',
					'address_1'  => '456 Updated Street',
					'address_2'  => '',
					'city'       => 'UpdatedCity',
					'state'      => 'NY',
					'postcode'   => '10001',
					'country'    => 'US',
				],
				// For logged-in users, user email and user meta are used (not order data).
				// The expected data should match what will be in user meta (set in the test).
				'expected_customer_data'   => [
					'email'       => 'updated-billing@example.com', // User email matches order email (set in test)
					'name'        => 'UpdatedFirstName UpdatedLastName',
					'address'     => [
						'line1'       => '456 Updated Street',
						'line2'       => '',
						'city'        => 'UpdatedCity',
						'state'       => 'NY',
						'postal_code' => '10001',
						'country'     => 'US',
					],
				],
			],
		];
	}

	/**
	 * Test `get_excluded_payment_method_types` in various scenarios.
	 *
	 * @param array  $unsupported_methods        Unsupported payment methods from PMC.
	 * @param callable|null $filter_callback     Filter callback function or null.
	 * @param array  $expected_excluded          Payment methods expected to be excluded.
	 * @param array  $expected_not_excluded      Payment methods expected NOT to be excluded.
	 * @return void
	 * @dataProvider provide_test_get_excluded_payment_method_types
	 */
	public function test_get_excluded_payment_method_types( array $unsupported_methods, $filter_callback, array $expected_excluded, array $expected_not_excluded ) {
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();
		$settings_base    = WC_Stripe_Helper::get_stripe_settings();

		// Set up settings with PMC enabled and test mode
		$settings = array_merge(
			$settings_base,
			[
				'pmc_enabled'          => 'yes',
				'testmode'             => 'yes',
				'test_publishable_key' => 'pk_test_1234567890',
				'test_secret_key'      => 'sk_test_1234567890',
				'test_connection_type' => 'connect',
			]
		);
		WC_Stripe_Helper::update_main_stripe_settings( $settings );

		// Build mock API response with unsupported enabled methods
		$pmc_data = (object) [
			'id'       => 'pmc_test',
			'parent'   => \WC_Stripe_Payment_Method_Configurations::TEST_MODE_CONFIGURATION_PARENT_ID,
			'active'   => true,
			'livemode' => false,
		];

		foreach ( $unsupported_methods as $method_id ) {
			$pmc_data->$method_id = (object) [
				'display_preference' => (object) [ 'value' => 'on' ],
			];
		}

		$mock_api_response = (object) [
			'data' => [ $pmc_data ],
		];

		$mock_api = $this->getMockBuilder( WC_Stripe_API::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_api->method( 'get_payment_method_configurations' )
			->willReturn( $mock_api_response );

		$reflection = new \ReflectionClass( WC_Stripe_API::class );
		$property   = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, $mock_api );

		// Clear cache
		delete_option( \WC_Stripe_Payment_Method_Configurations::FETCH_COOLDOWN_OPTION_KEY );
		\WC_Stripe_Payment_Method_Configurations::clear_payment_method_configuration_cache();

		// Add filter if provided
		if ( null !== $filter_callback ) {
			add_filter( 'wc_stripe_ocs_non_excludable_payment_methods', $filter_callback );
		}

		// Create gateway instance and call method via reflection
		$gateway            = new WC_Stripe_UPE_Payment_Gateway();
		$reflection_gateway = new \ReflectionClass( WC_Stripe_UPE_Payment_Gateway::class );
		$method             = $reflection_gateway->getMethod( 'get_excluded_payment_method_types' );
		$method->setAccessible( true );

		$excluded_methods = $method->invoke( $gateway );

		// Cleanup
		if ( null !== $filter_callback ) {
			remove_filter( 'wc_stripe_ocs_non_excludable_payment_methods', $filter_callback );
		}
		WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );
		$property->setValue( null, null );
		delete_option( \WC_Stripe_Payment_Method_Configurations::FETCH_COOLDOWN_OPTION_KEY );
		\WC_Stripe_Payment_Method_Configurations::clear_payment_method_configuration_cache();

		// Assertions.
		foreach ( $expected_excluded as $method_id ) {
			$this->assertContains(
				$method_id,
				$excluded_methods,
				"Expected method '{$method_id}' to be excluded."
			);
		}

		foreach ( $expected_not_excluded as $method_id ) {
			$this->assertNotContains(
				$method_id,
				$excluded_methods,
				"Expected method '{$method_id}' NOT to be excluded."
			);
		}

		// Amazon Pay should always be excluded
		$this->assertContains(
			WC_Stripe_Payment_Methods::AMAZON_PAY,
			$excluded_methods,
			'Amazon Pay should always be excluded.'
		);
	}

	/**
	 * Data provider for `test_get_excluded_payment_method_types`.
	 *
	 * @return array
	 */
	public function provide_test_get_excluded_payment_method_types(): array {
		return [
			'No filter, unsupported methods'  => [
				'unsupported_methods'   => [ 'fpx', 'naver_pay', 'paypal' ],
				'filter_callback'       => null,
				'expected_excluded'     => [ 'fpx', 'naver_pay', 'paypal', WC_Stripe_Payment_Methods::AMAZON_PAY ],
				'expected_not_excluded' => [],
			],
			'Filter with unsupported methods' => [
				'unsupported_methods'   => [ 'fpx', 'naver_pay', 'abc' ],
				'filter_callback'       => function () {
					return [ 'abc' ];
				},
				'expected_excluded'     => [ 'fpx', 'naver_pay', WC_Stripe_Payment_Methods::AMAZON_PAY ],
				'expected_not_excluded' => [ 'abc' ],
			],
			'Filter with empty array'         => [
				'unsupported_methods'   => [ 'fpx', 'naver_pay' ],
				'filter_callback'       => function () {
					return [];
				},
				'expected_excluded'     => [ 'fpx', 'naver_pay', WC_Stripe_Payment_Methods::AMAZON_PAY ],
				'expected_not_excluded' => [],
			],
			'Filter with non-string values'   => [
				'unsupported_methods'   => [ 'fpx', 'naver_pay', 'paypal', 'abc' ],
				'filter_callback'       => function () {
					return [ 123, null, 'abc', [], 'valid_method' ];
				},
				'expected_excluded'     => [ 'fpx', 'naver_pay', 'paypal', WC_Stripe_Payment_Methods::AMAZON_PAY ],
				'expected_not_excluded' => [ 'abc', 'valid_method' ],
			],
			'Filter with methods already in NON_EXCLUDABLE_PAYMENT_METHOD_TYPES' => [
				'unsupported_methods'   => [ 'fpx', 'naver_pay', 'paypal' ],
				'filter_callback'       => function () {
					return [ 'link', 'apple_pay', 'abc' ];
				},
				'expected_excluded'     => [ 'fpx', 'naver_pay', 'paypal', WC_Stripe_Payment_Methods::AMAZON_PAY ],
				'expected_not_excluded' => [ 'link', 'apple_pay', 'abc' ],
			],
			'No unsupported methods'          => [
				'unsupported_methods'   => [],
				'filter_callback'       => null,
				'expected_excluded'     => [ WC_Stripe_Payment_Methods::AMAZON_PAY ],
				'expected_not_excluded' => [],
			],
			'Filter with duplicate values'    => [
				'unsupported_methods'   => [ 'fpx', 'naver_pay' ],
				'filter_callback'       => function () {
					return [ 'fpx', 'fpx', 'naver_pay' ];
				},
				'expected_excluded'     => [ WC_Stripe_Payment_Methods::AMAZON_PAY ],
				'expected_not_excluded' => [ 'fpx', 'naver_pay' ],
			],
		];
	}
}
