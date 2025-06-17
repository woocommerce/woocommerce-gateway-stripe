<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class to handle Stripe settings.
 */
class WC_Stripe_Settings {
	/**
	 * Option name for storing Stripe settings.
	 *
	 * @var string
	 */
	const SETTINGS_OPTION = 'woocommerce_stripe_settings';

	/**
	 * The *Singleton* instance of this class
	 *
	 * @var WC_Stripe_Settings
	 */
	private static $instance;

	/**
	 * Settings array.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$settings = get_option( self::SETTINGS_OPTION, [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
		$this->settings = $settings;
	}

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return WC_Stripe_Settings The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get the value of the `enabled` setting.
	 *
	 * @return string
	 */
	public function get_enabled() {
		return $this->settings['enabled'] ?? '';
	}

	/**
	 * Get the value of the `logging` setting.
	 *
	 * @return string
	 */
	public function get_logging() {
		return $this->settings['logging'] ?? '';
	}

	/**
	 * Get the publishable key.
	 *
	 * @return string
	 */
	public function get_publishable_key() {
		return $this->settings['publishable_key'] ?? '';
	}

	/**
	 * Get the secret key.
	 *
	 * @return string
	 */
	public function get_secret_key() {
		return $this->settings['secret_key'] ?? '';
	}

	/**
	 * Get the statement descriptor.
	 *
	 * @return string
	 */
	public function get_statement_descriptor() {
		return $this->settings['statement_descriptor'] ?? '';
	}

	/**
	 * Get the test publishable key.
	 *
	 * @return string
	 */
	public function get_test_publishable_key() {
		return $this->settings['test_publishable_key'] ?? '';
	}

	/**
	 * Get the test secret key.
	 *
	 * @return string
	 */
	public function get_test_secret_key() {
		return $this->settings['test_secret_key'] ?? '';
	}

	/**
	 * Get the express checkout button type.
	 *
	 * @return string
	 */
	public function get_express_checkout_button_type() {
		return $this->settings['payment_request_button_type'] ?? 'default';
	}

	/**
	 * Get the express checkout button theme.
	 *
	 * @return string
	 */
	public function get_express_checkout_button_theme() {
		return $this->settings['payment_request_button_theme'] ?? 'dark';
	}

	/**
	 * Gets the button height.
	 *
	 * @return  string
	 */
	public function get_express_checkout_button_height() {
		$height = $this->settings['payment_request_button_size'] ?? 'default';
		if ( 'small' === $height ) {
			return '40';
		}

		if ( 'large' === $height ) {
			return '56';
		}

		return '48';
	}

	/**
	 * Gets the button radius.
	 *
	 * @return string
	 */
	public function get_express_checkout_button_radius() {
		$height = $this->settings['payment_request_button_size'] ?? 'default';
		if ( 'small' === $height ) {
			return '2';
		}

		if ( 'large' === $height ) {
			return '6';
		}

		return '4';
	}

	/**
	 * Pages where the express checkout buttons should be displayed.
	 *
	 * @return array
	 */
	public function get_express_checkout_button_locations() {
		// If the locations have not been set return the default setting.
		if ( ! isset( $this->settings['payment_request_button_locations'] ) ) {
			return [ 'product', 'cart' ];
		}

		// If all locations are removed through the settings UI the location config will be set to
		// an empty string "". If that's the case (and if the settings are not an array for any
		// other reason) we should return an empty array.
		if ( ! is_array( $this->settings['payment_request_button_locations'] ) ) {
			return [];
		}

		return $this->settings['payment_request_button_locations'];
	}

	/**
	 * Set the payment request button locations.
	 *
	 * @param array $value The value to set.
	 * @return void
	 */
	public function set_payment_request_button_locations( $value ) {
		$this->settings['payment_request_button_locations'] = $value;
		update_option( self::SETTINGS_OPTION, $this->settings );
	}

	/**
	 * Get the test mode setting.
	 *
	 * @return string
	 */
	public function get_test_mode() {
		return $this->settings['test_mode'] ?? '';
	}

	/**
	 * Get the value of the `upe_checkout_experience_accepted_payments` setting.
	 *
	 * @return array
	 */
	public function get_upe_checkout_experience_accepted_payments() {
		return ! empty( $this->settings['upe_checkout_experience_accepted_payments'] )
			? $this->settings['upe_checkout_experience_accepted_payments']
			: [ WC_Stripe_Payment_Methods::CARD ];
	}

	/**
	 * Get the value of the `webhook_secret` setting.
	 *
	 * @return string
	 */
	public function get_webhook_secret() {
		return $this->settings['webhook_secret'] ?? '';
	}

	/**
	 * Get the value of the `test_webhook_secret` setting.
	 *
	 * @return string
	 */
	public function get_test_webhook_secret() {
		return $this->settings['test_webhook_secret'] ?? '';
	}

	/**
	 * Get the value of the `connection_type` setting.
	 *
	 * @return string
	 */
	public function get_connection_type() {
		return $this->settings['connection_type'] ?? '';
	}

	/**
	 * Get the value of the `test_connection_type` setting.
	 *
	 * @return string
	 */
	public function get_test_connection_type() {
		return $this->settings['test_connection_type'] ?? '';
	}

	/**
	 * Get the value of the `pmc_enabled` setting.
	 *
	 * @return string
	 */
	public function get_pmc_enabled() {
		return $this->settings['pmc_enabled'] ?? '';
	}

	/**
	 * Set the `pmc_enabled` setting to true.
	 *
	 * @param string $value The value to set.
	 * @return void
	 */
	public function set_pmc_enabled( $value ) {
		$this->settings['pmc_enabled'] = $value;
		update_option( self::SETTINGS_OPTION, $this->settings );
	}

	/**
	 * Get the value of the `payment_request` setting.
	 *
	 * @return string
	 */
	public function get_payment_request() {
		return $this->settings['payment_request'] ?? '';
	}

	/**
	 * Get the value of the `stripe_upe_payment_method_order` setting.
	 *
	 * @return array
	 */
	public function get_stripe_upe_payment_method_order() {
		return $this->settings['stripe_upe_payment_method_order'] ?? [];
	}

	/**
	 * Set the `stripe_upe_payment_method_order` setting.
	 *
	 * @param array $value The value to set.
	 * @return void
	 */
	public function set_stripe_upe_payment_method_order( $value ) {
		$this->settings['stripe_upe_payment_method_order'] = $value;
		update_option( self::SETTINGS_OPTION, $this->settings );
	}
}
