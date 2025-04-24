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
	 */
	private static $classmap = null;

	/**
	 * Cached in-memory class map for admin code. Will be populated from {@see get_admin_classmap()}.
	 */
	private static $admin_classmap = null;

	/**
	 * Tries to autoloads a class based on the classmap from {@see get_classmap()}.
	 *
	 * @param string $class The class to autoload.
	 * @return boolean True if the class was autoloaded, false otherwise.
	 */
	public static function autoload( $class ) {
		if ( null === self::$classmap ) {
			self::$classmap = self::get_classmap();
		}

		if ( isset( self::$classmap[ $class ] ) ) {
			require self::$classmap[ $class ];

			return true;
		}

		if ( function_exists( 'is_admin' ) && is_admin() ) {
			if ( null === self::$admin_classmap ) {
				self::$admin_classmap = self::get_admin_classmap();
			}

			if ( isset( self::$admin_classmap[ $class ] ) ) {
				require self::$admin_classmap[ $class ];

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
	 * Returns the classmap for the plugin.
	 *
	 * @return array
	 */
	private static function get_classmap() {
		return [
			'Allowed_Payment_Request_Button_Types_Update'           => __DIR__ . '/migrations/class-allowed-payment-request-button-types-update.php',
			'Migrate_Payment_Request_Data_To_Express_Checkout_Data' => __DIR__ . '/migrations/class-migrate-payment-request-data-to-express-checkout-data.php',
			'WC_Gateway_Stripe'                                     => __DIR__ . '/class-wc-gateway-stripe.php',
			'WC_Gateway_Stripe_Alipay'                              => __DIR__ . '/payment-methods/class-wc-gateway-stripe-alipay.php',
			'WC_Gateway_Stripe_Bancontact'                          => __DIR__ . '/payment-methods/class-wc-gateway-stripe-bancontact.php',
			'WC_Gateway_Stripe_Boleto'                              => __DIR__ . '/payment-methods/class-wc-gateway-stripe-boleto.php',
			'WC_Gateway_Stripe_Eps'                                 => __DIR__ . '/payment-methods/class-wc-gateway-stripe-eps.php',
			'WC_Gateway_Stripe_Giropay'                             => __DIR__ . '/payment-methods/class-wc-gateway-stripe-giropay.php',
			'WC_Gateway_Stripe_Ideal'                               => __DIR__ . '/payment-methods/class-wc-gateway-stripe-ideal.php',
			'WC_Gateway_Stripe_Multibanco'                          => __DIR__ . '/payment-methods/class-wc-gateway-stripe-multibanco.php',
			'WC_Gateway_Stripe_Oxxo'                                => __DIR__ . '/payment-methods/class-wc-gateway-stripe-oxxo.php',
			'WC_Gateway_Stripe_P24'                                 => __DIR__ . '/payment-methods/class-wc-gateway-stripe-p24.php',
			'WC_Gateway_Stripe_Sepa'                                => __DIR__ . '/payment-methods/class-wc-gateway-stripe-sepa.php',
			'WC_Gateway_Stripe_Sofort'                              => __DIR__ . '/payment-methods/class-wc-gateway-stripe-sofort.php',
			'WC_Payment_Token_ACH'                                  => __DIR__ . '/payment-tokens/class-wc-stripe-ach-payment-token.php',
			'WC_Payment_Token_ACSS'                                 => __DIR__ . '/payment-tokens/class-wc-stripe-acss-payment-token.php',
			'WC_Payment_Token_Amazon_Pay'                           => __DIR__ . '/payment-tokens/class-wc-stripe-amazon-pay-payment-token.php',
			'WC_Payment_Token_Bacs_Debit'                           => __DIR__ . '/payment-tokens/class-wc-stripe-bacs-payment-token.php',
			'WC_Payment_Token_Becs_Debit'                           => __DIR__ . '/payment-tokens/class-wc-stripe-becs-debit-payment-token.php',
			'WC_Payment_Token_CashApp'                              => __DIR__ . '/payment-tokens/class-wc-stripe-cash-app-payment-token.php',
			'WC_Payment_Token_Link'                                 => __DIR__ . '/payment-tokens/class-wc-stripe-link-payment-token.php',
			'WC_Payment_Token_SEPA'                                 => __DIR__ . '/payment-tokens/class-wc-stripe-sepa-payment-token.php',
			'WC_Stripe_Account'                                     => __DIR__ . '/class-wc-stripe-account.php',
			'WC_Stripe_Action_Scheduler_Service'                    => __DIR__ . '/class-wc-stripe-action-scheduler-service.php',
			'WC_Stripe_API'                                         => __DIR__ . '/class-wc-stripe-api.php',
			'WC_Stripe_Apple_Pay'                                   => __DIR__ . '/deprecated/class-wc-stripe-apple-pay.php',
			'WC_Stripe_Apple_Pay_Registration'                      => __DIR__ . '/class-wc-stripe-apple-pay-registration.php',
			'WC_Stripe_Blocks_Support'                              => __DIR__ . '/class-wc-stripe-blocks-support.php',
			'WC_Stripe_Co_Branded_CC_Compatibility'                 => __DIR__ . '/class-wc-stripe-co-branded-cc-compatibility.php',
			'WC_Stripe_Connect'                                     => __DIR__ . '/connect/class-wc-stripe-connect.php',
			'WC_Stripe_Connect_API'                                 => __DIR__ . '/connect/class-wc-stripe-connect-api.php',
			'WC_Stripe_Connect_REST_Controller'                     => __DIR__ . '/abstracts/abstract-wc-stripe-connect-rest-controller.php',
			'WC_Stripe_Connect_REST_Oauth_Connect_Controller'       => __DIR__ . '/connect/class-wc-stripe-connect-rest-oauth-connect-controller.php',
			'WC_Stripe_Connect_REST_Oauth_Init_Controller'          => __DIR__ . '/connect/class-wc-stripe-connect-rest-oauth-init-controller.php',
			'WC_Stripe_Currency_Code'                               => __DIR__ . '/constants/class-wc-stripe-currency-code.php',
			'WC_Stripe_Customer'                                    => __DIR__ . '/class-wc-stripe-customer.php',
			'WC_Stripe_Email_Failed_Authentication'                 => __DIR__ . '/compat/class-wc-stripe-email-failed-authentication.php',
			'WC_Stripe_Email_Failed_Authentication_Retry'           => __DIR__ . '/compat/class-wc-stripe-email-failed-authentication-retry.php',
			'WC_Stripe_Email_Failed_Preorder_Authentication'        => __DIR__ . '/compat/class-wc-stripe-email-failed-preorder-authentication.php',
			'WC_Stripe_Email_Failed_Renewal_Authentication'         => __DIR__ . '/compat/class-wc-stripe-email-failed-renewal-authentication.php',
			'WC_Stripe_Exception'                                   => __DIR__ . '/class-wc-stripe-exception.php',
			'WC_Stripe_Express_Checkout_Ajax_Handler'               => __DIR__ . '/payment-methods/class-wc-stripe-express-checkout-ajax-handler.php',
			'WC_Stripe_Express_Checkout_Element'                    => __DIR__ . '/payment-methods/class-wc-stripe-express-checkout-element.php',
			'WC_Stripe_Express_Checkout_Helper'                     => __DIR__ . '/payment-methods/class-wc-stripe-express-checkout-helper.php',
			'WC_Stripe_Feature_Flags'                               => __DIR__ . '/class-wc-stripe-feature-flags.php',
			'WC_Stripe_Fingerprint_Trait'                           => __DIR__ . '/payment-tokens/trait-wc-stripe-fingerprint.php',
			'WC_Stripe_Helper'                                      => __DIR__ . '/class-wc-stripe-helper.php',
			'WC_Stripe_Hong_Kong_States'                            => __DIR__ . '/constants/class-wc-stripe-hong-kong-states.php',
			'WC_Stripe_Intent_Controller'                           => __DIR__ . '/class-wc-stripe-intent-controller.php',
			'WC_Stripe_Intent_Status'                               => __DIR__ . '/constants/class-wc-stripe-intent-status.php',
			'WC_Stripe_Logger'                                      => __DIR__ . '/class-wc-stripe-logger.php',
			'WC_Stripe_Mode'                                        => __DIR__ . '/class-wc-stripe-mode.php',
			'WC_Stripe_Order'                                       => __DIR__ . '/class-wc-stripe-order.php',
			'WC_Stripe_Order_Handler'                               => __DIR__ . '/class-wc-stripe-order-handler.php',
			'WC_Stripe_Payment_Gateway'                             => __DIR__ . '/abstracts/abstract-wc-stripe-payment-gateway.php',
			'WC_Stripe_Payment_Gateway_Voucher'                     => __DIR__ . '/abstracts/abstract-wc-stripe-payment-gateway-voucher.php',
			'WC_Stripe_Payment_Method_Comparison_Interface'         => __DIR__ . '/payment-tokens/interface-wc-stripe-payment-method-comparison.php',
			'WC_Stripe_Payment_Method_Configurations'               => __DIR__ . '/class-wc-stripe-payment-method-configurations.php',
			'WC_Stripe_Payment_Methods'                             => __DIR__ . '/constants/class-wc-stripe-payment-methods.php',
			'WC_Stripe_Payment_Request'                             => __DIR__ . '/payment-methods/class-wc-stripe-payment-request.php',
			'WC_Stripe_Payment_Request_Button_States'               => __DIR__ . '/constants/class-wc-stripe-payment-request-button-states.php',
			'WC_Stripe_Payment_Token_CC'                            => __DIR__ . '/payment-tokens/class-wc-stripe-cc-payment-token.php',
			'WC_Stripe_Payment_Tokens'                              => __DIR__ . '/payment-tokens/class-wc-stripe-payment-tokens.php',
			'WC_Stripe_Pre_Orders_Trait'                            => __DIR__ . '/compat/trait-wc-stripe-pre-orders.php',
			'WC_Stripe_UPE_Availability_Note'                       => __DIR__ . '/notes/class-wc-stripe-upe-availability-note.php',
			'WC_Stripe_UPE_Compatibility'                           => __DIR__ . '/class-wc-stripe-upe-compatibility.php',
			'WC_Stripe_UPE_Payment_Gateway'                         => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-gateway.php',
			'WC_Stripe_UPE_Payment_Method'                          => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method.php',
			'WC_Stripe_UPE_Payment_Method_ACH'                      => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-ach.php',
			'WC_Stripe_UPE_Payment_Method_ACSS'                     => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-acss.php',
			'WC_Stripe_UPE_Payment_Method_Affirm'                   => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-affirm.php',
			'WC_Stripe_UPE_Payment_Method_Afterpay_Clearpay'        => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-afterpay-clearpay.php',
			'WC_Stripe_UPE_Payment_Method_Alipay'                   => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-alipay.php',
			'WC_Stripe_UPE_Payment_Method_Amazon_Pay'               => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-amazon-pay.php',
			'WC_Stripe_UPE_Payment_Method_Bacs_Debit'               => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-bacs-debit.php',
			'WC_Stripe_UPE_Payment_Method_Bancontact'               => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-bancontact.php',
			'WC_Stripe_UPE_Payment_Method_Becs_Debit'               => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-becs-debit.php',
			'WC_Stripe_UPE_Payment_Method_BLIK'                     => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-blik.php',
			'WC_Stripe_UPE_Payment_Method_Boleto'                   => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-boleto.php',
			'WC_Stripe_UPE_Payment_Method_Cash_App_Pay'             => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-cash-app-pay.php',
			'WC_Stripe_UPE_Payment_Method_CC'                       => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-cc.php',
			'WC_Stripe_UPE_Payment_Method_Eps'                      => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-eps.php',
			'WC_Stripe_UPE_Payment_Method_Giropay'                  => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-giropay.php',
			'WC_Stripe_UPE_Payment_Method_Ideal'                    => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-ideal.php',
			'WC_Stripe_UPE_Payment_Method_Klarna'                   => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-klarna.php',
			'WC_Stripe_UPE_Payment_Method_Link'                     => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-link.php',
			'WC_Stripe_UPE_Payment_Method_Multibanco'               => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-multibanco.php',
			'WC_Stripe_UPE_Payment_Method_Oxxo'                     => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-oxxo.php',
			'WC_Stripe_UPE_Payment_Method_P24'                      => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-p24.php',
			'WC_Stripe_UPE_Payment_Method_Sepa'                     => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-sepa.php',
			'WC_Stripe_UPE_Payment_Method_Sofort'                   => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-sofort.php',
			'WC_Stripe_UPE_Payment_Method_Wechat_Pay'               => __DIR__ . '/payment-methods/class-wc-stripe-upe-payment-method-wechat-pay.php',
			'WC_Stripe_UPE_StripeLink_Note'                         => __DIR__ . '/notes/class-wc-stripe-upe-stripe-link-note.php',
			'WC_Stripe_Status'                                      => __DIR__ . '/class-wc-stripe-status.php',
			'WC_Stripe_Subscriptions_Helper'                        => __DIR__ . '/compat/class-wc-stripe-subscriptions-helper.php',
			'WC_Stripe_Subscriptions_Legacy_SEPA_Token_Update'      => __DIR__ . '/compat/class-wc-stripe-subscriptions-legacy-sepa-token-update.php',
			'WC_Stripe_Subscriptions_Trait'                         => __DIR__ . '/compat/trait-wc-stripe-subscriptions.php',
			'WC_Stripe_Subscriptions_Utilities_Trait'               => __DIR__ . '/compat/trait-wc-stripe-subscriptions-utilities.php',
			'WC_Stripe_Webhook_Handler'                             => __DIR__ . '/class-wc-stripe-webhook-handler.php',
			'WC_Stripe_Webhook_State'                               => __DIR__ . '/class-wc-stripe-webhook-state.php',
			'WC_Stripe_Woo_Compat_Utils'                            => __DIR__ . '/compat/class-wc-stripe-woo-compat-utils.php',
		];
	}

	/**
	 * Returns the classmap for admin-specific classes for the plugin.
	 *
	 * @return array
	 */
	private static function get_admin_classmap() {
		return [
			'WC_REST_Stripe_Account_Controller'                     => __DIR__ . '/admin/class-wc-rest-stripe-account-controller.php',
			'WC_REST_Stripe_Account_Keys_Controller'                => __DIR__ . '/admin/class-wc-rest-stripe-account-keys-controller.php',
			'WC_REST_Stripe_Connection_Tokens_Controller'           => __DIR__ . '/admin/class-wc-rest-stripe-connection-tokens-controller.php',
			'WC_REST_Stripe_Locations_Controller'                   => __DIR__ . '/admin/class-wc-rest-stripe-locations-controller.php',
			'WC_REST_Stripe_Orders_Controller'                      => __DIR__ . '/admin/class-wc-rest-stripe-orders-controller.php',
			'WC_REST_Stripe_Settings_Controller'                    => __DIR__ . '/admin/class-wc-rest-stripe-settings-controller.php',
			'WC_REST_Stripe_Tokens_Controller'                      => __DIR__ . '/admin/class-wc-rest-stripe-tokens-controller.php',
			'WC_Stripe_Admin_Inbox_Notes'                           => __DIR__ . '/admin/class-wc-stripe-inbox-notes.php',
			'WC_Stripe_Admin_Notices'                               => __DIR__ . '/admin/class-wc-stripe-admin-notices.php',
			'WC_Stripe_Admin_UPE_Compatibility_Controller'          => __DIR__ . '/admin/class-wc-stripe-upe-compatibility-controller.php',
			'WC_Stripe_Amazon_Pay_Controller'                       => __DIR__ . '/admin/class-wc-stripe-amazon-pay-controller.php',
			// Disabled as this class is used in non-admin contexts, and it instantiates the class as a side effect.
			//'WC_Stripe_Inbox_Notes'                                 => __DIR__ . '/admin/class-wc-stripe-inbox-notes.php',
			'WC_Stripe_Payment_Gateways_Controller'                 => __DIR__ . '/admin/class-wc-stripe-payment-gateways-controller.php',
			'WC_Stripe_Payment_Requests_Controller'                 => __DIR__ . '/admin/class-wc-stripe-payment-requests-controller.php',
			'WC_Stripe_Privacy'                                     => __DIR__ . '/admin/class-wc-stripe-privacy.php',
			'WC_Stripe_REST_Base_Controller'                        => __DIR__ . '/admin/class-wc-stripe-rest-base-controller.php',
			'WC_Stripe_REST_UPE_Flag_Toggle_Controller'             => __DIR__ . '/admin/class-wc-stripe-rest-upe-flag-toggle-controller.php',
			'WC_Stripe_Settings_Controller'                         => __DIR__ . '/admin/class-wc-stripe-settings-controller.php',
			'WC_Stripe_UPE_Compatibility_Controller'                => __DIR__ . '/admin/class-wc-stripe-upe-compatibility-controller.php',
		];
	}
}

WC_Stripe_Autoloader::init();
