<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Settings class.
 */
class WC_Stripe_Settings {
	/**
	 * The option name for the Stripe gateway settings.
	 */
	const SETTINGS_OPTION = 'woocommerce_stripe_settings';

	/**
	 * The *Singleton* instance of this class
	 *
	 * @var WC_Stripe_Settings
	 */
	private static $instance;

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
	 * Updates the plugin version in db
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 */
	public function update_plugin_version() {
		delete_option( 'wc_stripe_version' );
		update_option( 'wc_stripe_version', WC_STRIPE_VERSION );
	}

	/**
	 * Updates the PRB location settings based on deprecated filters.
	 *
	 * The filters were removed in favor of plugin settings. This function can, and should,
	 * be removed when we're reasonably sure most merchants have had their settings updated
	 * through this function. Maybe ~80% of merchants is a good threshold?
	 *
	 * @since 5.5.0
	 * @version 5.5.0
	 */
	public function update_prb_location_settings() {
		$stripe_settings = $this->get_gateway_settings();
		$prb_locations   = isset( $stripe_settings['payment_request_button_locations'] )
			? $stripe_settings['payment_request_button_locations']
			: [];
		if ( ! empty( $stripe_settings ) && empty( $prb_locations ) ) {
			global $post;

			$should_show_on_product_page  = ! apply_filters( 'wc_stripe_hide_payment_request_on_product_page', false, $post );
			$should_show_on_cart_page     = apply_filters( 'wc_stripe_show_payment_request_on_cart', true );
			$should_show_on_checkout_page = apply_filters( 'wc_stripe_show_payment_request_on_checkout', false, $post );

			$new_prb_locations = [];

			if ( $should_show_on_product_page ) {
				$new_prb_locations[] = 'product';
			}

			if ( $should_show_on_cart_page ) {
				$new_prb_locations[] = 'cart';
			}

			if ( $should_show_on_checkout_page ) {
				$new_prb_locations[] = 'checkout';
			}

			$stripe_settings['payment_request_button_locations'] = $new_prb_locations;
			$this->update_gateway_settings( $stripe_settings );
		}
	}

	/**
	 * Provide default values for missing settings on initial gateway settings save.
	 *
	 * @since 4.5.4
	 * @version 4.5.4
	 *
	 * @param array      $settings New settings to save.
	 * @param array|bool $old_settings Existing settings, if any.
	 * @return array New value but with defaults initially filled in for missing settings.
	 */
	public function gateway_settings_update( $settings, $old_settings ) {
		if ( false === $old_settings ) {
			$gateway      = new WC_Gateway_Stripe();
			$fields       = $gateway->get_form_fields();
			$old_settings = array_merge( array_fill_keys( array_keys( $fields ), '' ), wp_list_pluck( $fields, 'default' ) );
			$settings     = array_merge( $old_settings, $settings );
		}

		// Note that we need to run these checks before we call toggle_upe() below.
		$this->maybe_reset_stripe_in_memory_key( $settings, $old_settings );

		if ( ! WC_Stripe_Feature_Flags::is_upe_preview_enabled() ) {
			return $settings;
		}

		return $this->toggle_upe( $settings, $old_settings );
	}

	/**
	 * Helper function that ensures we clear the in-memory Stripe API key in {@see WC_Stripe_API}
	 * when we're making a change to our settings that impacts which secret key we should be using.
	 *
	 * @param array $settings     New settings that have just been saved.
	 * @param array $old_settings Old settings that were previously saved.
	 * @return void
	 */
	protected function maybe_reset_stripe_in_memory_key( $settings, $old_settings ) {
		// If we're making a change that impacts which secret key we should be using,
		// we need to clear the static key being used by WC_Stripe_API.
		// Note that this also needs to run before we call toggle_upe() below.
		$should_clear_stripe_api_key = false;

		$settings_to_check = [
			'testmode',
			'secret_key',
			'test_secret_key',
		];

		foreach ( $settings_to_check as $setting_to_check ) {
			if ( isset( $settings[ $setting_to_check ] ) && isset( $old_settings[ $setting_to_check ] ) && $settings[ $setting_to_check ] !== $old_settings[ $setting_to_check ] ) {
				$should_clear_stripe_api_key = true;
				break;
			}
		}

		if ( $should_clear_stripe_api_key ) {
			WC_Stripe_API::set_secret_key( '' );
		}
	}

