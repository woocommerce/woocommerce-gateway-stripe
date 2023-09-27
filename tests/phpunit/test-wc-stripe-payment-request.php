<?php
/**
 * These teste make assertions against class WC_Stripe_Payment_Request.
 *
 * @package WooCommerce_Stripe/Tests/Payment_Request
 */

/**
 * WC_Stripe_Payment_Request_Test class.
 */
class WC_Stripe_Payment_Request_Test extends WP_UnitTestCase {
	const SHIPPING_ADDRESS = [
		'country'   => 'US',
		'state'     => 'CA',
		'postcode'  => '94110',
		'city'      => 'San Francisco',
		'address'   => '60 29th Street #343',
		'address_2' => '',
	];

	/**
	 * Payment request instance.
	 *
	 * @var WC_Stripe_Payment_Request
	 */
	private $pr;

	/**
	 * Test product to add to the cart
	 * @var WC_Product_Simple
	 */
	private $simple_product;

	/**
	 * Test shipping zone.
	 *
	 * @var WC_Shipping_Zone
	 */
	private $zone;

	/**
	 * Flat rate shipping method instance id
	 *
	 * @var int
	 */
	private $flat_rate_id;

	/**
	 * Flat rate shipping method instance id
	 *
	 * @var int
	 */
	private $local_pickup_id;

	/**
	 * @var UPE_Test_Helper
	 */
	private $upe_helper;

	/**
	 * Sets up things all tests need.
	 */
	public function set_up() {
		parent::set_up();

		$this->upe_helper = new UPE_Test_Helper();

		$this->pr = new WC_Stripe_Payment_Request();

		$this->simple_product = WC_Helper_Product::create_simple_product();

		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'Worldwide' );
		$zone->set_zone_order( 1 );
		$zone->save();

		$this->flat_rate_id = $zone->add_shipping_method( 'flat_rate' );
		self::set_shipping_method_cost( $this->flat_rate_id, '5' );

		$this->local_pickup_id = $zone->add_shipping_method( 'local_pickup' );
		self::set_shipping_method_cost( $this->local_pickup_id, '1' );

		$this->zone = $zone;

		WC()->session->init();
		WC()->cart->add_to_cart( $this->simple_product->get_id(), 1 );
		$this->pr->update_shipping_method( [ self::get_shipping_option_rate_id( $this->flat_rate_id ) ] );
		WC()->cart->calculate_totals();
	}

	public function tear_down() {
		WC()->cart->empty_cart();
		WC()->session->cleanup_sessions();
		$this->zone->delete();

		parent::tear_down();
	}

	/**
	 * Sets shipping method cost
	 *
	 * @param string $instance_id Shipping method instance id
	 * @param string $cost        Shipping method cost in USD
	 */
	private static function set_shipping_method_cost( $instance_id, $cost ) {
		$method          = WC_Shipping_Zones::get_shipping_method( $instance_id );
		$option_key      = $method->get_instance_option_key();
		$options         = get_option( $option_key );
		$options['cost'] = $cost;
		update_option( $option_key, $options );
	}

	/**
	 * Composes shipping option object by shipping method instance id.
	 *
	 * @param string $instance_id Shipping method instance id.
	 *
	 * @return array Shipping option.
	 */
	private static function get_shipping_option( $instance_id ) {
		$method = WC_Shipping_Zones::get_shipping_method( $instance_id );
		return [
			'id'     => $method->get_rate_id(),
			'label'  => $method->title,
			'detail' => '',
			'amount' => WC_Stripe_Helper::get_stripe_amount( $method->get_instance_option( 'cost' ) ),
		];
	}

	/**
	 * Retrieves rate id by shipping method instance id.
	 *
	 * @param string $instance_id Shipping method instance id.
	 *
	 * @return string Shipping option instance rate id.
	 */
	private static function get_shipping_option_rate_id( $instance_id ) {
		$method = WC_Shipping_Zones::get_shipping_method( $instance_id );
		return $method->get_rate_id();
	}


	public function test_get_shipping_options_returns_shipping_options() {
		$data = $this->pr->get_shipping_options( self::SHIPPING_ADDRESS );

		$expected_shipping_options = array_map(
			'self::get_shipping_option',
			[ $this->flat_rate_id, $this->local_pickup_id ]
		);

		$this->assertEquals( 'success', $data['result'] );
		$this->assertEquals( $expected_shipping_options, $data['shipping_options'], 'Shipping options mismatch' );
	}

	public function test_get_shipping_options_returns_chosen_option() {
		$data = $this->pr->get_shipping_options( self::SHIPPING_ADDRESS );

		$flat_rate              = $this->get_shipping_option( $this->flat_rate_id );
		$expected_display_items = [
			[
				'label'  => 'Subtotal',
				'amount' => 1000,
			],
			[
				'label'  => 'Shipping',
				'amount' => $flat_rate['amount'],
			],
		];

		$this->assertEquals( 1500, $data['total']['amount'], 'Total amount mismatch' );
		$this->assertEquals( $expected_display_items, $data['displayItems'], 'Display items mismatch' );
	}

	public function test_get_shipping_options_keeps_chosen_option() {
		$method_id = self::get_shipping_option_rate_id( $this->local_pickup_id );
		$this->pr->update_shipping_method( [ $method_id ] );

		$data = $this->pr->get_shipping_options( self::SHIPPING_ADDRESS );

		$expected_shipping_options = array_map(
			'self::get_shipping_option',
			[ $this->local_pickup_id, $this->flat_rate_id ]
		);

		$this->assertEquals( 'success', $data['result'] );
		$this->assertEquals( $expected_shipping_options, $data['shipping_options'], 'Shipping options mismatch' );
	}

	public function test_is_at_least_one_payment_request_button_enabled_link_enabled() {
		$this->pr->stripe_settings = [ 'payment_request' => false ];

		$this->upe_helper->enable_upe();

		update_option(
			'woocommerce_stripe_settings',
			array_merge(
				get_option( 'woocommerce_stripe_settings', [] ),
				[
					'upe_checkout_experience_accepted_payments' => [ 'link' ],
				]
			)
		);

		$this->assertTrue( $this->pr->is_at_least_one_payment_request_button_enabled() );
	}

	public function test_is_at_least_one_payment_request_button_enabled_pr_enabled() {
		$this->pr->stripe_settings = [ 'payment_request' => 'yes' ];

		$this->assertTrue( $this->pr->is_at_least_one_payment_request_button_enabled() );
	}

	public function test_is_at_least_one_payment_request_button_enabled_none_enabled() {
		$this->pr->stripe_settings = [ 'payment_request' => false ];

		$this->assertFalse( $this->pr->is_at_least_one_payment_request_button_enabled() );
	}
}
