<?php

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;
use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

/**
 * These tests make assertions against class WC_Stripe_Express_Checkout_Custom_Fields.
 *
 * @package WooCommerce/Stripe/WC_Stripe_Express_Checkout_Custom_Fields
 *
 * Class WC_Stripe_Express_Checkout_Custom_Fields_Test
 */
class WC_Stripe_Express_Checkout_Custom_Fields_Test extends WP_UnitTestCase {
	/**
	 * Builds the class under test with the express-checkout-context check stubbed,
	 * since it depends on request globals absent in unit tests.
	 *
	 * @param bool $is_express_checkout_context Value the stubbed helper returns.
	 * @return WC_Stripe_Express_Checkout_Custom_Fields
	 */
	private function get_custom_fields_support( bool $is_express_checkout_context = true ) {
		$helper = $this->getMockBuilder( WC_Stripe_Express_Checkout_Helper::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'is_express_checkout_context' ] )
			->getMock();
		$helper->method( 'is_express_checkout_context' )->willReturn( $is_express_checkout_context );

		return new WC_Stripe_Express_Checkout_Custom_Fields( $helper );
	}

	public function test_get_custom_checkout_fields_classic() {
		$custom_checkout_fields = function ( $fields ) {
			$fields['billing']['billing_custom_field1']   = [
				'type'     => 'text',
				'label'    => 'Billing Custom Field 1',
				'required' => true,
			];
			$fields['shipping']['shipping_custom_field1'] = [
				'type'     => 'radio',
				'label'    => 'Shipping Custom Field 1',
				'required' => true,
			];
			$fields['order']['order_custom_field']        = [
				'type'     => 'select',
				'label'    => 'Order Custom Field',
				'required' => false,
			];
			return $fields;
		};

		$custom_billing_fields = function ( $fields ) {
			$fields['billing_custom_field2'] = [
				'type'     => 'textarea',
				'label'    => 'Billing Custom Field 2',
				'required' => true,
			];
			return $fields;
		};

		$custom_shipping_fields = function ( $fields ) {
			$fields['shipping_custom_field2'] = [
				'type'     => 'checkbox',
				'label'    => 'Shipping Custom Field 2',
				'required' => true,
			];
			return $fields;
		};

		add_filter( 'woocommerce_checkout_fields', $custom_checkout_fields );
		add_filter( 'woocommerce_billing_fields', $custom_billing_fields );
		add_filter( 'woocommerce_shipping_fields', $custom_shipping_fields );
		WC()->checkout()->checkout_fields = null;
		WC()->checkout()->get_checkout_fields();

		$custom_fields_support = $this->get_custom_fields_support();
		$fields                = $custom_fields_support->get_custom_checkout_fields( 'classic' );
		$this->assertCount( 5, $fields );
		$this->assertArrayHasKey( 'billing_custom_field1', $fields );
		$this->assertArrayHasKey( 'shipping_custom_field1', $fields );
		$this->assertArrayHasKey( 'order_custom_field', $fields );
		$this->assertArrayHasKey( 'billing_custom_field2', $fields );
		$this->assertArrayHasKey( 'shipping_custom_field2', $fields );

		$this->assertSame( 'text', $fields['billing_custom_field1']['type'] );
		$this->assertSame( 'radio', $fields['shipping_custom_field1']['type'] );
		$this->assertSame( 'select', $fields['order_custom_field']['type'] );
		$this->assertSame( 'textarea', $fields['billing_custom_field2']['type'] );
		$this->assertSame( 'checkbox', $fields['shipping_custom_field2']['type'] );

		$this->assertSame( 'billing', $fields['billing_custom_field1']['location'] );
		$this->assertSame( 'shipping', $fields['shipping_custom_field1']['location'] );
		$this->assertSame( 'order', $fields['order_custom_field']['location'] );
		$this->assertSame( 'billing', $fields['billing_custom_field2']['location'] );
		$this->assertSame( 'shipping', $fields['shipping_custom_field2']['location'] );

		$this->assertSame( 'Billing Custom Field 1', $fields['billing_custom_field1']['label'] );
		$this->assertSame( 'Shipping Custom Field 1', $fields['shipping_custom_field1']['label'] );
		$this->assertSame( 'Order Custom Field', $fields['order_custom_field']['label'] );
		$this->assertSame( 'Billing Custom Field 2', $fields['billing_custom_field2']['label'] );
		$this->assertSame( 'Shipping Custom Field 2', $fields['shipping_custom_field2']['label'] );

		$this->assertTrue( $fields['billing_custom_field1']['required'] );
		$this->assertTrue( $fields['shipping_custom_field1']['required'] );
		$this->assertFalse( $fields['order_custom_field']['required'] );
		$this->assertTrue( $fields['billing_custom_field2']['required'] );
		$this->assertTrue( $fields['shipping_custom_field2']['required'] );

		// Remove filters and reset checkout fields.
		remove_filter( 'woocommerce_checkout_fields', $custom_checkout_fields );
		remove_filter( 'woocommerce_billing_fields', $custom_billing_fields );
		remove_filter( 'woocommerce_shipping_fields', $custom_shipping_fields );
		WC()->checkout()->checkout_fields = null;
		WC()->checkout()->get_checkout_fields();
	}

	public function test_get_custom_checkout_fields_checkout_blocks() {
		woocommerce_register_additional_checkout_field(
			[
				'id'         => 'my-plugin/gov-id',
				'label'      => 'Government ID',
				'location'   => 'address',
				'attributes' => [],
			],
		);

		$custom_fields_support = $this->get_custom_fields_support();
		$fields                = $custom_fields_support->get_custom_checkout_fields( 'block' );
		$this->assertCount( 1, $fields );
		$this->assertArrayHasKey( 'my-plugin/gov-id', $fields );
		$this->assertSame( 'Government ID', $fields['my-plugin/gov-id']['label'] );
		$this->assertSame( 'address', $fields['my-plugin/gov-id']['location'] );
		$this->assertFalse( $fields['my-plugin/gov-id']['required'] );

		// Cleanup: remove the registered field.
		$checkout_fields = Package::container()->get( CheckoutFields::class );
		$checkout_fields->deregister_checkout_field( 'my-plugin/gov-id' );
	}

	/**
	 * Valid custom field data passes validation and is persisted on the order keyed by field ID.
	 *
	 * @return void
	 */
	public function test_process_custom_checkout_data_valid_data() {
		$custom_checkout_fields = function ( $fields ) {
			$fields['billing']['billing_custom_field1'] = [
				'type'     => 'text',
				'label'    => 'Billing Custom Field 1',
				'required' => true,
			];
			return $fields;
		};
		add_filter( 'woocommerce_checkout_fields', $custom_checkout_fields );
		WC()->checkout()->checkout_fields = null;
		WC()->checkout()->get_checkout_fields();

		$request = new \WP_REST_Request( 'POST', '/wc/stripe-ece/v1/test-request' );
		$request->set_param(
			'extensions',
			[
				'wc-stripe/express-checkout' => [
					'custom_checkout_data' => json_encode( [ 'billing_custom_field1' => 'test' ] ),
				],
			]
		);

		$order                 = WC_Helper_Order::create_order();
		$custom_fields_support = $this->get_custom_fields_support();

		// Assert no exceptions are thrown.
		try {
			$custom_fields_support->process_custom_checkout_data( $order, $request );
			$this->assertTrue( true );
		} catch ( Exception $e ) {
			$this->fail( 'Expected no exceptions to be thrown, but got: ' . $e->getMessage() );
		}

		// The entered value is saved on the order, not lost.
		$this->assertSame( 'test', $order->get_meta( 'billing_custom_field1' ) );

		// Remove filters and reset checkout fields.
		remove_filter( 'woocommerce_checkout_fields', $custom_checkout_fields );
		WC()->checkout()->checkout_fields = null;
		WC()->checkout()->get_checkout_fields();
	}

	/**
	 * Non-required custom field values are persisted on the order even when no third-party plugin hooks the stand-in action.
	 *
	 * @return void
	 */
	public function test_process_custom_checkout_data_persists_optional_values() {
		$custom_checkout_fields = function ( $fields ) {
			$fields['order']['order_custom_field'] = [
				'type'     => 'text',
				'label'    => 'Order Custom Field',
				'required' => false,
			];
			return $fields;
		};
		add_filter( 'woocommerce_checkout_fields', $custom_checkout_fields );
		WC()->checkout()->checkout_fields = null;
		WC()->checkout()->get_checkout_fields();

		$request = new \WP_REST_Request( 'POST', '/wc/stripe-ece/v1/test-request' );
		$request->set_param(
			'extensions',
			[
				'wc-stripe/express-checkout' => [
					'custom_checkout_data' => json_encode( [ 'order_custom_field' => 'gift wrap please' ] ),
				],
			]
		);

		$order                 = WC_Helper_Order::create_order();
		$custom_fields_support = $this->get_custom_fields_support();
		$custom_fields_support->process_custom_checkout_data( $order, $request );

		$this->assertSame( 'gift wrap please', $order->get_meta( 'order_custom_field' ) );

		// Remove filters and reset checkout fields.
		remove_filter( 'woocommerce_checkout_fields', $custom_checkout_fields );
		WC()->checkout()->checkout_fields = null;
		WC()->checkout()->get_checkout_fields();
	}

	/**
	 * Only registered checkout fields are persisted to order meta; client-supplied
	 * unknown keys (e.g. internal WooCommerce/Stripe meta keys) must not be written
	 * to the order or redirected to prop setters.
	 *
	 * @return void
	 */
	public function test_process_custom_checkout_data_ignores_unregistered_keys() {
		$custom_checkout_fields = function ( $fields ) {
			$fields['order']['order_custom_field'] = [
				'type'     => 'text',
				'label'    => 'Order Custom Field',
				'required' => false,
			];
			return $fields;
		};
		add_filter( 'woocommerce_checkout_fields', $custom_checkout_fields );
		WC()->checkout()->checkout_fields = null;
		WC()->checkout()->get_checkout_fields();

		$request = new \WP_REST_Request( 'POST', '/wc/stripe-ece/v1/test-request' );
		$request->set_param(
			'extensions',
			[
				'wc-stripe/express-checkout' => [
					'custom_checkout_data' => json_encode(
						[
							'order_custom_field' => 'legit value',
							'_billing_email'     => 'attacker@example.com',
							'_customer_user'     => '1',
							'_stripe_source_id'  => 'pm_attacker',
						]
					),
				],
			]
		);

		$order          = WC_Helper_Order::create_order();
		$original_email = $order->get_billing_email();

		$custom_fields_support = $this->get_custom_fields_support();
		$custom_fields_support->process_custom_checkout_data( $order, $request );

		$this->assertSame( 'legit value', $order->get_meta( 'order_custom_field' ) );
		$this->assertSame( '', $order->get_meta( '_customer_user' ) );
		$this->assertSame( '', $order->get_meta( '_stripe_source_id' ) );
		// _billing_email is an internal key WC_Data redirects to set_billing_email();
		// the whitelist must prevent that redirect from ever happening.
		$this->assertSame( $original_email, $order->get_billing_email() );

		// Remove filters and reset checkout fields.
		remove_filter( 'woocommerce_checkout_fields', $custom_checkout_fields );
		WC()->checkout()->checkout_fields = null;
		WC()->checkout()->get_checkout_fields();
	}

	/**
	 * Classic-checkout custom field processing is enabled by default, and can be disabled via the opt-out filter.
	 *
	 * @return void
	 */
	public function test_classic_custom_fields_enabled_by_default() {
		$this->assertTrue( apply_filters( 'wc_stripe_express_checkout_enable_classic_checkout_custom_fields', true ) );

		$opt_out = '__return_false';
		add_filter( 'wc_stripe_express_checkout_enable_classic_checkout_custom_fields', $opt_out );
		$this->assertFalse( apply_filters( 'wc_stripe_express_checkout_enable_classic_checkout_custom_fields', true ) );
		remove_filter( 'wc_stripe_express_checkout_enable_classic_checkout_custom_fields', $opt_out );
	}

	public function test_process_custom_checkout_data_missing_data() {
		$custom_checkout_fields = function ( $fields ) {
			$fields['billing']['billing_custom_field1'] = [
				'type'     => 'text',
				'label'    => 'Billing Custom Field 1',
				'required' => true,
			];
			return $fields;
		};
		add_filter( 'woocommerce_checkout_fields', $custom_checkout_fields );
		WC()->checkout()->checkout_fields = null;
		WC()->checkout()->get_checkout_fields();

		$request = new \WP_REST_Request( 'POST', '/wc/stripe-ece/v1/test-request' );
		$request->set_param(
			'extensions',
			[
				'wc-stripe/express-checkout' => [
					'custom_checkout_data' => json_encode( [] ),
				],
			]
		);
		$order                 = WC_Helper_Order::create_order();
		$custom_fields_support = $this->get_custom_fields_support();

		// Assert RouteException is thrown.
		$this->expectException( RouteException::class );
		$custom_fields_support->process_custom_checkout_data( $order, $request );

		// Remove filters and reset checkout fields.
		remove_filter( 'woocommerce_checkout_fields', $custom_checkout_fields );
		WC()->checkout()->checkout_fields = null;
		WC()->checkout()->get_checkout_fields();
	}

	/**
	 * Non-express-checkout Store API requests are skipped entirely, so a missing required custom field does not block them.
	 *
	 * @return void
	 */
	public function test_process_custom_checkout_data_skips_non_express_checkout_request() {
		$custom_checkout_fields = function ( $fields ) {
			$fields['billing']['billing_custom_field1'] = [
				'type'     => 'text',
				'label'    => 'Billing Custom Field 1',
				'required' => true,
			];
			return $fields;
		};
		add_filter( 'woocommerce_checkout_fields', $custom_checkout_fields );
		WC()->checkout()->checkout_fields = null;
		WC()->checkout()->get_checkout_fields();

		// A normal checkout request carries no express checkout custom field data.
		$request = new \WP_REST_Request( 'POST', '/wc/stripe-ece/v1/test-request' );
		$request->set_param( 'extensions', [] );

		$order                 = WC_Helper_Order::create_order();
		$custom_fields_support = $this->get_custom_fields_support( false );

		// Required field missing, but a non-express request must not be blocked.
		try {
			$custom_fields_support->process_custom_checkout_data( $order, $request );
			$this->assertTrue( true );
		} catch ( Exception $e ) {
			$this->fail( 'Non-express-checkout requests must be skipped, but got: ' . $e->getMessage() );
		}

		// Remove filters and reset checkout fields.
		remove_filter( 'woocommerce_checkout_fields', $custom_checkout_fields );
		WC()->checkout()->checkout_fields = null;
		WC()->checkout()->get_checkout_fields();
	}
}
