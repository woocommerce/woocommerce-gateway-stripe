<?php

namespace WooCommerce\Stripe\Tests\PaymentMethods;

use WC_Stripe_Order_Helper;
use WC_Stripe_UPE_Payment_Gateway;
use WooCommerce\Stripe\Tests\WC_Mock_Stripe_API_Unit_Test_Case;
use WooCommerce\Stripe\Tests\Helpers\UPE_Test_Helper;
use WooCommerce\Stripe\Tests\Helpers\WC_Helper_Order;

/**
 * Tests for WC_Stripe_UPE_Payment_Gateway::display_order_fee()
 * and WC_Stripe_UPE_Payment_Gateway::display_order_payout().
 *
 * When the Stripe account currency differs from the order currency (e.g. an AUD
 * store whose Stripe account settles in USD), both $ symbols look identical in
 * the WP Admin order totals, making the amounts ambiguous. The fix appends the
 * 3-letter currency code when the currencies differ.
 *
 * @see https://github.com/woocommerce/woocommerce-gateway-stripe/issues/4184
 */
class WC_Stripe_UPE_Display_Order_Fee_Test extends WC_Mock_Stripe_API_Unit_Test_Case {

	/**
	 * @var WC_Stripe_UPE_Payment_Gateway
	 */
	private $gateway;

	public function set_up(): void {
		parent::set_up();

		$upe_helper = new UPE_Test_Helper();
		$upe_helper->enable_upe();
		$upe_helper->reload_payment_gateways();

		$this->gateway = new WC_Stripe_UPE_Payment_Gateway();
	}

	/**
	 * When Stripe currency matches the order currency, no code is appended to the fee row.
	 */
	public function test_display_order_fee_same_currency_no_code_appended(): void {
		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->save();

		$order_helper = WC_Stripe_Order_Helper::get_instance();
		$order_helper->update_stripe_fee( $order, '0.59' );
		$order_helper->update_stripe_currency( $order, 'USD' );
		$order->save();

		ob_start();
		$this->gateway->display_order_fee( $order->get_id() );
		$output = ob_get_clean();

		// No raw currency code appended when currencies match.
		$this->assertStringNotContainsString( ' USD', $output );
	}

	/**
	 * When Stripe currency differs from the order currency, the code is appended to the fee row.
	 */
	public function test_display_order_fee_different_currency_appends_code(): void {
		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'AUD' );
		$order->save();

		$order_helper = WC_Stripe_Order_Helper::get_instance();
		$order_helper->update_stripe_fee( $order, '0.59' );
		$order_helper->update_stripe_currency( $order, 'USD' );
		$order->save();

		ob_start();
		$this->gateway->display_order_fee( $order->get_id() );
		$output = ob_get_clean();

		$this->assertStringContainsString( ' USD', $output );
	}

	/**
	 * When Stripe currency matches the order currency, no code is appended to the payout row.
	 */
	public function test_display_order_payout_same_currency_no_code_appended(): void {
		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->save();

		$order_helper = WC_Stripe_Order_Helper::get_instance();
		$order_helper->update_stripe_net( $order, '19.41' );
		$order_helper->update_stripe_currency( $order, 'USD' );
		$order->save();

		ob_start();
		$this->gateway->display_order_payout( $order->get_id() );
		$output = ob_get_clean();

		// No raw currency code appended when currencies match.
		$this->assertStringNotContainsString( ' USD', $output );
	}

	/**
	 * When Stripe currency differs from the order currency, the code is appended to the payout row.
	 */
	public function test_display_order_payout_different_currency_appends_code(): void {
		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'AUD' );
		$order->save();

		$order_helper = WC_Stripe_Order_Helper::get_instance();
		$order_helper->update_stripe_net( $order, '19.41' );
		$order_helper->update_stripe_currency( $order, 'USD' );
		$order->save();

		ob_start();
		$this->gateway->display_order_payout( $order->get_id() );
		$output = ob_get_clean();

		$this->assertStringContainsString( ' USD', $output );
	}
}
