<?php

use Automattic\WooCommerce\Enums\OrderStatus;

/**
 * These tests make assertions against class WC_Stripe_Helper.
 *
 * @package WooCommerce/Stripe/WC_Stripe_Helper
 *
 * WC_Stripe_Helper_Test class.
 */
class WC_Stripe_Helper_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	/**
	 * @var UPE_Test_Helper
	 */
	private $upe_helper;

	public function set_up() {
		parent::set_up();
		$this->upe_helper = new UPE_Test_Helper();
		$this->set_stripe_account_data( [ 'country' => 'US' ] );
	}

	/**
	 * Test for `get_transaction_url_for_id`.
	 *
	 * @param string $id           The Stripe object ID.
	 * @param bool   $is_test_mode Whether to use the test-mode dashboard.
	 * @param string $expected     The expected dashboard URL.
	 * @return void
	 * @dataProvider provide_get_transaction_url_for_id
	 */
	public function test_get_transaction_url_for_id( string $id, bool $is_test_mode, string $expected ) {
		$this->assertSame( $expected, WC_Stripe_Helper::get_transaction_url_for_id( $id, $is_test_mode ) );
	}

	/**
	 * Data provider for `test_get_transaction_url_for_id`.
	 *
	 * @return array
	 */
	public function provide_get_transaction_url_for_id(): array {
		return [
			'live mode' => [ 'pi_123', false, 'https://dashboard.stripe.com/payments/pi_123' ],
			'test mode' => [ 'ch_456', true, 'https://dashboard.stripe.com/test/payments/ch_456' ],
		];
	}

	/**
	 * Test for `convert_wc_locale_to_stripe_locale`.
	 *
	 * @param string $wc_locale     The WooCommerce locale.
	 * @param string $stripe_locale The expected Stripe locale.
	 * @return void
	 * @dataProvider provide_test_convert_to_stripe_locale
	 */
	public function test_convert_to_stripe_locale( string $wc_locale, string $stripe_locale ) {
		$this->assertEquals( $stripe_locale, WC_Stripe_Helper::convert_wc_locale_to_stripe_locale( $wc_locale ) );
	}

	/**
	 * Data provider for `test_convert_to_stripe_locale`.
	 *
	 * @return array
	 */
	public function provide_test_convert_to_stripe_locale(): array {
		return [
			'en_GB → en-GB'  => [ 'en_GB', 'en-GB' ],
			'fr_FR → fr'     => [ 'fr_FR', 'fr' ],
			'fr_CA → fr-CA'  => [ 'fr_CA', 'fr-CA' ],
			'es_UY → es'     => [ 'es_UY', 'es' ],
			'es_EC → es-419' => [ 'es_EC', 'es-419' ],
		];
	}

	public function test_should_enqueue_in_current_tab_section() {
		global $current_tab, $current_section;
		$current_tab     = 'checkout';
		$current_section = 'stripe';

		$result = WC_Stripe_Helper::should_enqueue_in_current_tab_section( 'checkout', 'stripe' );
		$this->assertTrue( $result );

		$result = WC_Stripe_Helper::should_enqueue_in_current_tab_section( 'onboarding', 'stripe' );
		$this->assertFalse( $result );

		unset( $current_tab );
		unset( $current_section );
	}

	/**
	 * Test for `add_payment_method_to_request_array`.
	 *
	 * @param string      $payment_method_id     The payment method ID.
	 * @param string|null $expected_key          The expected key in the request array, or null if empty.
	 * @param string|null $expected_value        The expected value in the request array.
	 * @return void
	 * @dataProvider provide_test_add_payment_method_to_request_array
	 */
	public function test_add_payment_method_to_request_array( string $payment_method_id, ?string $expected_key, ?string $expected_value ) {
		$request = WC_Stripe_Helper::add_payment_method_to_request_array( $payment_method_id, [] );

		if ( null === $expected_key ) {
			$this->assertArrayNotHasKey( 'payment_method', $request );
			$this->assertArrayNotHasKey( 'source', $request );
			$this->assertEmpty( $request );
		} else {
			$this->assertArrayHasKey( $expected_key, $request );
			$this->assertEquals( $expected_value, $request[ $expected_key ] );
		}
	}

	/**
	 * Data provider for `test_add_payment_method_to_request_array`.
	 *
	 * @return array
	 */
	public function provide_test_add_payment_method_to_request_array(): array {
		return [
			'source ID is added under source key'                 => [ 'src_mock', 'source', 'src_mock' ],
			'payment method ID is added under payment_method key' => [ 'pm_mock', 'payment_method', 'pm_mock' ],
			'card ID is added under payment_method key'           => [ 'card_mock', 'payment_method', 'card_mock' ],
			'unknown prefix is not added to the request'          => [ 'cus_mock', null, null ],
		];
	}

	/**
	 * Test for `is_payment_method_object`.
	 *
	 * @param object $input    The object to check.
	 * @param bool   $expected Whether the object is a payment method.
	 * @return void
	 * @dataProvider provide_test_is_payment_method_object
	 */
	public function test_is_payment_method_object( object $input, bool $expected ) {
		$this->assertSame( $expected, WC_Stripe_Helper::is_payment_method_object( $input ) );
	}

	/**
	 * Data provider for `test_is_payment_method_object`.
	 *
	 * @return array
	 */
	public function provide_test_is_payment_method_object(): array {
		$payment_method         = new stdClass();
		$payment_method->object = 'payment_method';

		$empty = new stdClass();

		$not_payment_method         = new stdClass();
		$not_payment_method->object = 'not_payment_method';

		return [
			'object is payment_method'      => [ $payment_method, true ],
			'object has no object property' => [ $empty, false ],
			'object is not payment_method'  => [ $not_payment_method, false ],
		];
	}

	/**
	 * Test for `is_reusable_payment_method`.
	 *
	 * @param object $input    The object to check.
	 * @param bool   $expected Whether the object is a reusable payment method.
	 * @return void
	 * @dataProvider provide_test_is_reusable_source
	 */
	public function test_is_reusable_source( object $input, bool $expected ) {
		$this->assertSame( $expected, WC_Stripe_Helper::is_reusable_payment_method( $input ) );
	}

	/**
	 * Data provider for `test_is_reusable_source`.
	 *
	 * @return array
	 */
	public function provide_test_is_reusable_source(): array {
		$payment_method         = new stdClass();
		$payment_method->object = 'payment_method';

		$reusable_source        = new stdClass();
		$reusable_source->usage = 'reusable';

		$empty = new stdClass();

		$non_reusable_source        = new stdClass();
		$non_reusable_source->usage = 'single_use';

		return [
			'payment_method object is reusable'            => [ $payment_method, true ],
			'source with usage=reusable is reusable'       => [ $reusable_source, true ],
			'empty object is not reusable'                 => [ $empty, false ],
			'source with usage=single_use is not reusable' => [ $non_reusable_source, false ],
		];
	}

	/**
	 * Test for `is_card_payment_method`.
	 *
	 * @param object $input    The object to check.
	 * @param bool   $expected Whether the object is a card payment method.
	 * @return void
	 * @dataProvider provide_test_is_card_payment_method
	 */
	public function test_is_card_payment_method( object $input, bool $expected ) {
		$this->assertSame( $expected, WC_Stripe_Helper::is_card_payment_method( $input ) );
	}

	/**
	 * Data provider for `test_is_card_payment_method`.
	 *
	 * @return array
	 */
	public function provide_test_is_card_payment_method(): array {
		$card_payment_method         = new stdClass();
		$card_payment_method->object = 'payment_method';
		$card_payment_method->type   = WC_Stripe_Payment_Methods::CARD;

		$card_source         = new stdClass();
		$card_source->object = 'source';
		$card_source->type   = WC_Stripe_Payment_Methods::CARD;

		$non_card_payment_method         = new stdClass();
		$non_card_payment_method->object = 'payment_method';
		$non_card_payment_method->type   = 'not_card';

		$non_card_source         = new stdClass();
		$non_card_source->object = 'source';
		$non_card_source->type   = 'not_card';

		$not_payment_method_or_source         = new stdClass();
		$not_payment_method_or_source->object = 'not_payment_method_or_source';

		return [
			'card payment method object'                  => [ $card_payment_method, true ],
			'card source object'                          => [ $card_source, true ],
			'non-card payment method object'              => [ $non_card_payment_method, false ],
			'non-card source object'                      => [ $non_card_source, false ],
			'object is neither payment_method nor source' => [ $not_payment_method_or_source, false ],
		];
	}

	/**
	 * Test for `get_payment_method_from_intent`.
	 *
	 * @param object $intent   The intent object.
	 * @param mixed  $expected The expected result.
	 * @return void
	 * @dataProvider provide_test_get_payment_method_from_intent
	 */
	public function test_get_payment_method_from_intent( object $intent, $expected ) {
		$this->assertSame( $expected, WC_Stripe_Helper::get_payment_method_from_intent( $intent ) );
	}

	/**
	 * Data provider for `test_get_payment_method_from_intent`.
	 *
	 * @return array
	 */
	public function provide_test_get_payment_method_from_intent(): array {
		$intent_with_source         = new stdClass();
		$intent_with_source->source = 'src_mock';

		$intent_with_payment_method                 = new stdClass();
		$intent_with_payment_method->payment_method = 'pm_mock';

		$intent_with_neither = new stdClass();

		return [
			'intent with source returns source'                          => [ $intent_with_source, 'src_mock' ],
			'intent with payment_method returns payment_method'          => [ $intent_with_payment_method, 'pm_mock' ],
			'intent with neither source nor payment_method returns null' => [ $intent_with_neither, null ],
		];
	}

	/**
	 * Test for `get_order_by_intent_id`
	 *
	 * @param string      $type           The order type to return - either 'order' or 'refund'.
	 * @param string|null $status         The order status to return.
	 * @param bool        $use_hpos       Whether to use HPOS.
	 * @param bool        $expect_success Whether the order should be found.
	 * @return void
	 * @dataProvider provide_test_get_order_by_intent_id
	 */
	public function test_get_order_by_intent_id( string $type, ?string $status, bool $use_hpos, bool $expect_success ) {
		$previous_hpos_setting = get_option( 'woocommerce_custom_orders_table_enabled', 'no' );
		$new_hpos_setting      = $use_hpos ? 'yes' : 'no';

		try {

			// Allow HPOS to be toggled regardless of database state.
			add_filter( 'wc_allow_changing_orders_storage_while_sync_is_pending', '__return_true' );

			if ( $new_hpos_setting !== $previous_hpos_setting ) {
				update_option( 'woocommerce_custom_orders_table_enabled', $new_hpos_setting );
			}

			$order     = WC_Helper_Order::create_order();
			$order_id  = $order->get_id();
			$intent_id = 'pi_mock';

			if ( 'order' === $type ) {
				$order = wc_get_order( $order_id );
				$order->set_status( $status );
				$order->save();

				WC_Stripe_Order_Helper::get_instance()->update_stripe_intent_id( $order, $intent_id );
				$order->save_meta_data();
			} elseif ( 'refund' === $type ) {
				$refund = wc_create_refund(
					[
						'amount'   => $order->get_total(),
						'currency' => $order->get_currency(),
						'order_id' => $order->get_id(),
						'reason'   => 'Test refund',
					]
				);

				$intent_meta_key = WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Order_Helper::class, 'META_STRIPE_INTENT_ID', 'string' );

				$refund->update_meta_data( $intent_meta_key, $intent_id );
				$refund->save_meta_data();
			} else {
				throw new Exception( 'Invalid order type' );
			}

			$order = WC_Stripe_Helper::get_order_by_intent_id( $intent_id );
			if ( $expect_success ) {
				$this->assertInstanceOf( WC_Order::class, $order );
			} else {
				$this->assertFalse( $order );
			}
		} finally {
			if ( $new_hpos_setting !== $previous_hpos_setting ) {
				update_option( 'woocommerce_custom_orders_table_enabled', $previous_hpos_setting );
			}
			remove_filter( 'wc_allow_changing_orders_storage_while_sync_is_pending', '__return_true' );
		}
	}

	/**
	 * Data provider for `test_get_order_by_intent_id`
	 *
	 * @return array
	 */
	public function provide_test_get_order_by_intent_id(): array {
		return [
			'completed legacy order' => [
				'type'           => 'order',
				'status'         => OrderStatus::COMPLETED,
				'use_hpos'       => false,
				'expect_success' => true,
			],
			'completed HPOS order'   => [
				'type'           => 'order',
				'status'         => OrderStatus::COMPLETED,
				'use_hpos'       => true,
				'expect_success' => true,
			],
			'trashed legacy order'   => [
				'type'           => 'order',
				'status'         => OrderStatus::TRASH,
				'use_hpos'       => false,
				'expect_success' => false,
			],
			'trashed HPOS order'     => [
				'type'           => 'order',
				'status'         => OrderStatus::TRASH,
				'use_hpos'       => true,
				'expect_success' => false,
			],
			'legacy refund'          => [
				'type'           => 'refund',
				'status'         => null,
				'use_hpos'       => false,
				'expect_success' => false,
			],
			'HPOS refund'            => [
				'type'           => 'refund',
				'status'         => null,
				'use_hpos'       => true,
				'expect_success' => false,
			],
		];
	}

	/**
	 * Test for `get_order_by_setup_intent_id`.
	 *
	 * @param string|null $intent_target  Which object to save the SetupIntent ID on. One of null, 'refund', or 'order'.
	 * @param bool        $use_hpos       Whether to enable or disable HPOS.
	 * @param bool        $expect_success Whether an order should be returned.
	 * @dataProvider provide_test_get_order_by_setup_intent_id
	 */
	public function test_get_order_by_setup_intent_id( ?string $intent_target, bool $use_hpos, bool $expect_success ): void {
		$previous_hpos_setting = get_option( 'woocommerce_custom_orders_table_enabled', 'no' );
		$new_hpos_setting      = $use_hpos ? 'yes' : 'no';
		try {
			// Allow HPOS to be toggled regardless of database state.
			add_filter( 'wc_allow_changing_orders_storage_while_sync_is_pending', '__return_true' );

			if ( $new_hpos_setting !== $previous_hpos_setting ) {
				update_option( 'woocommerce_custom_orders_table_enabled', $new_hpos_setting );
			}

			$order     = WC_Helper_Order::create_order();
			$intent_id = 'seti_mock';
			$lookup_id = $intent_id;

			if ( 'refund' === $intent_target ) {
				$refund = wc_create_refund(
					[
						'amount'   => $order->get_total(),
						'currency' => $order->get_currency(),
						'order_id' => $order->get_id(),
						'reason'   => 'Test refund',
					]
				);

				$setup_intent_meta_key = WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Order_Helper::class, 'META_STRIPE_SETUP_INTENT', 'string' );

				$refund->update_meta_data( $setup_intent_meta_key, $intent_id );
				$refund->save_meta_data();
			} elseif ( 'order' === $intent_target ) {
				WC_Stripe_Order_Helper::get_instance()->update_stripe_setup_intent_id( $order, $intent_id );
				$order->save_meta_data();
			} elseif ( null === $intent_target ) {
				$lookup_id = 'seti_unknown';
			} else {
				throw new Exception( 'Invalid intent target' );
			}

			$result = WC_Stripe_Helper::get_order_by_setup_intent_id( $lookup_id );

			if ( $expect_success ) {
				$this->assertInstanceOf( WC_Order::class, $result );
				$this->assertSame( $order->get_id(), $result->get_id() );
			} else {
				$this->assertFalse( $result );
			}
		} finally {
			if ( $new_hpos_setting !== $previous_hpos_setting ) {
				update_option( 'woocommerce_custom_orders_table_enabled', $previous_hpos_setting );
			}
			remove_filter( 'wc_allow_changing_orders_storage_while_sync_is_pending', '__return_true' );
		}
	}

	/**
	 * Data provider for `test_get_order_by_setup_intent_id`.
	 *
	 * @return array
	 */
	public function provide_test_get_order_by_setup_intent_id(): array {
		return [
			'SetupIntent matches legacy order'  => [
				'intent_target'  => 'order',
				'use_hpos'       => false,
				'expect_success' => true,
			],
			'SetupIntent matches HPOS order'    => [
				'intent_target'  => 'order',
				'use_hpos'       => true,
				'expect_success' => true,
			],
			'SetupIntent matches legacy refund' => [
				'intent_target'  => 'refund',
				'use_hpos'       => false,
				'expect_success' => false,
			],
			'SetupIntent matches HPOS refund'   => [
				'intent_target'  => 'refund',
				'use_hpos'       => true,
				'expect_success' => false,
			],
			'unknown SetupIntent ID HPOS'       => [
				'intent_target'  => null,
				'use_hpos'       => true,
				'expect_success' => false,
			],
			'unknown SetupIntent ID legacy'     => [
				'intent_target'  => null,
				'use_hpos'       => false,
				'expect_success' => false,
			],
		];
	}

	/**
	 * Test for `get_stripe_amount`
	 *
	 * @param int    $total    The total amount.
	 * @param string $currency The currency.
	 * @param int    $expected The expected amount.
	 * @dataProvider provide_test_get_stripe_amount
	 */
	public function test_get_stripe_amount( int $total, string $currency, int $expected, int $price_decimals_setting = 2 ): void {
		if ( 2 !== $price_decimals_setting ) {
			update_option( 'woocommerce_price_num_decimals', $price_decimals_setting );
		}

		$amount = WC_Stripe_Helper::get_stripe_amount( $total, $currency );
		$this->assertEquals( $expected, $amount );
	}

	/**
	 * Data provider for `test_get_stripe_amount`
	 *
	 * @return array
	 */
	public function provide_test_get_stripe_amount(): array {
		return [
			WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR             => [
				'total'    => 100,
				'currency' => WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR,
				'expected' => 10000,
			],
			WC_Stripe_Currency_Code::JAPANESE_YEN                     => [
				'total'    => 100,
				'currency' => WC_Stripe_Currency_Code::JAPANESE_YEN,
				'expected' => 100,
			],
			WC_Stripe_Currency_Code::EURO                             => [
				'total'    => 100,
				'currency' => WC_Stripe_Currency_Code::EURO,
				'expected' => 10000,
			],
			WC_Stripe_Currency_Code::BAHRAINI_DINAR                   => [
				'total'    => 100,
				'currency' => WC_Stripe_Currency_Code::BAHRAINI_DINAR,
				'expected' => 100000,
			],
			WC_Stripe_Currency_Code::BAHRAINI_DINAR . ' (3 decimals)' => [
				'total'                  => 100,
				'currency'               => WC_Stripe_Currency_Code::BAHRAINI_DINAR,
				'expected'               => 100000,
				'price_decimals_setting' => 3,
			],
			WC_Stripe_Currency_Code::JORDANIAN_DINAR                  => [
				'total'    => 100,
				'currency' => WC_Stripe_Currency_Code::JORDANIAN_DINAR,
				'expected' => 100000,
			],
			WC_Stripe_Currency_Code::BURUNDIAN_FRANC                  => [
				'total'    => 100,
				'currency' => WC_Stripe_Currency_Code::BURUNDIAN_FRANC,
				'expected' => 100,
			],
		];
	}

	/**
	 * @dataProvider provide_test_convert_from_stripe_amount
	 */
	public function test_convert_from_stripe_amount( int $stripe_amount, string $currency, float $expected ): void {
		$result = WC_Stripe_Helper::convert_from_stripe_amount( $stripe_amount, $currency );
		$this->assertSame( $expected, $result );
	}

	/**
	 * Test for `get_payment_intent_description`.
	 */
	public function test_get_payment_intent_description(): void {
		$order = WC_Helper_Order::create_order();

		$expected = sprintf( '%1$s - Order %2$s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() );
		$this->assertSame( $expected, WC_Stripe_Helper::get_payment_intent_description( $order ) );
	}

	/**
	 * Tax recorded on the order but left out of a stale (pre-tax) grand total must be folded back in,
	 * and the corrected total persisted so the charge and the stored order stay consistent.
	 */
	public function test_recalculate_order_total_folds_in_stale_tax(): void {
		$tax   = 9.0;
		$order = WC_Helper_Order::create_order();
		$net   = (float) $order->get_subtotal() + (float) $order->get_shipping_total();

		// Buggy persisted state: tax recorded on the order, grand total stale (pre-tax).
		$order->set_cart_tax( $tax );
		$order->set_shipping_tax( 0 );
		$order->set_total( $net );
		$order->save();

		$result = WC_Stripe_Helper::recalculate_order_total( $order );

		$this->assertSame( $net + $tax, $result );
		// Persisted, so a later read of the order reflects the tax-inclusive total.
		$this->assertSame( $net + $tax, (float) wc_get_order( $order->get_id() )->get_total() );
	}

	/**
	 * An order whose total already matches its line items must be returned unchanged — re-summing
	 * stored tax must not double-count it.
	 */
	public function test_recalculate_order_total_is_idempotent_for_correct_order(): void {
		$order    = WC_Helper_Order::create_order();
		$expected = (float) $order->get_total();

		$this->assertSame( $expected, WC_Stripe_Helper::recalculate_order_total( $order ) );
		$this->assertSame( $expected, WC_Stripe_Helper::recalculate_order_total( $order ) );
	}

	/**
	 * Test for `get_woocommerce_amount_from_stripe_amount` (Stripe → WooCommerce amount conversion).
	 *
	 * @param int|string $stripe_amount Stripe amount in smallest unit (cents, etc.).
	 * @param string     $currency      Currency code.
	 * @param string     $expected      Expected WooCommerce formatted amount string.
	 * @dataProvider provide_test_get_woocommerce_amount_from_stripe_amount
	 */
	public function test_get_woocommerce_amount_from_stripe_amount( $stripe_amount, string $currency, string $expected ): void {
		$result = WC_Stripe_Helper::get_woocommerce_amount_from_stripe_amount( $stripe_amount, $currency );
		$this->assertIsString( $result );
		$this->assertSame( $expected, $result );
	}

	/**
	 * Data provider for `test_convert_from_stripe_amount`.
	 *
	 * @return array
	 */
	public function provide_test_convert_from_stripe_amount(): array {
		return [
			'USD standard'            => [
				'stripe_amount' => 10000,
				'currency'      => WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR,
				'expected'      => 100.00,
			],
			'USD small amount'        => [
				'stripe_amount' => 99,
				'currency'      => WC_Stripe_Currency_Code::EURO,
				'expected'      => 0.99,
			],
			'JPY no-decimal'          => [
				'stripe_amount' => 1000,
				'currency'      => WC_Stripe_Currency_Code::JAPANESE_YEN,
				'expected'      => 1000.0,
			],
			'BIF no-decimal'          => [
				'stripe_amount' => 100,
				'currency'      => WC_Stripe_Currency_Code::BURUNDIAN_FRANC,
				'expected'      => 100.0,
			],
			'BHD three-decimal'       => [
				'stripe_amount' => 100000,
				'currency'      => WC_Stripe_Currency_Code::BAHRAINI_DINAR,
				'expected'      => 100.0,
			],
			'JOD three-decimal'       => [
				'stripe_amount' => 1000,
				'currency'      => WC_Stripe_Currency_Code::JORDANIAN_DINAR,
				'expected'      => 1.0,
			],
			'zero amount'             => [
				'stripe_amount' => 0,
				'currency'      => WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR,
				'expected'      => 0.0,
			],
			'uppercase currency code' => [
				'stripe_amount' => 500,
				'currency'      => 'USD',
				'expected'      => 5.00,
			],
		];
	}

	/**
	 * Data provider for `test_get_woocommerce_amount_from_stripe_amount`.
	 *
	 * Covers two-decimal, zero-decimal, and three-decimal currencies plus edge cases.
	 *
	 * @return array
	 */
	public function provide_test_get_woocommerce_amount_from_stripe_amount(): array {
		return [
			'USD two-decimal: 10000 cents'           => [
				'stripe_amount' => 10000,
				'currency'      => 'usd',
				'expected'      => '100.00',
			],
			'USD two-decimal: 10050 cents'           => [
				'stripe_amount' => 10050,
				'currency'      => 'usd',
				'expected'      => '100.50',
			],
			'USD two-decimal: 1 cent'                => [
				'stripe_amount' => 1,
				'currency'      => 'usd',
				'expected'      => '0.01',
			],
			'USD two-decimal: zero'                  => [
				'stripe_amount' => 0,
				'currency'      => 'usd',
				'expected'      => '0.00',
			],
			'USD currency case insensitivity'        => [
				'stripe_amount' => 10000,
				'currency'      => 'USD',
				'expected'      => '100.00',
			],
			'JPY no-decimal: whole units'            => [
				'stripe_amount' => 100,
				'currency'      => 'jpy',
				'expected'      => '100',
			],
			'JPY no-decimal: single unit'            => [
				'stripe_amount' => 1,
				'currency'      => 'jpy',
				'expected'      => '1',
			],
			'JPY no-decimal: zero'                   => [
				'stripe_amount' => 0,
				'currency'      => 'jpy',
				'expected'      => '0',
			],
			'BHD three-decimal: 5 fil (single unit)' => [
				'stripe_amount' => 5,
				'currency'      => 'bhd',
				'expected'      => '0.005',
			],
			'BHD three-decimal: 100 fils'            => [
				'stripe_amount' => 100,
				'currency'      => 'bhd',
				'expected'      => '0.100',
			],
			'BHD three-decimal: 100500 fils'         => [
				'stripe_amount' => 100500,
				'currency'      => 'bhd',
				'expected'      => '100.500',
			],
			'BHD three-decimal: 0'                   => [
				'stripe_amount' => 0,
				'currency'      => 'bhd',
				'expected'      => '0.000',
			],
		];
	}

	/**
	 * Test for `get_woocommerce_amount_from_stripe_amount` with empty currency (uses store currency).
	 */
	public function test_get_woocommerce_amount_from_stripe_amount_falls_back_to_store_currency(): void {
		$original_currency = get_option( 'woocommerce_currency' );
		update_option( 'woocommerce_currency', 'EUR' );

		$result = WC_Stripe_Helper::get_woocommerce_amount_from_stripe_amount( 19999, '' );

		// Restore original currency.
		update_option( 'woocommerce_currency', $original_currency );

		$this->assertIsString( $result );
		$this->assertSame( '199.99', $result );
	}

	/**
	 * Test for `payment_method_allows_manual_capture`
	 *
	 * @param string $payment_method The payment method.
	 * @param bool   $expected       Whether manual capture is allowed.
	 * @dataProvider provide_payment_method_allows_manual_capture
	 * @return void
	 */
	public function test_payment_method_allows_manual_capture( $payment_method, $expected ): void {
		$actual = WC_Stripe_Helper::payment_method_allows_manual_capture( $payment_method );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Provider for `test_payment_method_allows_manual_capture`
	 *
	 * @return array
	 */
	public function provide_payment_method_allows_manual_capture(): array {
		return [
			'Card'              => [
				'payment_method' => 'stripe',
				'expected'       => true,
			],
			'Affirm'            => [
				'payment_method' => 'stripe_affirm',
				'expected'       => true,
			],
			'Klarna'            => [
				'payment_method' => 'stripe_klarna',
				'expected'       => true,
			],
			'Afterpay/Clearpay' => [
				'payment_method' => 'stripe_afterpay_clearpay',
				'expected'       => true,
			],
			'EPS'               => [
				'payment_method' => 'stripe_eps',
				'expected'       => false,
			],
			'AmazonPay'         => [
				'payment_method' => 'stripe_amazon_pay',
				'expected'       => true,
			],
		];
	}

	public function provide_is_wallet_payment_method(): array {
		return [
			'Apple Pay'  => [
				'apple_pay',
				false,
			],
			'Google Pay' => [
				'google_pay',
				false,
			],
			'Alipay'     => [
				WC_Stripe_Payment_Methods::ALIPAY,
				false,
			],
			'Klarna'     => [
				WC_Stripe_Payment_Methods::KLARNA,
				false,
			],
			'EPS'        => [
				WC_Stripe_Payment_Methods::EPS,
				false,
			],
			'WeChat'     => [
				WC_Stripe_Payment_Methods::WECHAT_PAY,
				true,
			],
			'Cash App'   => [
				WC_Stripe_Payment_Methods::CASHAPP_PAY,
				true,
			],
		];
	}

	/**
	 * Test for `update_main_stripe_settings`, `get_stripe_settings` and `delete_main_stripe_settings`.
	 *
	 * @return void
	 */
	public function test_handle_main_stripe_settings() {
		WC_Stripe_Helper::update_main_stripe_settings( [ 'test' => 'abc' ] );
		$current_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( $current_settings['test'], 'abc' );

		WC_Stripe_Helper::delete_main_stripe_settings();
		$current_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( [], $current_settings );
	}

	/**
	 * Test for `get_klarna_preferred_locale`.
	 * @return void
	 */
	public function test_get_klarna_preferred_locale() {
		// Language is supported for the region (same region)
		$store_locale    = 'en_US';
		$billing_country = 'US';
		$expected        = 'en-US';
		$actual          = WC_Stripe_Helper::get_klarna_preferred_locale( $store_locale, $billing_country );
		$this->assertSame( $expected, $actual );

		// Language is supported for the region (different region)
		$store_locale    = 'en_US';
		$billing_country = 'DE';
		$expected        = 'en-DE';
		$actual          = WC_Stripe_Helper::get_klarna_preferred_locale( $store_locale, $billing_country );
		$this->assertSame( $expected, $actual );

		// Language is supported for the region (different region)
		$store_locale    = 'es_ES';
		$billing_country = 'US';
		$expected        = 'es-US';
		$actual          = WC_Stripe_Helper::get_klarna_preferred_locale( $store_locale, $billing_country );
		$this->assertSame( $expected, $actual );

		// Language is not supported for the region
		$store_locale    = 'fr_FR';
		$billing_country = 'US';
		$actual          = WC_Stripe_Helper::get_klarna_preferred_locale( $store_locale, $billing_country );
		$this->assertNull( $actual );

		// Region is not supported, with supported locale
		$store_locale    = 'pt_PT';
		$billing_country = 'BR';
		$actual          = WC_Stripe_Helper::get_klarna_preferred_locale( $store_locale, $billing_country );
		$this->assertNull( $actual );

		// Region is not supported, with non-supported locale
		$store_locale    = 'tl';
		$billing_country = 'PH';
		$actual          = WC_Stripe_Helper::get_klarna_preferred_locale( $store_locale, $billing_country );
		$this->assertNull( $actual );
	}

	/**
	 * Test for `add_stripe_methods_in_woocommerce_gateway_order`.
	 * @return void
	 */
	public function test_add_stripe_methods_in_woocommerce_gateway_order() {
		// When the option is empty, i.e. fresh install, gateway ordering should still work.
		$stripe_payment_methods = [
			'stripe_klarna',
			'card',
			'stripe_alipay',
		];
		delete_option( 'woocommerce_gateway_order' );
		WC_Stripe_Helper::add_stripe_methods_in_woocommerce_gateway_order( $stripe_payment_methods );
		$gateway_order = get_option( 'woocommerce_gateway_order', [] );
		$this->assertArrayHasKey( 'stripe_klarna', $gateway_order );
		$this->assertArrayHasKey( 'stripe', $gateway_order );
		$this->assertArrayHasKey( 'stripe_alipay', $gateway_order );
		$this->assertTrue( $gateway_order['stripe_klarna'] < $gateway_order['stripe'] );
		$this->assertTrue( $gateway_order['stripe'] < $gateway_order['stripe_alipay'] );

		// Further updates to gateway ordering should work.
		$stripe_payment_methods = [
			'stripe_klarna',
			'stripe_alipay',
			'card',
		];
		WC_Stripe_Helper::add_stripe_methods_in_woocommerce_gateway_order( $stripe_payment_methods );
		$gateway_order = get_option( 'woocommerce_gateway_order', [] );
		$this->assertArrayHasKey( 'stripe_klarna', $gateway_order );
		$this->assertArrayHasKey( 'stripe', $gateway_order );
		$this->assertArrayHasKey( 'stripe_alipay', $gateway_order );
		$this->assertTrue( $gateway_order['stripe_klarna'] < $gateway_order['stripe_alipay'] );
		$this->assertTrue( $gateway_order['stripe_alipay'] < $gateway_order['stripe'] );

		// Order with respect to other gateways is retained.
		update_option(
			'woocommerce_gateway_order',
			[
				'cod'           => 1,
				'stripe_klarna' => 2,
				'stripe'        => 3,
				'stripe_alipay' => 4,
				'cheque'        => 5,
			]
		);
		$stripe_payment_methods = [
			'stripe_alipay',
			'stripe_klarna',
			'card',
			'stripe_affirm',
		];
		WC_Stripe_Helper::add_stripe_methods_in_woocommerce_gateway_order( $stripe_payment_methods );
		$gateway_order = get_option( 'woocommerce_gateway_order', [] );
		$this->assertTrue( $gateway_order['cod'] < $gateway_order['stripe_alipay'] );
		$this->assertTrue( $gateway_order['stripe_alipay'] < $gateway_order['stripe_klarna'] );
		$this->assertTrue( $gateway_order['stripe_klarna'] < $gateway_order['stripe'] );
		$this->assertTrue( $gateway_order['stripe'] < $gateway_order['stripe_affirm'] );
		$this->assertTrue( $gateway_order['stripe_affirm'] < $gateway_order['cheque'] );
		$this->assertSame( 'yes', get_option( 'wc_stripe_show_stripe_first_method_notice' ) );
	}

	/**
	 * Test for `is_stripe_in_position_one_in_woocommerce_gateway_order`.
	 *
	 * @param array $payment_gateways Stub for `WC()->payment_gateways->payment_gateways` (id ⇒ enabled).
	 *                                Iteration order matches WC's sorted-by-position order.
	 * @param bool  $expected         Expected result.
	 * @dataProvider provide_test_is_stripe_in_position_one_in_woocommerce_gateway_order
	 */
	public function test_is_stripe_in_position_one_in_woocommerce_gateway_order( array $payment_gateways, bool $expected ) {
		$original_payment_gateways               = WC()->payment_gateways->payment_gateways;
		WC()->payment_gateways->payment_gateways = $this->build_gateway_stubs( $payment_gateways );

		try {
			$this->assertSame(
				$expected,
				WC_Stripe_Helper::is_stripe_in_position_one_in_woocommerce_gateway_order()
			);
		} finally {
			WC()->payment_gateways->payment_gateways = $original_payment_gateways;
		}
	}

	/**
	 * Builds an array of gateway stub objects from an `[ id => enabled_bool ]` map.
	 *
	 * @param array $map Gateway id → enabled bool.
	 * @return array Stub objects with `id` and `enabled` properties keyed by gateway id.
	 */
	private function build_gateway_stubs( array $map ): array {
		$stubs = [];
		foreach ( $map as $id => $enabled ) {
			$stubs[ $id ] = (object) [
				'id'      => $id,
				'enabled' => $enabled ? 'yes' : 'no',
			];
		}
		return $stubs;
	}

	/**
	 * Data provider for `test_is_stripe_in_position_one_in_woocommerce_gateway_order`.
	 *
	 * @return array
	 */
	public function provide_test_is_stripe_in_position_one_in_woocommerce_gateway_order(): array {
		return [
			'stripe is first'                              => [
				'payment_gateways' => [
					'stripe' => true,
					'cod'    => true,
					'bacs'   => true,
				],
				'expected'         => true,
			],
			'stripe is not first'                          => [
				'payment_gateways' => [
					'cod'    => true,
					'stripe' => true,
					'bacs'   => true,
				],
				'expected'         => false,
			],
			'stripe missing from loaded gateways'          => [
				'payment_gateways' => [
					'cod'  => true,
					'bacs' => true,
				],
				'expected'         => false,
			],
			'no loaded gateways'                           => [
				'payment_gateways' => [],
				'expected'         => true,
			],
			'all methods above stripe are disabled'        => [
				'payment_gateways' => [
					'cod'    => false,
					'bacs'   => false,
					'stripe' => true,
				],
				'expected'         => true,
			],
			'mixed enabled/disabled above stripe'          => [
				'payment_gateways' => [
					'cod'    => false,
					'bacs'   => true,
					'stripe' => true,
				],
				'expected'         => false,
			],
			'stripe APM (stripe_klarna) is first'          => [
				'payment_gateways' => [
					'stripe_klarna' => true,
					'stripe'        => true,
					'cod'           => true,
				],
				'expected'         => true,
			],
			'stripe disabled at top still counts as first' => [
				'payment_gateways' => [
					'stripe' => false,
					'cod'    => true,
				],
				'expected'         => true,
			],
		];
	}

	/**
	 * Test for `should_show_stripe_first_method_notice`.
	 *
	 * @param string|null $notice_option    Value for `wc_stripe_show_stripe_first_method_notice`, or null to delete (default yes).
	 * @param array       $payment_gateways Stub for `WC()->payment_gateways->payment_gateways` (id ⇒ enabled).
	 * @param bool        $expected         Expected return value.
	 * @dataProvider provide_test_should_show_stripe_first_method_notice
	 */
	public function test_should_show_stripe_first_method_notice( ?string $notice_option, array $payment_gateways, bool $expected ): void {
		if ( null === $notice_option ) {
			delete_option( 'wc_stripe_show_stripe_first_method_notice' );
		} else {
			update_option( 'wc_stripe_show_stripe_first_method_notice', $notice_option );
		}

		$original_payment_gateways               = WC()->payment_gateways->payment_gateways;
		WC()->payment_gateways->payment_gateways = $this->build_gateway_stubs( $payment_gateways );

		try {
			$this->assertSame( $expected, WC_Stripe_Helper::should_show_stripe_first_method_notice() );
		} finally {
			WC()->payment_gateways->payment_gateways = $original_payment_gateways;
		}
	}

	/**
	 * Data provider for `test_should_show_stripe_first_method_notice`.
	 *
	 * @return array
	 */
	public function provide_test_should_show_stripe_first_method_notice(): array {
		return [
			'notice dismissed'                                     => [
				'notice_option'    => 'no',
				'payment_gateways' => [
					'cod'    => true,
					'stripe' => true,
				],
				'expected'         => false,
			],
			'notice enabled and stripe is first'                   => [
				'notice_option'    => 'yes',
				'payment_gateways' => [
					'stripe' => true,
					'cod'    => true,
				],
				'expected'         => false,
			],
			'notice enabled default and stripe is first'           => [
				'notice_option'    => null,
				'payment_gateways' => [
					'stripe' => true,
					'cod'    => true,
				],
				'expected'         => false,
			],
			'notice enabled and stripe is not first'               => [
				'notice_option'    => 'yes',
				'payment_gateways' => [
					'cod'    => true,
					'stripe' => true,
				],
				'expected'         => true,
			],
			'notice enabled default and stripe is not first'       => [
				'notice_option'    => null,
				'payment_gateways' => [
					'cod'    => true,
					'stripe' => true,
				],
				'expected'         => true,
			],
			'notice enabled and no loaded gateways'                => [
				'notice_option'    => 'yes',
				'payment_gateways' => [],
				'expected'         => false,
			],
			'notice hidden when methods above stripe are disabled' => [
				'notice_option'    => 'yes',
				'payment_gateways' => [
					'cod'    => false,
					'bacs'   => false,
					'stripe' => true,
				],
				'expected'         => false,
			],
		];
	}

	/**
	 * Test for `move_stripe_gateways_to_top_in_woocommerce_gateway_order`.
	 */
	public function test_move_stripe_gateways_to_top_in_woocommerce_gateway_order() {
		update_option(
			'woocommerce_gateway_order',
			[
				'affirm'       => '0',
				'woopayments'  => '1',
				'amazon_pay'   => '2',
				'stripe_sepa'  => '3',
				'stripe_ideal' => '4',
				'stripe'       => '5',
				'stripe_eps'   => '6',
				'cod'          => '7',
				'paypal'       => '8',
			]
		);

		WC_Stripe_Helper::move_stripe_gateways_to_top_in_woocommerce_gateway_order();
		$gateway_order = get_option( 'woocommerce_gateway_order', [] );

		$this->assertSame(
			[
				'stripe_sepa'  => '0',
				'stripe_ideal' => '1',
				'stripe'       => '2',
				'stripe_eps'   => '3',
				'affirm'       => '4',
				'woopayments'  => '5',
				'amazon_pay'   => '6',
				'cod'          => '7',
				'paypal'       => '8',
			],
			$gateway_order
		);
	}

	/**
	 * Test for `add_mandate_data`.
	 *
	 * @param string $server_variable_key   The key of the server variable to set.
	 * @param string $server_variable_value The value to set the server variable to.
	 * @param string $expected_ip_address    The expected IP address.
	 * @dataProvider provider_test_add_mandate_data
	 * @return void
	 */
	public function test_add_mandate_data( $server_variable_key, $server_variable_value, $expected_ip_address ) {
		unset( $_SERVER['REMOTE_ADDR'] );
		unset( $_SERVER['HTTP_X_REAL_IP'] );
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

		$_SERVER[ $server_variable_key ] = $server_variable_value;
		$request                         = WC_Stripe_Helper::add_mandate_data( [] );
		$this->assertTrue( isset( $request['mandate_data']['customer_acceptance']['online']['ip_address'] ) );
		$ip_address = $request['mandate_data']['customer_acceptance']['online']['ip_address'];
		$this->assertSame( $expected_ip_address, $ip_address );
	}

	/**
	 * Data provider for `test_add_mandate_data`.
	 *
	 * @return array
	 */
	public function provider_test_add_mandate_data() {
		return [
			[ 'REMOTE_ADDR', '192.168.1.1', '192.168.1.1' ],
			[ 'REMOTE_ADDR', '192.168.1.1, 192.168.1.2, 192.168.1.3', '192.168.1.1' ],
			[ 'HTTP_X_REAL_IP', '192.168.1.1', '192.168.1.1' ],
			[ 'HTTP_X_REAL_IP', '192.168.1.1, 192.168.1.2, 192.168.1.3', '192.168.1.1' ],
			[ 'HTTP_X_FORWARDED_FOR', '192.168.1.1, 192.168.1.2, 192.168.1.3', '192.168.1.1' ],
			[ 'HTTP_X_FORWARDED_FOR', '192.168.1.1', '192.168.1.1' ],
			[ 'HTTP_X_REAL_IP', 'invalid-ip-address', 'invalid-ip-address' ],
			[ 'HTTP_X_REAL_IP', '', '' ],
		];
	}

	/**
	 * Tests for `get_refund_reason_description`.
	 *
	 * @param string $refund_reason_key The refund reason key to test.
	 * @param string $expected          The expected description.
	 * @return void
	 *
	 * @dataProvider provide_test_get_refund_reason_description
	 */
	public function test_get_refund_reason_description( $refund_reason_key, $expected ) {
		$this->assertSame( $expected, WC_Stripe_Helper::get_refund_reason_description( $refund_reason_key ) );
	}

	/**
	 * Data provider for `test_get_refund_reason_description`.
	 *
	 * @return array
	 */
	public function provide_test_get_refund_reason_description() {
		return [
			'The charge has been disputed'                            => [
				'key'      => 'charge_for_pending_refund_disputed',
				'expected' => 'The charge has been disputed',
			],
			'The refund was declined'                                 => [
				'key'      => 'declined',
				'expected' => 'The refund was declined',
			],
			'The original payment method has expired or was canceled' => [
				'key'      => 'expired_or_canceled_card',
				'expected' => 'The original payment method has expired or was canceled',
			],
			'We could not process the refund at this time'            => [
				'key'      => 'insufficient_funds',
				'expected' => 'We could not process the refund at this time',
			],
			'The original payment method was lost or stolen'          => [
				'key'      => 'lost_or_stolen_card',
				'expected' => 'The original payment method was lost or stolen',
			],
			'We stopped processing the refund'                        => [
				'key'      => 'merchant_request',
				'expected' => 'We stopped processing the refund',
			],
			'Unknown reason (random)'                                 => [
				'key'      => 'random',
				'expected' => 'Unknown reason',
			],
			'Unknown reason (null)'                                   => [
				'key'      => null,
				'expected' => 'Unknown reason',
			],
			'Unknown reason (empty)'                                  => [
				'key'      => '',
				'expected' => 'Unknown reason',
			],
		];
	}

	/**
	 * Tests for `has_other_bnpl_plugins_active`.
	 *
	 * @param array $payment_gateways The available payment gateways.
	 * @param bool  $expected         The expected result.
	 * @dataProvider provide_test_has_other_bnpl_plugins_active
	 * @return void
	 */
	public function test_has_other_bnpl_plugins_active( $payment_gateways, $expected ) {
		$original_payment_gateways = WC()->payment_gateways->payment_gateways;

		// Mock the available payment gateways.
		WC()->payment_gateways->payment_gateways = $payment_gateways;

		$actual = WC_Stripe_Helper::has_other_bnpl_plugins_active();

		// Clean up.
		WC()->payment_gateways->payment_gateways = $original_payment_gateways;

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Provider for `test_has_other_bnpl_plugins_active`.
	 *
	 * @return array
	 */
	public function provide_test_has_other_bnpl_plugins_active() {
		return [
			'has other plugins'           => [
				'payment gateways' => [
					'klarna' => (object) [
						'id'      => 'klarna_payments',
						'enabled' => 'yes',
					],
					'affirm' => (object) [
						'id'      => 'affirm',
						'enabled' => 'yes',
					],
				],
				'expected'         => true,
			],
			'does not have other plugins' => [
				'payment gateways' => [],
				'expected'         => false,
			],
		];
	}

	/**
	 * Tests for `has_gateway_plugin_active`.
	 *
	 * @param string $plugin_id The plugin ID to evaluate.
	 * @param array $payment_gateways The available payment gateways.
	 * @param bool $expected The expected result.
	 * @return void
	 *
	 * @dataProvider provide_has_gateway_plugin_active
	 */
	public function test_has_gateway_plugin_active( $plugin_id, $payment_gateways, $expected ) {
		$original_payment_gateways = WC()->payment_gateways->payment_gateways;

		// Mock the available payment gateways.
		WC()->payment_gateways->payment_gateways = $payment_gateways;

		$actual = WC_Stripe_Helper::has_gateway_plugin_active( $plugin_id );

		// Clean up.
		WC()->payment_gateways->payment_gateways = $original_payment_gateways;

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Provider for `test_has_gateway_plugin_active`.
	 *
	 * @return array
	 */
	public function provide_has_gateway_plugin_active() {
		return [
			'has Klarna official plugin active'           => [
				'plugin id'        => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA,
				'payment gateways' => [
					'klarna' => (object) [
						'id'      => 'klarna_payments',
						'enabled' => 'yes',
					],
				],
				'expected'         => true,
			],
			'does not have Klarna official plugin active' => [
				'plugin id'        => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA,
				'payment gateways' => [
					'affirm' => (object) [
						'id'      => 'affirm',
						'enabled' => 'yes',
					],
				],
				'expected'         => false,
			],
		];
	}

	/**
	 * Stripe requires price in the smallest dominations aka cents.
	 * This test will see if we're indeed converting the price correctly.
	 */
	public function test_price_conversion_before_send_to_stripe() {
		$this->assertEquals( 10050, WC_Stripe_Helper::get_stripe_amount( 100.50, WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR ) );
		$this->assertEquals( 10050, WC_Stripe_Helper::get_stripe_amount( 10050, WC_Stripe_Currency_Code::JAPANESE_YEN ) );
		$this->assertEquals( 100, WC_Stripe_Helper::get_stripe_amount( 100.50, WC_Stripe_Currency_Code::JAPANESE_YEN ) );
		$this->assertEquals( 10050, WC_Stripe_Helper::get_stripe_amount( 100.50 ) );
		$this->assertIsInt( WC_Stripe_Helper::get_stripe_amount( 100.50, WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR ) );
	}

	/**
	 * We store balance fee/net amounts coming from Stripe.
	 * We need to make sure we format it correctly to be stored in WC.
	 * These amounts are posted in lowest dominations.
	 */
	public function test_format_balance_fee() {
		$balance_fee1           = new stdClass();
		$balance_fee1->fee      = 10500;
		$balance_fee1->net      = 10000;
		$balance_fee1->currency = WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR;

		$this->assertEquals( 105.00, WC_Stripe_Helper::format_balance_fee( $balance_fee1, 'fee' ) );

		$balance_fee2           = new stdClass();
		$balance_fee2->fee      = 10500;
		$balance_fee2->net      = 10000;
		$balance_fee2->currency = WC_Stripe_Currency_Code::JAPANESE_YEN;

		$this->assertEquals( 10500, WC_Stripe_Helper::format_balance_fee( $balance_fee2, 'fee' ) );

		$balance_fee3           = new stdClass();
		$balance_fee3->fee      = 10500;
		$balance_fee3->net      = 10000;
		$balance_fee3->currency = WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR;

		$this->assertEquals( 100.00, WC_Stripe_Helper::format_balance_fee( $balance_fee3, 'net' ) );

		$balance_fee4           = new stdClass();
		$balance_fee4->fee      = 10500;
		$balance_fee4->net      = 10000;
		$balance_fee4->currency = WC_Stripe_Currency_Code::JAPANESE_YEN;

		$this->assertEquals( 10000, WC_Stripe_Helper::format_balance_fee( $balance_fee4, 'net' ) );

		$balance_fee5           = new stdClass();
		$balance_fee5->fee      = 10500;
		$balance_fee5->net      = 10000;
		$balance_fee5->currency = WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR;

		$this->assertEquals( 105.00, WC_Stripe_Helper::format_balance_fee( $balance_fee5 ) );

		$this->assertIsString( WC_Stripe_Helper::format_balance_fee( $balance_fee5 ) );
	}

	/**
	 * Stripe requires statement_descriptor to be no longer than 22 characters.
	 * In addition, it cannot contain <>"' special characters.
	 *
	 * @dataProvider statement_descriptor_sanitation_provider
	 */
	public function test_statement_descriptor_sanitation( $original, $expected ) {
		$this->assertEquals( $expected, WC_Stripe_Helper::clean_statement_descriptor( $original ) );
	}

	public function statement_descriptor_sanitation_provider() {
		return [
			'removes \''             => [ 'Test\'s Store', 'Tests Store' ],
			'removes "'              => [ 'Test " Store', 'Test  Store' ],
			'removes <'              => [ 'Test < Store', 'Test  Store' ],
			'removes >'              => [ 'Test > Store', 'Test  Store' ],
			'removes /'              => [ 'Test / Store', 'Test  Store' ],
			'removes ('              => [ 'Test ( Store', 'Test  Store' ],
			'removes )'              => [ 'Test ) Store', 'Test  Store' ],
			'removes {'              => [ 'Test { Store', 'Test  Store' ],
			'removes }'              => [ 'Test } Store', 'Test  Store' ],
			'removes \\'             => [ 'Test \\ Store', 'Test  Store' ],
			'removes *'              => [ 'Test * Store', 'Test  Store' ],
			'keeps at most 22 chars' => [ 'Test\'s Store > Driving Course Range', 'Tests Store  Driving C' ],
			'mixed length, \' and >' => [ 'Test\'s Store > Driving Course Range', 'Tests Store  Driving C' ],
			'mixed length, \' and <' => [ 'Test\'s Store < Driving Course Range', 'Tests Store  Driving C' ],
			'mixed length, \' and "' => [ 'Test\'s Store " Driving Course Range', 'Tests Store  Driving C' ],
			'removes non-Latin'      => [ 'Test-Storeシ Drהiving?12', 'Test-Store Driving?12' ],
		];
	}

	/**
	 * Test that {@see WC_Stripe_Helper::is_webhook_url()} works as expected.
	 *
	 * @dataProvider is_webhook_url_provider
	 * @covers WC_Stripe_Helper::is_webhook_url()
	 */
	public function test_is_webhook_url( $url, $webhook_url, $expected_result ) {
		$this->assertEquals( $expected_result, WC_Stripe_Helper::is_webhook_url( $url, $webhook_url ) );
	}

	/**
	 * Data provider for {@see test_is_webhook_url()}.
	 *
	 * @return array
	 */
	public function is_webhook_url_provider() {
		return [
			'webhook URLs with mismatched protocol should match'       => [ 'https://example.com/?wc-api=wc_stripe', 'http://example.com/?wc-api=wc_stripe', true ],
			'webhook URLs with mismatched host should not match'       => [ 'https://example.com/?wc-api=wc_stripe', 'https://test.example.com/?wc-api=wc_stripe', false ],
			'webhook URLs with mismatched path should not match'       => [ 'https://example.com/foo?wc-api=wc_stripe', 'https://example.com/bar?wc-api=wc_stripe', false ],
			'webhook URL with empty path should match'                 => [ 'https://example.com/', 'https://example.com/?wc-api=wc_stripe', true ],
			'webhook URL with empty query string should match'         => [ 'https://example.com/test/', 'https://example.com/test/', true ],
			'webhook URL with empty comparison query should not match' => [ 'https://example.com/test/?foo=bar', 'https://example.com/test/', false ],
			'webhook URL with missing parameter should not match'      => [ 'https://example.com/test/?wc-api=wc_stripe', 'https://example.com/test/?wc-api=wc_stripe&foo=bar', false ],
			'webhook URL with wrong parameter should not match'        => [ 'https://example.com/test/?wc-api=wc_stripe_BAD', 'https://example.com/test/?wc-api=wc_stripe', false ],
			'webhook URL with extra parameters should match'           => [ 'https://example.com/test/?wc-api=wc_stripe&foo=bar', 'https://example.com/test/?wc-api=wc_stripe', true ],
		];
	}

	/**
	 * Data provider for {@see test_get_minimum_amount()}.
	 *
	 * @return array
	 */
	public function provide_test_get_minimum_amount(): array {
		return [
			'USD'              => [ 'USD', 50 ],
			'EUR'              => [ 'EUR', 50 ],
			'GBP'              => [ 'GBP', 30 ],
			'CAD'              => [ 'CAD', 50 ],
			'CHF'              => [ 'CHF', 50 ],
			'CZK'              => [ 'CZK', 1500 ],
			'DKK'              => [ 'DKK', 250 ],
			'HUF'              => [ 'HUF', 17500 ],
			'INR'              => [ 'INR', 50 ],
			'MXN'              => [ 'MXN', 1000 ],
			'MYR'              => [ 'MYR', 200 ],
			'NOK'              => [ 'NOK', 300 ],
			'NZD'              => [ 'NZD', 50 ],
			'PLN'              => [ 'PLN', 200 ],
			'RON'              => [ 'RON', 200 ],
			'SEK'              => [ 'SEK', 300 ],
			'SGD'              => [ 'SGD', 50 ],
			'THB'              => [ 'THB', 1000 ],
			'JPY'              => [ 'JPY', 5000 ],
			'UNKNOWN_CURRENCY' => [ 'UNKNOWN_CURRENCY', 50 ],
			'ZMW - not known'  => [ 'ZMW', 50 ],
		];
	}

	/**
	 * @dataProvider provide_test_get_minimum_amount
	 */
	public function test_get_minimum_amount( string $currency, int $expected ): void {
		$currency_filter = function () use ( $currency ) {
			return $currency;
		};
		add_filter( 'woocommerce_currency', $currency_filter );

		$minimum_amount = WC_Stripe_Helper::get_minimum_amount();

		remove_filter( 'woocommerce_currency', $currency_filter );

		$this->assertEquals( $expected, $minimum_amount );
	}

	/**
	 * Test that {@see WC_Stripe_Helper::get_localized_error_message_from_response()} works as expected.
	 *
	 * @param string $error_type The type of error.
	 * @param string $error_code The code of error.
	 * @param string $error_message The message of error.
	 * @param array $localized_data The localized data.
	 * @param string $expected_message The expected message.
	 * @dataProvider provide_test_get_localized_error_message_from_response
	 */
	public function test_get_localized_error_message_from_response( string $error_type, string $error_code, string $error_message, array $localized_data, string $expected_message ): void {
		$response = (object) [
			'error' => (object) [
				'type'    => $error_type,
				'code'    => $error_code,
				'message' => $error_message,
			],
		];

		$localized_message_filter = function ( $messages ) use ( $localized_data ) {
			return array_merge( $messages, $localized_data );
		};

		add_filter( 'wc_stripe_localized_messages', $localized_message_filter, 10, 1 );

		$localized_message = WC_Stripe_Helper::get_localized_error_message_from_response( $response );

		remove_filter( 'wc_stripe_localized_messages', $localized_message_filter, 10 );

		$this->assertEquals( $expected_message, $localized_message );
	}

	/**
	 * Data provider for {@see test_get_localized_error_message_from_response()}.
	 *
	 * @return array
	 */
	public function provide_test_get_localized_error_message_from_response(): array {
		return [
			'card_error with localized message'     => [
				'error_type'       => 'card_error',
				'error_code'       => 'invalid_cvc',
				'error_message'    => 'Mock invalid CVC',
				'localized_data'   => [
					'invalid_cvc' => "The card's security code is invalid.",
				],
				'expected_message' => "The card's security code is invalid.",
			],
			'card_error without localized message'  => [
				'error_type'       => 'card_error',
				'error_code'       => 'unexpected_error_code',
				'error_message'    => 'Unexpected error',
				'localized_data'   => [],
				'expected_message' => 'Unexpected error',
			],
			'other error with localized message'    => [
				'error_type'       => 'invalid_request_error',
				'error_code'       => 'amount_too_small',
				'error_message'    => 'Amount too small',
				'localized_data'   => [
					'invalid_request_error' => 'Unable to process this payment, please try again or use alternative method.',
				],
				'expected_message' => 'Unable to process this payment, please try again or use alternative method.',
			],
			'other error without localized message' => [
				'error_type'       => 'unexpected_error_type',
				'error_code'       => 'unexpected_error_code',
				'error_message'    => 'Unexpected error',
				'localized_data'   => [],
				'expected_message' => 'Unexpected error',
			],
		];
	}

	/**
	 * Test that {@see WC_Stripe_Helper::get_localized_error_message_from_response()} works as expected with unexpected data.
	 *
	 * @dataProvider provide_test_get_localized_error_message_from_response_with_unexpected_data
	 * @param mixed $response The response to test.
	 * @param string $expected_message The expected message.
	 */
	public function test_get_localized_error_message_from_response_with_unexpected_data( $response, string $expected_message ) {
		$localized_message = WC_Stripe_Helper::get_localized_error_message_from_response( $response );
		$this->assertEquals( $expected_message, $localized_message );
	}

	/**
	 * Data provider for {@see test_get_localized_error_message_from_response_with_unexpected_data()}.
	 *
	 * @return array
	 */
	public function provide_test_get_localized_error_message_from_response_with_unexpected_data(): array {
		return [
			'String response'                                                              => [
				'response'         => 'Unexpected data',
				'expected_message' => '',
			],
			'Integer response'                                                             => [
				'response'         => 123,
				'expected_message' => '',
			],
			'Float response'                                                               => [
				'response'         => 123.45,
				'expected_message' => '',
			],
			'Boolean response'                                                             => [
				'response'         => true,
				'expected_message' => '',
			],
			'Array response'                                                               => [
				'response'         => [ 'error' => 'Unexpected data' ],
				'expected_message' => '',
			],
			'Object response with string error'                                            => [
				'response'         => (object) [ 'error' => 'Unexpected data' ],
				'expected_message' => '',
			],
			'Object response with array error'                                             => [
				'response'         => (object) [ 'error' => [ 'message' => 'Unexpected data' ] ],
				'expected_message' => '',
			],
			'Object response with object error but no type or message property'            => [
				'response'         => (object) [ 'error' => (object) [ 'code' => 'unexpected_error_code' ] ],
				'expected_message' => '',
			],
			'Object response with object error but no type property'                       => [
				'response'         => (object) [ 'error' => (object) [ 'message' => 'Unexpected error' ] ],
				'expected_message' => 'Unexpected error',
			],
			'Object response with object error, no type, and integer message property'     => [
				'response'         => (object) [ 'error' => (object) [ 'message' => 123 ] ],
				'expected_message' => '123',
			],
			'Object response with object error, no type, and float message property'       => [
				'response'         => (object) [ 'error' => (object) [ 'message' => 123.45 ] ],
				'expected_message' => '123.45',
			],
			'Object response with object error, no type, and boolean message property'     => [
				'response'         => (object) [ 'error' => (object) [ 'message' => true ] ],
				'expected_message' => '1',
			],
			'Object response with object error, no type, and array message property'       => [
				'response'         => (object) [ 'error' => (object) [ 'message' => [ 'test' => 'Unexpected error' ] ] ],
				'expected_message' => '',
			],
			'Object response with object error, no type, and object message property'      => [
				'response'         => (object) [ 'error' => (object) [ 'message' => (object) [ 'test' => 'Unexpected error' ] ] ],
				'expected_message' => '',
			],
			'Object response with object error, type, and object message property'         => [
				'response'         => (object) [
					'error' => (object) [
						'type'    => 'card_error',
						'message' => (object) [ 'test' => 'Unexpected error' ],
					],
				],
				'expected_message' => '',
			],
			'Object response with valid card_error but no code property'                   => [
				'response'         => (object) [
					'error' => (object) [
						'type'    => 'card_error',
						'message' => 'Unexpected card error',
					],
				],
				'expected_message' => 'Unexpected card error',
			],
			'Object response with valid card_error, array message, and no code property'   => [
				'response'         => (object) [
					'error' => (object) [
						'type'    => 'card_error',
						'message' => [ 'test' => 'Unexpected error' ],
					],
				],
				'expected_message' => '',
			],
			'Object response with valid card_error, object message, and no code property'  => [
				'response'         => (object) [
					'error' => (object) [
						'type'    => 'card_error',
						'message' => (object) [ 'test' => 'Unexpected error' ],
					],
				],
				'expected_message' => '',
			],
			'Object response with valid card_error, integer message, and no code property' => [
				'response'         => (object) [
					'error' => (object) [
						'type'    => 'card_error',
						'message' => 456,
					],
				],
				'expected_message' => '456',
			],
			'Object response with valid card_error, float message, and no code property'   => [
				'response'         => (object) [
					'error' => (object) [
						'type'    => 'card_error',
						'message' => 456.78,
					],
				],
				'expected_message' => '456.78',
			],
			'Object response with valid card_error, boolean message, and no code property' => [
				'response'         => (object) [
					'error' => (object) [
						'type'    => 'card_error',
						'message' => false,
					],
				],
				'expected_message' => '',
			],
		];
	}

	/**
	 * Test for `is_adaptive_pricing_supported` – cart content and preconditions.
	 *
	 * @param bool   $is_checkout        Whether is classic checkout page.
	 * @param bool   $has_block          Whether is block checkout page.
	 * @param string $adaptive_pricing   Adaptive pricing setting.
	 * @param array  $cart_product_types Cart product types (e.g. ['simple'], ['simple','simple'], ['simple','simple','subscription']). Empty or null = empty cart.
	 * @param bool   $expected           Expected result.
	 * @param string $account_country    Two-letter ISO country code for the Stripe account. Defaults to 'US'.
	 * @param bool   $webhook_enabled    Whether the Stripe webhook endpoint is enabled. Defaults to true.
	 * @return void
	 * @dataProvider provide_is_adaptive_pricing_supported
	 */
	public function test_is_adaptive_pricing_supported( bool $is_checkout, bool $has_block, string $adaptive_pricing, ?array $cart_product_types, bool $expected, string $account_country = 'US', bool $webhook_enabled = true ): void {
		$original_stripe_settings                          = WC_Stripe_Helper::get_stripe_settings();
		$new_stripe_settings                               = $original_stripe_settings;
		$new_stripe_settings['adaptive_pricing']           = $adaptive_pricing;
		$new_stripe_settings['optimized_checkout_element'] = 'yes';
		$new_stripe_settings['capture']                    = 'yes';
		$new_stripe_settings['pmc_enabled']                = 'yes';
		if ( $webhook_enabled ) {
			$new_stripe_settings['webhook_data']      = [
				'id'     => 'we_live',
				'secret' => 'whsec_live',
			];
			$new_stripe_settings['test_webhook_data'] = [
				'id'     => 'we_test',
				'secret' => 'whsec_test',
			];
		}
		WC_Stripe_Helper::update_main_stripe_settings( $new_stripe_settings );

		// is_webhook_enabled() short-circuits on a cached status, so we don't hit the Stripe API here.
		if ( $webhook_enabled ) {
			set_transient( WC_Stripe_Account::LIVE_WEBHOOK_STATUS_OPTION, 'enabled', HOUR_IN_SECONDS );
			set_transient( WC_Stripe_Account::TEST_WEBHOOK_STATUS_OPTION, 'enabled', HOUR_IN_SECONDS );
		} else {
			delete_transient( WC_Stripe_Account::LIVE_WEBHOOK_STATUS_OPTION );
			delete_transient( WC_Stripe_Account::TEST_WEBHOOK_STATUS_OPTION );
		}

		$is_checkout_filter = function () use ( $is_checkout ) {
			return $is_checkout;
		};
		add_filter( 'woocommerce_is_checkout', $is_checkout_filter );

		$saved_post = null;
		if ( $has_block ) {
			// Mock has_block( 'woocommerce/checkout' ) via global $post so the helper sees the expected value.
			global $post;
			$saved_post = $post;
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test isolation for has_block().
			$post               = new stdClass();
			$post->post_content = '<!-- wp:woocommerce/checkout -->';
		}

		\WC_Subscriptions_Product::set_is_subscription( false );
		\WC_Subscriptions_Product::set_subscription_product_ids( [] );
		\WC_Pre_Orders_Product::set_is_pre_order_charged_upon_release( false );
		\WC_Deposits_Product_Manager::set_deposits_enabled( false );

		$saved_shipping_methods = WC()->shipping()->shipping_methods;

		WC()->cart->empty_cart();
		$products = [];

		if ( ! empty( $cart_product_types ) ) {
			$subscription_product_ids = [];
			$has_pre_order            = in_array( 'pre-order', $cart_product_types, true );
			$has_deposits             = in_array( 'deposits', $cart_product_types, true );

			\WC_Pre_Orders_Product::set_is_pre_order_charged_upon_release( $has_pre_order );
			\WC_Deposits_Product_Manager::set_deposits_enabled( $has_deposits );

			foreach ( $cart_product_types as $type ) {
				$product        = WC_Helper_Product::create_simple_product();
				$products[]     = $product;
				$cart_item_data = 'deposits' === $type ? [ 'is_deposit' => true ] : [];
				WC()->cart->add_to_cart( $product->get_id(), 1, 0, [], $cart_item_data );
				if ( 'subscription' === $type ) {
					$subscription_product_ids[] = $product->get_id();
				}
			}
			if ( ! empty( $subscription_product_ids ) ) {
				\WC_Subscriptions_Product::set_subscription_product_ids( $subscription_product_ids );
			}
		}

		$this->set_stripe_account_data( [ 'country' => $account_country ] );

		$actual = WC_Stripe_Helper::is_adaptive_pricing_supported();

		// Cleanup.
		WC()->cart->empty_cart();
		WC()->shipping()->shipping_methods = $saved_shipping_methods;

		remove_filter( 'woocommerce_is_checkout', $is_checkout_filter );
		WC_Stripe_Helper::update_main_stripe_settings( $original_stripe_settings );
		delete_transient( WC_Stripe_Account::LIVE_WEBHOOK_STATUS_OPTION );
		delete_transient( WC_Stripe_Account::TEST_WEBHOOK_STATUS_OPTION );
		\WC_Subscriptions_Product::set_is_subscription( false );
		\WC_Subscriptions_Product::set_subscription_product_ids( [] );
		\WC_Pre_Orders_Product::set_is_pre_order_charged_upon_release( false );
		\WC_Deposits_Product_Manager::set_deposits_enabled( false );

		foreach ( $products as $product ) {
			$product->delete( true );
		}

		if ( $has_block ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring $post after test isolation.
			$post = $saved_post;
		}

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider for `test_is_adaptive_pricing_supported`.
	 *
	 * @return array
	 */
	public function provide_is_adaptive_pricing_supported(): array {
		return [
			'adaptive pricing disabled'                 => [
				'is_checkout'        => true,
				'has_block'          => false,
				'adaptive_pricing'   => 'no',
				'cart_product_types' => [ 'simple' ],
				'expected'           => false,
			],
			'not on classic checkout or block checkout' => [
				'is_checkout'        => false,
				'has_block'          => false,
				'adaptive_pricing'   => 'yes',
				'cart_product_types' => [ 'simple' ],
				'expected'           => false,
			],
			'on block checkout'                         => [
				'is_checkout'        => false,
				'has_block'          => true,
				'adaptive_pricing'   => 'yes',
				'cart_product_types' => [ 'simple' ],
				'expected'           => true,
			],
			'empty cart'                                => [
				'is_checkout'        => true,
				'has_block'          => false,
				'adaptive_pricing'   => 'yes',
				'cart_product_types' => null,
				'expected'           => true,
			],
			'simple product only'                       => [
				'is_checkout'        => true,
				'has_block'          => false,
				'adaptive_pricing'   => 'yes',
				'cart_product_types' => [ 'simple' ],
				'expected'           => true,
			],
			'multiple simple products'                  => [
				'is_checkout'        => true,
				'has_block'          => false,
				'adaptive_pricing'   => 'yes',
				'cart_product_types' => [ 'simple', 'simple' ],
				'expected'           => true,
			],
			'simple and subscription products mixed'    => [
				'is_checkout'        => true,
				'has_block'          => false,
				'adaptive_pricing'   => 'yes',
				'cart_product_types' => [ 'simple', 'simple', 'subscription' ],
				'expected'           => false,
			],
			'simple and deposits products mixed'        => [
				'is_checkout'        => true,
				'has_block'          => false,
				'adaptive_pricing'   => 'yes',
				'cart_product_types' => [ 'simple', 'simple', 'deposits' ],
				'expected'           => false,
			],
			'subscription in cart'                      => [
				'is_checkout'        => true,
				'has_block'          => false,
				'adaptive_pricing'   => 'yes',
				'cart_product_types' => [ 'subscription' ],
				'expected'           => false,
			],
			'pre-order in cart'                         => [
				'is_checkout'        => true,
				'has_block'          => false,
				'adaptive_pricing'   => 'yes',
				'cart_product_types' => [ 'pre-order' ],
				'expected'           => false,
			],
			'deposits in cart'                          => [
				'is_checkout'        => true,
				'has_block'          => false,
				'adaptive_pricing'   => 'yes',
				'cart_product_types' => [ 'deposits' ],
				'expected'           => false,
			],
			'India account country'                     => [
				'is_checkout'        => true,
				'has_block'          => false,
				'adaptive_pricing'   => 'yes',
				'cart_product_types' => [ 'simple' ],
				'expected'           => false,
				'account_country'    => 'IN',
			],
			'supported account country'                 => [
				'is_checkout'        => true,
				'has_block'          => false,
				'adaptive_pricing'   => 'yes',
				'cart_product_types' => [ 'simple' ],
				'expected'           => true,
				'account_country'    => 'US',
			],
			'webhooks disabled'                         => [
				'is_checkout'        => true,
				'has_block'          => false,
				'adaptive_pricing'   => 'yes',
				'cart_product_types' => [ 'simple' ],
				'expected'           => false,
				'account_country'    => 'US',
				'webhook_enabled'    => false,
			],
		];
	}

	/**
	 * Tests for `build_line_items`.
	 *
	 * @param bool  $itemized       Whether itemized line items are enabled.
	 * @param array $expected_items The expected line items.
	 * @return void
	 * @dataProvider provide_test_build_line_items
	 */
	public function test_build_line_items( bool $itemized = false, array $expected_items = [] ): void {
		update_option( 'woocommerce_calc_taxes', 'yes' );

		$product = WC_Helper_Product::create_simple_product();
		$product->save();

		$coupon = new \WC_Coupon();
		$coupon->set_code( 'TESTDISCOUNT' );
		$coupon->set_amount( 1 );
		$coupon->set_discount_type( 'fixed_cart' );
		$coupon->save();

		WC()->session->init();
		WC()->cart->empty_cart();

		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->add_discount( 'TESTDISCOUNT' );

		$actual = WC_Stripe_Helper::build_line_items( $itemized );

		// Clean up.
		WC()->cart->empty_cart();
		$product->delete( true );
		$coupon->delete();
		delete_option( 'woocommerce_calc_taxes' );

		$this->assertSame( $expected_items, $actual );
	}

	/**
	 * Test for {@see WC_Stripe_Helper::get_adaptive_pricing_account_unavailable_reason()}.
	 *
	 * @param array   $account_data    Stripe account data to mock.
	 * @param bool    $test_mode       Whether Stripe test mode is enabled.
	 * @param string  $store_currency  WooCommerce store currency code.
	 * @param ?string $expected        Expected return value.
	 * @param bool    $webhook_enabled Whether the Stripe webhook endpoint is enabled. Defaults to true.
	 * @return void
	 * @dataProvider provide_test_get_adaptive_pricing_account_unavailable_reason
	 */
	public function test_get_adaptive_pricing_account_unavailable_reason(
		array $account_data,
		bool $test_mode,
		string $store_currency,
		?string $expected,
		bool $webhook_enabled = true
	): void {
		$original_settings    = WC_Stripe_Helper::get_stripe_settings();
		$settings             = $original_settings;
		$settings['testmode'] = $test_mode ? 'yes' : 'no';
		if ( $webhook_enabled ) {
			$settings['webhook_data']      = [
				'id'     => 'we_live',
				'secret' => 'whsec_live',
			];
			$settings['test_webhook_data'] = [
				'id'     => 'we_test',
				'secret' => 'whsec_test',
			];
		}
		WC_Stripe_Helper::update_main_stripe_settings( $settings );

		// is_webhook_enabled() short-circuits on a cached status, so we don't hit the Stripe API here.
		if ( $webhook_enabled ) {
			set_transient( WC_Stripe_Account::LIVE_WEBHOOK_STATUS_OPTION, 'enabled', HOUR_IN_SECONDS );
			set_transient( WC_Stripe_Account::TEST_WEBHOOK_STATUS_OPTION, 'enabled', HOUR_IN_SECONDS );
		} else {
			delete_transient( WC_Stripe_Account::LIVE_WEBHOOK_STATUS_OPTION );
			delete_transient( WC_Stripe_Account::TEST_WEBHOOK_STATUS_OPTION );
		}

		$this->set_stripe_account_data( $account_data );

		$original_currency = get_option( 'woocommerce_currency' );
		update_option( 'woocommerce_currency', $store_currency );

		$actual = WC_Stripe_Helper::get_adaptive_pricing_account_unavailable_reason();

		// Cleanup.
		update_option( 'woocommerce_currency', $original_currency );
		WC_Stripe_Helper::update_main_stripe_settings( $original_settings );
		delete_transient( WC_Stripe_Account::LIVE_WEBHOOK_STATUS_OPTION );
		delete_transient( WC_Stripe_Account::TEST_WEBHOOK_STATUS_OPTION );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider for {@see test_get_adaptive_pricing_account_unavailable_reason()}.
	 *
	 * @return array
	 */
	public function provide_test_get_adaptive_pricing_account_unavailable_reason(): array {
		return [
			'India account (uppercase) → account-country'                                   => [
				'account_data'   => [
					'country'           => 'IN',
					'external_accounts' => [
						'data' => [
							[ 'currency' => 'inr' ],
						],
					],
				],
				'test_mode'      => false,
				'store_currency' => 'USD',
				'expected'       => 'account-country',
			],
			'India account (lowercase) → account-country'                                   => [
				'account_data'   => [
					'country'           => 'in',
					'external_accounts' => [
						'data' => [
							[ 'currency' => 'inr' ],
						],
					],
				],
				'test_mode'      => false,
				'store_currency' => 'USD',
				'expected'       => 'account-country',
			],
			'India account + test mode → account-country (India checked before mode)'       => [
				'account_data'   => [
					'country'           => 'IN',
					'external_accounts' => [
						'data' => [],
					],
				],
				'test_mode'      => true,
				'store_currency' => 'USD',
				'expected'       => 'account-country',
			],
			'Test mode, no settlement currencies → null (currency check bypassed)'          => [
				'account_data'   => [
					'country'           => 'US',
					'external_accounts' => [
						'data' => [],
					],
				],
				'test_mode'      => true,
				'store_currency' => 'USD',
				'expected'       => null,
			],
			'Test mode, currency mismatch → null (currency check bypassed)'                 => [
				'account_data'   => [
					'country'           => 'US',
					'external_accounts' => [
						'data' => [
							[ 'currency' => 'eur' ],
						],
					],
				],
				'test_mode'      => true,
				'store_currency' => 'USD',
				'expected'       => null,
			],
			'Live mode, no settlement currencies → no-settlement-currencies'                => [
				'account_data'   => [
					'country'           => 'US',
					'external_accounts' => [
						'data' => [],
					],
				],
				'test_mode'      => false,
				'store_currency' => 'USD',
				'expected'       => 'no-settlement-currencies',
			],
			'Live mode, store currency not in settlement → store-currency-not-settlement-currency' => [
				'account_data'   => [
					'country'           => 'US',
					'external_accounts' => [
						'data' => [
							[ 'currency' => 'eur' ],
						],
					],
				],
				'test_mode'      => false,
				'store_currency' => 'USD',
				'expected'       => 'store-currency-not-settlement-currency',
			],
			'Live mode, store currency in settlement → null'                                => [
				'account_data'   => [
					'country'           => 'US',
					'external_accounts' => [
						'data' => [
							[ 'currency' => 'usd' ],
						],
					],
				],
				'test_mode'      => false,
				'store_currency' => 'USD',
				'expected'       => null,
			],
			'Live mode, non-US account, matching currency → null'                           => [
				'account_data'   => [
					'country'           => 'GB',
					'external_accounts' => [
						'data' => [
							[ 'currency' => 'gbp' ],
						],
					],
				],
				'test_mode'      => false,
				'store_currency' => 'GBP',
				'expected'       => null,
			],
			'Live mode, webhooks disabled → webhooks-disabled'                              => [
				'account_data'    => [
					'country'           => 'US',
					'external_accounts' => [
						'data' => [
							[ 'currency' => 'usd' ],
						],
					],
				],
				'test_mode'       => false,
				'store_currency'  => 'USD',
				'expected'        => 'webhooks-disabled',
				'webhook_enabled' => false,
			],
			'Test mode, webhooks disabled → webhooks-disabled (gate applies in both modes)' => [
				'account_data'    => [
					'country'           => 'US',
					'external_accounts' => [
						'data' => [],
					],
				],
				'test_mode'       => true,
				'store_currency'  => 'USD',
				'expected'        => 'webhooks-disabled',
				'webhook_enabled' => false,
			],
		];
	}

	/**
	 * Data provider for `test_build_line_items`.
	 *
	 * @return array
	 */
	public function provide_test_build_line_items(): array {
		return [
			'itemized'     => [
				'itemized'       => true,
				'expected items' => [
					[
						'label'  => 'Dummy Product',
						'amount' => 1000,
					],
					[
						'label'  => 'Tax',
						'amount' => 0,
					],
					[
						'key'    => 'total_shipping',
						'label'  => 'Shipping',
						'amount' => 0,
					],
					[
						'key'    => 'total_discount',
						'label'  => 'Discount',
						'amount' => 100,
					],
				],
			],
			'non-itemized' => [
				'itemized'       => false,
				'expected items' => array_merge(
					[
						[
							'label'  => 'Subtotal',
							'amount' => 1000,
						],
						[
							'label'  => 'Tax',
							'amount' => 0,
						],
						[
							'key'    => 'total_discount',
							'label'  => 'Discount',
							'amount' => 100,
						],
					],
				),
			],
		];
	}

	/**
	 * Test for `is_express_checkout_button_style_overridden`.
	 *
	 * @param string $page_content The content of the checkout page.
	 * @param bool   $expected     Whether an override is expected to be detected.
	 * @return void
	 * @dataProvider provide_test_is_express_checkout_button_style_overridden
	 */
	public function test_is_express_checkout_button_style_overridden( string $page_content, bool $expected ) {
		$page_id = self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $page_content,
			]
		);

		update_option( 'woocommerce_checkout_page_id', $page_id );
		// Isolate the assertion to the checkout page so an unrelated cart page does not interfere.
		update_option( 'woocommerce_cart_page_id', 0 );

		$this->assertSame( $expected, WC_Stripe_Helper::is_express_checkout_button_style_overridden() );
	}

	/**
	 * Data provider for `test_is_express_checkout_button_style_overridden`.
	 *
	 * @return array
	 */
	public function provide_test_is_express_checkout_button_style_overridden() {
		$with_express_block = function ( $express_block ) {
			return '<!-- wp:woocommerce/checkout -->'
				. '<div class="wp-block-woocommerce-checkout">'
				. '<!-- wp:woocommerce/checkout-fields-block -->'
				. '<div class="wp-block-woocommerce-checkout-fields-block">'
				. $express_block
				. '</div>'
				. '<!-- /wp:woocommerce/checkout-fields-block -->'
				. '</div>'
				. '<!-- /wp:woocommerce/checkout -->';
		};

		return [
			'uniform style enabled'                        => [
				$with_express_block( '<!-- wp:woocommerce/checkout-express-payment-block {"showButtonStyles":true} /-->' ),
				true,
			],
			'uniform style explicitly off'                 => [
				$with_express_block( '<!-- wp:woocommerce/checkout-express-payment-block {"showButtonStyles":false} /-->' ),
				false,
			],
			'uniform style attribute omitted'              => [
				$with_express_block( '<!-- wp:woocommerce/checkout-express-payment-block /-->' ),
				false,
			],
			'checkout block without express payment block' => [
				'<!-- wp:woocommerce/checkout --><div class="wp-block-woocommerce-checkout"></div><!-- /wp:woocommerce/checkout -->',
				false,
			],
			'classic shortcode checkout'                   => [
				'[woocommerce_checkout]',
				false,
			],
		];
	}
}