	/**
	 * Enable or disable UPE.
	 *
	 * When enabling UPE: For each currently enabled Stripe LPM, the corresponding UPE method is enabled.
	 *
	 * When disabling UPE: For each currently enabled UPE method, the corresponding LPM is enabled.
	 *
	 * @param array      $settings New settings to save.
	 * @param array|bool $old_settings Existing settings, if any.
	 * @return array New value but with defaults initially filled in for missing settings.
	 */
	protected function toggle_upe( $settings, $old_settings ) {
		if ( false === $old_settings || ! isset( $old_settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] ) ) {
			$old_settings = [ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME => 'no' ];
		}
		if ( ! isset( $settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] ) || $settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] === $old_settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] ) {
			return $settings;
		}

		if ( 'yes' === $settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] ) {
			return $this->enable_upe( $settings );
		}

		return $this->disable_upe( $settings );
	}

	/**
	 * Enable UPE and disable legacy LPMs.
	 *
	 * @param array $settings New settings to save.
	 * @return array New value but with defaults initially filled in for missing settings.
	 * @return array
	 */
	protected function enable_upe( $settings ) {
		$settings['upe_checkout_experience_accepted_payments'] = [];

		$payment_gateways = WC_Stripe_Helper::get_legacy_payment_methods();
		foreach ( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS as $method_class ) {
			if ( ! defined( "$method_class::LPM_GATEWAY_CLASS" ) ) {
				continue;
			}

			$lpm_gateway_id = constant( $method_class::LPM_GATEWAY_CLASS . '::ID' );
			if ( isset( $payment_gateways[ $lpm_gateway_id ] ) && $payment_gateways[ $lpm_gateway_id ]->is_enabled() ) {
				// DISABLE LPM
				/**
				 * TODO: This can be replaced with:
				 *
				 *   $payment_gateways[ $lpm_gateway_id ]->update_option( 'enabled', 'no' );
				 *   $payment_gateways[ $lpm_gateway_id ]->enabled = 'no';
				 *
				 * ...once the minimum WC version is 3.4.0.
				 */
				$payment_gateways[ $lpm_gateway_id ]->settings['enabled'] = 'no';
				update_option(
					$payment_gateways[ $lpm_gateway_id ]->get_option_key(),
					apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $payment_gateways[ $lpm_gateway_id ]::ID, $payment_gateways[ $lpm_gateway_id ]->settings ),
					'yes'
				);
				// ENABLE UPE METHOD
				$settings['upe_checkout_experience_accepted_payments'][] = $method_class::STRIPE_ID;
			}

			if ( 'stripe' === $lpm_gateway_id && isset( $this->stripe_gateway ) && $this->stripe_gateway->is_enabled() ) {
				$settings['upe_checkout_experience_accepted_payments'][] = 'card';
				$settings['upe_checkout_experience_accepted_payments'][] = 'link';
			}
		}
		if ( empty( $settings['upe_checkout_experience_accepted_payments'] ) ) {
			$settings['upe_checkout_experience_accepted_payments'] = [ 'card', 'link' ];
		} else {
			// The 'stripe' gateway must be enabled for UPE if any LPMs were enabled.
			$settings['enabled'] = 'yes';
		}

		return $settings;
	}

	/**
	 * Disable UPE and enable legacy LPMs.
	 *
	 * @param array $settings New settings to save.
	 * @return array New value but with defaults initially filled in for missing settings.
	 */
	protected function disable_upe( $settings ) {
		$upe_gateway            = new WC_Stripe_UPE_Payment_Gateway();
		$upe_enabled_method_ids = $upe_gateway->get_upe_enabled_payment_method_ids();
		foreach ( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS as $method_class ) {
			if ( ! defined( "$method_class::LPM_GATEWAY_CLASS" ) || ! in_array( $method_class::STRIPE_ID, $upe_enabled_method_ids, true ) ) {
				continue;
			}
			// ENABLE LPM
			$gateway_class = $method_class::LPM_GATEWAY_CLASS;
			$gateway       = new $gateway_class();
			/**
			 * TODO: This can be replaced with:
			 *
			 *   $gateway->update_option( 'enabled', 'yes' );
			 *
			 * ...once the minimum WC version is 3.4.0.
			 */
			$gateway->settings['enabled'] = 'yes';
			update_option( $gateway->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $gateway::ID, $gateway->settings ), 'yes' );
		}
		// Disable main Stripe/card LPM if 'card' UPE method wasn't enabled.
		if ( ! in_array( 'card', $upe_enabled_method_ids, true ) ) {
			$settings['enabled'] = 'no';
		}
		// DISABLE ALL UPE METHODS
		if ( ! isset( $settings['upe_checkout_experience_accepted_payments'] ) ) {
			$settings['upe_checkout_experience_accepted_payments'] = [];
		}
		return $settings;
	}

	/**
	 * Get the main Stripe settings option.
	 *
	 * @param string $method (Optional) The payment method to get the settings from.
	 * @return array $settings The Stripe settings.
	 */
	public function get_gateway_settings( $method = null ) {
		$settings = null === $method ? get_option( self::SETTINGS_OPTION, [] ) : get_option( 'woocommerce_stripe_' . $method . '_settings', [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
		return $settings;
	}

	/**
	 * Update the main Stripe settings option.
	 *
	 * @param $options array The Stripe settings.
	 * @return void
	 */
	public function update_gateway_settings( $options ) {
		update_option( self::SETTINGS_OPTION, $options );
	}

	/**
	 * Delete the main Stripe settings option.
	 *
	 * @return void
	 */
	public function delete_gateway_settings() {
		delete_option( self::SETTINGS_OPTION );
	}
}
