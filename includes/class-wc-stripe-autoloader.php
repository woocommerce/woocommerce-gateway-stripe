<?php

/**
 * Class that implements a basic autoloader for the WooCommerce Stripe payment gateway.
 *
 * @since 9.5.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Stripe_Autoloader {

	/**
	 * Cached in-memory class map. Will be populated from {@see get_classmap()}.
	 *
	 * @var string[]|null
	 */
	private static $classmap = null;

	/**
	 * Cached in-memory class map for admin code. Will be populated from {@see get_admin_classmap()}.
	 *
	 * @var string[]|null
	 */
	private static $admin_classmap = null;

	/**
	 * Tries to autoloads a class based on the classmap from {@see get_classmap()}
	 * and when {@see is_admin()} is true, we access {@see get_admin_classmap()} as well.
	 *
	 * @param string $class The class to autoload.
	 * @return boolean True if the class was autoloaded, false otherwise.
	 */
	public static function autoload( $class ) {
		// We're not using namespaces, so skip if the class name contains a namespace.
		if ( str_contains( $class, '\\' ) ) {
			return false;
		}

		// Note that we lowercase the class name as PHP class names are case-insensitive.
		// We intentionally avoid prefix matching because that would require multiple prefix checks for every lookup.
		// Instead we use key lookups against static classmaps with one-time load costs.
		$class_lower = strtolower( $class );

		if ( null === self::$classmap ) {
			self::$classmap = self::get_classmap();
		}

		if ( isset( self::$classmap[ $class_lower ] ) ) {
			require self::$classmap[ $class_lower ];

			return true;
		}

		if ( function_exists( 'is_admin' ) && is_admin() ) {
			if ( null === self::$admin_classmap ) {
				self::$admin_classmap = self::get_admin_classmap();
			}

			if ( isset( self::$admin_classmap[ $class_lower ] ) ) {
				require self::$admin_classmap[ $class_lower ];

				return true;
			}
		}

		return false;
	}

	/**
	 * Constructor.
	 */
	public static function init() {
		spl_autoload_register( [ __CLASS__, 'autoload' ] );
	}

	/**
	 * Returns the main classmap for the plugin.
	 *
	 * @return array
	 */
	private static function get_classmap() {
		return [
			'allowed_payment_request_button_types_update'           => __DIR__ . '/migrations/class-allowed-payment-request-button-types-update.php',
			'migrate_payment_request_data_to_express_checkout_data' => __DIR__ . '/migrations/class-migrate-payment-request-data-to-express-checkout-data.php',
			'wc_gateway_stripe'                                     => __DIR__ . '/class-wc-gateway-stripe.php',
			'wc_gateway_stripe_alipay'                              => __DIR__ . '/payment-methods/class-wc-gateway-stripe-alipay.php',
			'wc_gateway_stripe_bancontact'                          => __DIR__ . '/payment-methods/class-wc-gateway-stripe-bancontact.php',
			'wc_gateway_stripe_boleto'                              => __DIR__ . '/payment-methods/class-wc-gateway-stripe-boleto.php',
			'wc_gateway_stripe_eps'                                 => __DIR__ . '/payment-methods/class-wc-gateway-stripe-eps.php',
			'wc_gateway_stripe_giropay'                             => __DIR__ . '/payment-methods/class-wc-gateway-stripe-giropay.php',
			'wc_gateway_stripe_ideal'                               => __DIR__ . '/payment-methods/class-wc-gateway-stripe-ideal.php',
			'wc_gateway_stripe_multibanco'                          => __DIR__ . '/payment-methods/class-wc-gateway-stripe-multibanco.php',
			'wc_gateway_stripe_oxxo'                                => __DIR__ . '/payment-methods/class-wc-gateway-stripe-oxxo.php',
			'wc_gateway_stripe_p24'                                 => __DIR__ . '/payment-methods/class-wc-gateway-stripe-p24.php',
			'wc_gateway_stripe_sepa'                                => __DIR__ . '/payment-methods/class-wc-gateway-stripe-sepa.php',
			'wc_gateway_stripe_sofort'                              => __DIR__ . '/payment-methods/class-wc-gateway-stripe-sofort.php',
			'wc_payment_token_ach'                                  => __DIR__ . '/payment-tokens/class-wc-stripe-ach-payment-token.php',
			'wc_payment_token_acss'                                 => __DIR__ . '/payment-tokens/class-wc-stripe-acss-payment-token.php',
			'wc_payment_token_amazon_pay'                           => __DIR__ . '/payment-tokens/class-wc-stripe-amazon-pay-payment-token.php',
			'wc_payment_token_bacs_debit'                           => __DIR__ . '/payment-tokens/class-wc-stripe-bacs-payment-token.php',
			'wc_payment_token_becs_debit'                           => __DIR__ . '/payment-tokens/class-wc-stripe-becs-debit-payment-token.php',
			'wc_payment_token_cashapp'                              => __DIR__ . '/payment-tokens/class-wc-stripe-cash-app-payment-token.php',
			'wc_payment_token_link'                                 => __DIR__ . '/payment-tokens/class-wc-stripe-link-payment-token.php',
			'wc_payment_token_sepa'                                 => __DIR__ . '/payment-tokens/class-wc-stripe-sepa-payment-token.php',
			'wc_rest_stripe_account_controller'                     => __DIR__ . '/admin/class-wc-rest-stripe-account-controller.php',
			'wc_rest_stripe_account_keys_controller'                => __DIR__ . '/admin/class-wc-rest-stripe-account-keys-controller.php',
			'wc_rest_stripe_connection_tokens_controller'           => __DIR__ . '/admin/class-wc-rest-stripe-connection-tokens-controller.php',
			'wc_rest_stripe_locations_controller'                   => __DIR__ . '/admin/class-wc-rest-stripe-locations-controller.php',
			'wc_rest_stripe_orders_controller'                      => __DIR__ . '/admin/class-wc-rest-stripe-orders-controller.php',
			'wc_rest_stripe_settings_controller'                    => __DIR__ . '/admin/class-wc-rest-stripe-settings-controller.php',
			'wc_rest_stripe_tokens_controller'                      => __DIR__ . '/admin/class-wc-rest-stripe-tokens-controller.php',
			'wc_stripe_account'                                     => __DIR__ . '/class-wc-stripe-account.php',
			'wc_stripe_action_scheduler_service'                    => __DIR__ . '/class-wc-stripe-action-scheduler-service.php',
			'wc_stripe_admin_upe_compatibility_controller'          => __DIR__ . '/admin/class-wc-stripe-upe-compatibility-controller.php',
			'wc_stripe_amazon_pay_controller'                       => __DIR__ . '/admin/class-wc-stripe-amazon-pay-controller.php',
			'wc_stripe_api'                                         => __DIR__ . '/class-wc-stripe-api.php',
			'wc_stripe_apple_pay'                                   => __DIR__ . '/deprecated/class-wc-stripe-apple-pay.php',
			'wc_stripe_apple_pay_registration'                      => __DIR__ . '/class-wc-stripe-apple-pay-registration.php',
			'wc_stripe_blocks_support'                              => __DIR__ . '/class-wc-stripe-blocks-support.php',
			'wc_stripe_co_branded_cc_compatibility'                 => __DIR__ . '/class-wc-stripe-co-branded-cc-compatibility.php',
			'wc_stripe_connect'                                     => __DIR__ . '/connect/class-wc-stripe-connect.php',
			'wc_stripe_connect_api'                                 => __DIR__ . '/connect/class-wc-stripe-connect-api.php',
			'wc_stripe_connect_rest_controller'                     => __DIR__ . '/abstracts/abstract-wc-stripe-connect-rest-controller.php',
			'wc_stripe_connect_rest_oauth_connect_controller'       => __DIR__ . '/connect/class-wc-stripe-connect-rest-oauth-connect-controller.php',
			'wc_stripe_connect_rest_oauth_init_controller'          => __DIR__ . '/connect/class-wc-stripe-connect-rest-oauth-init-controller.php',
			'wc_stripe_currency_code'                               => __DIR__ . '/constants/class-wc-stripe-currency-code.php',
			'wc_stripe_customer'                                    => __DIR__ . '/class-wc-stripe-customer.php',
			'wc_stripe_email_failed_authentication'                 => __DIR__ . '/compat/class-wc-stripe-email-failed-authentication.php',
			'wc_stripe_email_failed_authentication_retry'           => __DIR__ . '/compat/class-wc-stripe-email-failed-authentication-retry.php',
			'wc_stripe_email_failed_preorder_authentication'        => __DIR__ . '/compat/class-wc-stripe-email-failed-preorder-authentication.php',
			'wc_stripe_email_failed_renewal_authentication'         => __DIR__ . '/compat/class-wc-stripe-email-failed-renewal-authentication.php',
			'wc_stripe_exception'                                   => __DIR__ . '/class-wc-stripe-exception.php',
			'wc_stripe_express_checkout_ajax_handler'               => __DIR__ . '/payment-methods/class-wc-stripe-express-checkout-ajax-handler.php',
			'wc_stripe_express_checkout_element'                    => __DIR__ . '/payment-methods/class-wc-stripe-express-checkout-element.php',
			'wc_stripe_express_checkout_helper'                     => __DIR__ . '/payment-methods/class-wc-stripe-express-checkout-helper.php',
			'wc_stripe_feature_flags'                               => __DIR__ . '/class-wc-stripe-feature-flags.php',
			'wc_stripe_fingerprint_trait'                           => __DIR__ . '/payment-tokens/trait-wc-stripe-fingerprint.php',
			'wc_stripe_helper'                                      => __DIR__ . '/class-wc-stripe-helper.php',
			'wc_stripe_hong_kong_states'                            => __DIR__ . '/constants/class-wc-stripe-hong-kong-states.php',
			'wc_stripe_inbox_notes'                                 => __DIR__ . '/admin/class-wc-stripe-inbox-notes.php',
			'wc_stripe_intent_controller'                           => __DIR__ . '/class-wc-stripe-intent-controller.php',
			'wc_stripe_intent_status'                               => __DIR__ . '/constants/class-wc-stripe-intent-status.php',
			'wc_stripe_logger'                                      => __DIR__ . '/class-wc-stripe-logger.php',
			'wc_stripe_mode'                                        => __DIR__ . '/class-wc-stripe-mode.php',
			'wc_stripe_order'                                       => __DIR__ . '/class-wc-stripe-order.php',
			'wc_stripe_order_handler'                               => __DIR__ . '/class-wc-stripe-order-handler.php',
			'wc_stripe_payment_gateway'                             => __DIR__ . '/abstracts/abstract-wc-stripe-payment-gateway.php',
			'wc_stripe_payment_gateway_voucher'                     => __DIR__ . '/abstracts/abstract-wc-stripe-payment-gateway-voucher.php',
			'wc_stripe_payment_gateways_controller'                 => __DIR__ . '/admin/class-wc-stripe-payment-gateways-controller.php',
			'wc_stripe_payment_method_comparison_interface'         => __DIR__ . '/payment-tokens/interface-wc-stripe-payment-method-comparison.php',
			'wc_stripe_payment_method_configurations'               => __DIR__ . '/class-wc-stripe-payment-method-configurations.php',
			'wc_stripe_payment_methods'                             => __DIR__ . '/constants/class-wc-stripe-payment-methods.php',
			'wc_stripe_payment_request'                             => __DIR__ . '/payment-methods/class-wc-stripe-payment-request.php',
			'wc_stripe_payment_request_button_states'               => __DIR__ . '/constants/class-wc-stripe-payment-request-button-states.php',
			'wc_stripe_payment_requests_controller'                 => __DIR__ . '/admin/class-wc-stripe-payment-requests-controller.php',
			'wc_stripe_payment_token_cc'                            => __DIR__ . '/payment-tokens/class-wc-stripe-cc-payment-token.php',
			'wc_stripe_payment_tokens'                              => __DIR__ . '/payment-tokens/class-wc-stripe-payment-tokens.php',
			'wc_stripe_pre_orders_trait'                            => __DIR__ . '/compat/trait-wc-stripe-pre-orders.php',
			'wc_stripe_rest_base_controller'                        => __DIR__ . '/admin/class-wc-stripe-rest-base-controller.php',
			'wc_stripe_rest_upe_flag_toggle_controller'             => __DIR__ . '/admin/class-wc-stripe-rest-upe-flag-toggle-controller.php',
			'wc_stripe_settings_controller'                         => __DIR__ . '/admin/class-wc-stripe-settings-controller.php',
			'wc_stripe_upe_availability_note'                       => __DIR__ . '/notes/class-wc-stripe-upe-availability-note.php',
			'wc_stripe_upe_compatibility'                           => __DIR__ . '/class-wc-stripe-upe-compatibility.php',
			'wc_stripe_upe_compatibility_controller'                => __DIR__ . '/admin/class-wc-stripe-upe-compatibility-controller.php',
			'wc_stripe_upe_payment_gateway'                         => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-gateway.php',
			'wc_stripe_upe_payment_method'                          => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method.php',
			'wc_stripe_upe_payment_method_ach'                      => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-ach.php',
			'wc_stripe_upe_payment_method_acss'                     => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-acss.php',
			'wc_stripe_upe_payment_method_affirm'                   => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-affirm.php',
			'wc_stripe_upe_payment_method_afterpay_clearpay'        => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-afterpay-clearpay.php',
			'wc_stripe_upe_payment_method_alipay'                   => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-alipay.php',
			'wc_stripe_upe_payment_method_amazon_pay'               => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-amazon-pay.php',
			'wc_stripe_upe_payment_method_bacs_debit'               => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-bacs-debit.php',
			'wc_stripe_upe_payment_method_bancontact'               => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-bancontact.php',
			'wc_stripe_upe_payment_method_becs_debit'               => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-becs-debit.php',
			'wc_stripe_upe_payment_method_blik'                     => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-blik.php',
			'wc_stripe_upe_payment_method_boleto'                   => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-boleto.php',
			'wc_stripe_upe_payment_method_cash_app_pay'             => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-cash-app-pay.php',
			'wc_stripe_upe_payment_method_cc'                       => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-cc.php',
			'wc_stripe_upe_payment_method_eps'                      => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-eps.php',
			'wc_stripe_upe_payment_method_giropay'                  => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-giropay.php',
			'wc_stripe_upe_payment_method_ideal'                    => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-ideal.php',
			'wc_stripe_upe_payment_method_klarna'                   => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-klarna.php',
			'wc_stripe_upe_payment_method_link'                     => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-link.php',
			'wc_stripe_upe_payment_method_multibanco'               => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-multibanco.php',
			'wc_stripe_upe_payment_method_oxxo'                     => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-oxxo.php',
			'wc_stripe_upe_payment_method_p24'                      => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-p24.php',
			'wc_stripe_upe_payment_method_sepa'                     => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-sepa.php',
			'wc_stripe_upe_payment_method_sofort'                   => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-sofort.php',
			'wc_stripe_upe_payment_method_wechat_pay'               => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-wechat-pay.php',
			'wc_stripe_upe_stripelink_note'                         => __DIR__ . '/notes/class-wc-stripe-upe-stripe-link-note.php',
			'wc_stripe_status'                                      => __DIR__ . '/class-wc-stripe-status.php',
			'wc_stripe_subscriptions_helper'                        => __DIR__ . '/compat/class-wc-stripe-subscriptions-helper.php',
			'wc_stripe_subscriptions_legacy_sepa_token_update'      => __DIR__ . '/compat/class-wc-stripe-subscriptions-legacy-sepa-token-update.php',
			'wc_stripe_subscriptions_trait'                         => __DIR__ . '/compat/trait-wc-stripe-subscriptions.php',
			'wc_stripe_subscriptions_utilities_trait'               => __DIR__ . '/compat/trait-wc-stripe-subscriptions-utilities.php',
			'wc_stripe_webhook_handler'                             => __DIR__ . '/class-wc-stripe-webhook-handler.php',
			'wc_stripe_webhook_state'                               => __DIR__ . '/class-wc-stripe-webhook-state.php',
			'wc_stripe_woo_compat_utils'                            => __DIR__ . '/compat/class-wc-stripe-woo-compat-utils.php',
		];
	}

	/**
	 * Returns the classmap for admin-specific classes for the plugin.
	 *
	 * @return array
	 */
	private static function get_admin_classmap() {
		return [
			'wc_stripe_admin_inbox_notes'                           => __DIR__ . '/admin/class-wc-stripe-inbox-notes.php',
			'wc_stripe_admin_notices'                               => __DIR__ . '/admin/class-wc-stripe-admin-notices.php',
			'wc_stripe_privacy'                                     => __DIR__ . '/admin/class-wc-stripe-privacy.php',
		];
	}
}
