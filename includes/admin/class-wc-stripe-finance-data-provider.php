<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (
	! class_exists( 'Automattic\WooCommerce\Enums\FinanceDataSource' )
	|| ! interface_exists( 'Automattic\WooCommerce\Admin\Payments\Finance\FinanceDataProviderInterface' )
	|| ! interface_exists( 'Automattic\WooCommerce\Admin\Payments\Finance\BalanceProviderInterface' )
	|| ! interface_exists( 'Automattic\WooCommerce\Admin\Payments\Finance\PayoutsProviderInterface' )
	|| ! class_exists( 'Automattic\WooCommerce\Admin\Payments\Finance\FinanceDataPage' )
	|| ! class_exists( 'Automattic\WooCommerce\Admin\Payments\Finance\FinanceDataQuery' )
) {
	return;
}

use Automattic\WooCommerce\Admin\Payments\Finance\BalanceProviderInterface;
use Automattic\WooCommerce\Admin\Payments\Finance\FinanceDataException;
use Automattic\WooCommerce\Admin\Payments\Finance\FinanceDataPage;
use Automattic\WooCommerce\Admin\Payments\Finance\FinanceDataProviderInterface;
use Automattic\WooCommerce\Admin\Payments\Finance\FinanceDataProviderRegistry;
use Automattic\WooCommerce\Admin\Payments\Finance\FinanceDataQuery;
use Automattic\WooCommerce\Admin\Payments\Finance\PayoutsProviderInterface;
use Automattic\WooCommerce\Admin\Payments\Finance\V1\Balance;
use Automattic\WooCommerce\Admin\Payments\Finance\V1\Link;
use Automattic\WooCommerce\Admin\Payments\Finance\V1\Payout;
use Automattic\WooCommerce\Enums\FinanceDataSource;
use Automattic\WooCommerce\Enums\PayoutStatus;

/**
 * Finance Data Provider for Stripe.
 *
 * @since 11.1.0
 */
class WC_Stripe_Finance_Data_Provider implements FinanceDataProviderInterface, BalanceProviderInterface, PayoutsProviderInterface {
	/**
	 * Register hooks that will be used to register Stripe data for the Finance tab in WooCommerce.
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_payments_finance_providers_registration', [ $this, 'register_finance_data_provider' ], 10, 1 );
	}

	/**
	 * Register the finance data provider for Stripe.
	 *
	 * @param FinanceDataProviderRegistry $registry The registry for finance data providers.
	 */
	public function register_finance_data_provider( FinanceDataProviderRegistry $registry ): void {
		if ( ! $registry->is_registered( $this->get_payment_gateway_id() ) ) {
			$registry->register( $this );
		}
	}

	/**
	 * Get the Stripe payment gateway ID.
	 *
	 * @return string The Stripe payment gateway ID: 'stripe'.
	 */
	public function get_payment_gateway_id(): string {
		return 'stripe';
	}

	/**
	 * Get the Stripe icon URL.
	 *
	 * @return string The Stripe icon URL.
	 */
	public function get_icon_url(): string {
		return WC_STRIPE_PLUGIN_URL . '/assets/images/stripe.svg';
	}

	/**
	 * Get the finance data supported by Stripe.
	 *
	 * @return array The finance data types supported by Stripe.
	 */
	public function get_supported_data_types(): array {
		return [
			FinanceDataSource::BALANCE => 1,
			FinanceDataSource::PAYOUTS => 1,
		];
	}

	/**
	 * Get the balance(s) for the Stripe payment gateway. Multiple can be returned when the account has
	 * multiple settlement currencies.
	 *
	 * @param FinanceDataQuery $query The query for the balances.
	 * @return FinanceDataPage The balances for the Stripe payment gateway.
	 * @throws FinanceDataException If the balance cannot be retrieved.
	 */
	public function get_balances( FinanceDataQuery $query ): FinanceDataPage {
		$stripe_balance = WC_Stripe_API::retrieve( 'balance' );

		if ( null === $stripe_balance || is_wp_error( $stripe_balance ) || ! is_array( $stripe_balance->available ) ) {
			throw new FinanceDataException( __( 'Could not get account balance from Stripe.', 'woocommerce-gateway-stripe' ) );
		}

		$balances             = [];
		$balances_by_currency = [];
		$gateway_id           = $this->get_payment_gateway_id();

		foreach ( $stripe_balance->available as $stripe_balance ) {
			$currency = strtoupper( $stripe_balance->currency );
			if ( ! isset( $balances_by_currency[ $currency ] ) ) {
				$balance                           = new Balance(
					$gateway_id,
					$currency,
					(string) WC_Stripe_Helper::convert_from_stripe_amount( $stripe_balance->amount, $stripe_balance->currency )
				);
				$balances_by_currency[ $currency ] = $balance;

				$balances[] = $balance;
			}
		}

		if ( is_array( $stripe_balance->instant_available ?? null ) ) {
			$is_live_mode = WC_Stripe_Mode::is_live();
			$account_data = WC_Stripe::get_instance()->account->get_cached_account_data( $is_live_mode ? 'live' : 'test' );

			// We need valid account data to be able to deep link to the Stripe dashboard.
			if ( ! empty( $account_data['id'] ) ) {
				$url_mode      = $is_live_mode ? '' : 'test/';
				$dashboard_url = esc_url( 'https://dashboard.stripe.com/' . $account_data['id'] . '/' . $url_mode . 'balance/overview' );

				foreach ( $stripe_balance->instant_available as $stripe_instant_balance ) {
					if ( $stripe_instant_balance->amount <= 0 ) {
						continue;
					}
					$currency = strtoupper( $stripe_instant_balance->currency );
					$balance  = $balances_by_currency[ $currency ] ?? null;
					if ( ! $balance ) {
						continue;
					}

					$instant_payout_amount = (string) WC_Stripe_Helper::convert_from_stripe_amount( $stripe_instant_balance->amount, $stripe_instant_balance->currency );
					$balance->set_available_amount( $instant_payout_amount );

					$dashboard_link = new Link( $dashboard_url, __( 'Instant Payout', 'woocommerce-gateway-stripe' ) );
					$balance->set_payout_link( $dashboard_link );
				}
			}
		}

		return new FinanceDataPage( $balances );
	}

