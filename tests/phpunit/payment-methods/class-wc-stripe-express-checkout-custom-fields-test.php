<?php

namespace WooCommerce\Stripe\Tests\PaymentMethods;

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;
use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use WooCommerce\Stripe\Tests\Helpers\WC_Helper_Order;
use WC_Stripe_Express_Checkout_Custom_Fields;
use WP_UnitTestCase;

/**
 * These tests make assertions against class WC_Stripe_Express_Checkout_Custom_Fields.
 *
 * @package WooCommerce/Stripe/WC_Stripe_Express_Checkout_Custom_Fields
 *
 * Class WC_Stripe_Express_Checkout_Custom_Fields_Test
 */
class WC_Stripe_Express_Checkout_Custom_Fields_Test extends WP_UnitTestCase {
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

		$custom_fields_support = new WC_Stripe_Express_Checkout_Custom_Fields();
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

		$custom_fields_support = new WC_Stripe_Express_Checkout_Custom_Fields();
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
		$custom_fields_support = new WC_Stripe_Express_Checkout_Custom_Fields();

		// Assert no exceptions are thrown.
		try {
			$custom_fields_support->process_custom_checkout_data( $order, $request );
			$this->assertTrue( true );
		} catch ( Exception $e ) {
			$this->fail( 'Expected no exceptions to be thrown, but got: ' . $e->getMessage() );
		}

		// Remove filters and reset checkout fields.
		remove_filter( 'woocommerce_checkout_fields', $custom_checkout_fields );
		WC()->checkout()->checkout_fields = null;
		WC()->checkout()->get_checkout_fields();
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
		$custom_fields_support = new WC_Stripe_Express_Checkout_Custom_Fields();

		// Assert RouteException is thrown.
		$this->expectException( RouteException::class );
		$custom_fields_support->process_custom_checkout_data( $order, $request );

		// Remove filters and reset checkout fields.
		remove_filter( 'woocommerce_checkout_fields', $custom_checkout_fields );
		WC()->checkout()->checkout_fields = null;
		WC()->checkout()->get_checkout_fields();
	}
}
