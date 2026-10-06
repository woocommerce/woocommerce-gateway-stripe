<?php
/**
 * Class WC_Stripe_User_Banners
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class for managing user banners and dismissals.
 */
class WC_Stripe_User_Banners {
	/**
	 * The user option name for the Instant Payouts banner dismissal.
	 */
	private const INSTANT_PAYOUTS_BANNER_OPTION = 'wc_stripe_show_instant_payouts_banner';

	/**
	 * Instant Payouts banner configuration.
	 */
	private const INSTANT_PAYOUTS_BANNER = [
		'option' => self::INSTANT_PAYOUTS_BANNER_OPTION,
		'time'   => 3 * WEEK_IN_SECONDS,
	];

	/**
	 * Dismiss a banner.
	 *
	 * @since 11.1.0
	 *
	 * @param array $banner_config Banner configuration.
	 * @return void
	 */
	private static function dismiss_banner( array $banner_config ): void {
		$banner_option = $banner_config['option'] ?? null;
		if ( ! is_string( $banner_option ) ) {
			return;
		}

		$banner_time = $banner_config['time'] ?? null;
		if ( is_int( $banner_time ) ) {
			update_user_option( get_current_user_id(), $banner_option, time() + $banner_time );
			return;
		}

		$dismiss_value = $banner_config['dismiss_value'] ?? 'no';
		update_user_option( get_current_user_id(), $banner_option, $dismiss_value );
	}

	/**
	 * Check if a banner is dismissed.
	 *
	 * @since 11.1.0
	 *
	 * @param array $banner_config Banner configuration.
	 * @return bool
	 */
	private static function is_banner_dismissed( array $banner_config ): bool {
		$banner_option = $banner_config['option'] ?? null;
		if ( ! is_string( $banner_option ) ) {
			return false;
		}

		$option_value = get_user_option( $banner_config['option'] );
		if ( false === $option_value ) {
			return false;
		}

		$banner_time = $banner_config['time'] ?? null;
		if ( is_int( $banner_time ) ) {
			$expiry_time = null;

			if ( is_int( $option_value ) ) {
				$expiry_time = $option_value;
			} elseif ( is_string( $option_value ) && ctype_digit( $option_value ) ) {
				$expiry_time = (int) $option_value;
			}

			if ( null === $expiry_time ) {
				return false;
			}

			return time() < $expiry_time;
		}

		$dismiss_value = $banner_config['dismiss_value'] ?? 'no';
		return $dismiss_value === $option_value;
	}

	/**
	 * Dismiss the Instant Payouts banner for the current user.
	 *
	 * @since 11.1.0
	 * @return void
	 */
	public static function dismiss_instant_payouts_banner(): void {
		self::dismiss_banner( self::INSTANT_PAYOUTS_BANNER );
	}

	/**
	 * Check if the Instant Payouts banner is dismissed for the current user.
	 *
	 * @since 11.1.0
	 * @return bool
	 */
	public static function is_instant_payouts_banner_dismissed(): bool {
		return self::is_banner_dismissed( self::INSTANT_PAYOUTS_BANNER );
	}
}