	/**
	 * Fetch payouts for the Stripe payment gateway.
	 *
	 * @param FinanceDataQuery $query The query for the payouts.
	 * @return FinanceDataPage The payouts for the Stripe payment gateway.
	 * @throws FinanceDataException If the payouts cannot be retrieved.
	 */
	public function get_payouts( FinanceDataQuery $query ): FinanceDataPage {
		$query_args  = [
			'limit'  => $query->get_per_page(),
			'expand' => [ 'data.destination' ],
		];
		$next_cursor = $query->get_next_cursor();
		if ( null !== $next_cursor ) {
			$query_args['starting_after'] = $next_cursor;
		} elseif ( null !== $query->get_prev_cursor() ) {
				$query_args['ending_before'] = $query->get_prev_cursor();
		}

		$url_query      = http_build_query( $query_args, '', null, PHP_QUERY_RFC3986 );
		$api            = 'payouts' . ( '' === $url_query ? '' : '?' . $url_query );
		$stripe_payouts = WC_Stripe_API::retrieve( $api );

		if ( null === $stripe_payouts || is_wp_error( $stripe_payouts ) || ! is_array( $stripe_payouts->data ) ) {
			throw new FinanceDataException( __( 'Could not get payouts from Stripe.', 'woocommerce-gateway-stripe' ) );
		}

		$payout_status_map = [
			'pending'    => PayoutStatus::PENDING,
			'paid'       => PayoutStatus::COMPLETE,
			'in_transit' => PayoutStatus::PENDING,
			'canceled'   => PayoutStatus::FAILED,
			'failed'     => PayoutStatus::FAILED,
		];

		$payouts    = [];
		$first_id   = null;
		$last_id    = null;
		$gateway_id = $this->get_payment_gateway_id();

		$account_id   = null;
		$is_test_mode = WC_Stripe_Mode::is_test();
		$account_data = WC_Stripe::get_instance()->account->get_cached_account_data( $is_test_mode ? 'test' : 'live' );
		if ( ! empty( $account_data['id'] ) ) {
			$account_id = $account_data['id'];
		}

		foreach ( $stripe_payouts->data as $stripe_payout ) {
			if ( null === $first_id ) {
				$first_id = $stripe_payout->id;
			}
			$last_id = $stripe_payout->id;

			$currency      = strtoupper( $stripe_payout->currency );
			$payout_amount = (string) WC_Stripe_Helper::convert_from_stripe_amount( $stripe_payout->amount, $stripe_payout->currency );
			$payout_status = $payout_status_map[ $stripe_payout->status ] ?? PayoutStatus::PENDING;
			$created_at    = null;
			if ( ! empty( $stripe_payout->created ) ) {
				$created_at = new \DateTimeImmutable( '@' . $stripe_payout->created );
			}
			$arrival_date = null;
			if ( ! empty( $stripe_payout->arrival_date ) ) {
				$arrival_date = new \DateTimeImmutable( '@' . $stripe_payout->arrival_date );
			}
			$created_at = $created_at ?? $arrival_date;
			if ( $created_at === $arrival_date ) {
				$arrival_date = null;
			}
			$payout = new Payout( $gateway_id, $stripe_payout->id, $currency, $payout_amount, $payout_status, $created_at, $arrival_date, $stripe_payout->status );
			if ( ! empty( $stripe_payout->destination->bank_name ) ) {
				$bank_account = $stripe_payout->destination->bank_name;
				if ( ! empty( $stripe_payout->destination->last4 ) ) {
					$bank_account .= ' ••••' . $stripe_payout->destination->last4;
				}
				$payout->set_bank_account( $bank_account );
			}

			if ( ! empty( $account_id ) ) {
				$payout_link = new Link(
					__( 'View in Stripe', 'woocommerce-gateway-stripe' ),
					esc_url( 'https://dashboard.stripe.com/' . $account_id . '/' . ( $is_test_mode ? 'test/' : '' ) . 'payouts/' . $stripe_payout->id )
				);
				$payout->set_provider_link( $payout_link );
			}

			$payouts[] = $payout;
		}

		$has_more = ! empty( $stripe_payouts->has_more );

		return new FinanceDataPage( $payouts, $has_more, $has_more ? $last_id : null, $first_id );
	}
}
