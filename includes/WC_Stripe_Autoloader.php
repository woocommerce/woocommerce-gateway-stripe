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
			'allowed_payment_request_button_types_update'  => __DIR__ . '/Migrations/Allowed_Payment_Request_Button_Types_Update.php',
			'migrate_payment_request_data_to_express_checkout_data' => __DIR__ . '/Migrations/Migrate_Payment_Request_Data_To_Express_Checkout_Data.php',
			'wc_gateway_stripe'                            => __DIR__ . '/WC_Gateway_Stripe.php',
			'wc_gateway_stripe_alipay'                     => __DIR__ . '/PaymentMethods/WC_Gateway_Stripe_Alipay.php',
			'wc_gateway_stripe_bancontact'                 => __DIR__ . '/PaymentMethods/WC_Gateway_Stripe_Bancontact.php',
			'wc_gateway_stripe_boleto'                     => __DIR__ . '/PaymentMethods/WC_Gateway_Stripe_Boleto.php',
			'wc_gateway_stripe_eps'                        => __DIR__ . '/PaymentMethods/WC_Gateway_Stripe_Eps.php',
			'wc_gateway_stripe_giropay'                    => __DIR__ . '/PaymentMethods/WC_Gateway_Stripe_Giropay.php',
			'wc_gateway_stripe_ideal'                      => __DIR__ . '/PaymentMethods/WC_Gateway_Stripe_Ideal.php',
			'wc_gateway_stripe_multibanco'                 => __DIR__ . '/PaymentMethods/WC_Gateway_Stripe_Multibanco.php',
			'wc_gateway_stripe_oxxo'                       => __DIR__ . '/PaymentMethods/WC_Gateway_Stripe_Oxxo.php',
			'wc_gateway_stripe_p24'                        => __DIR__ . '/PaymentMethods/WC_Gateway_Stripe_P24.php',
			'wc_gateway_stripe_sepa'                       => __DIR__ . '/PaymentMethods/WC_Gateway_Stripe_Sepa.php',
			'wc_gateway_stripe_sofort'                     => __DIR__ . '/PaymentMethods/WC_Gateway_Stripe_Sofort.php',
			'wc_payment_token_ach'                         => __DIR__ . '/PaymentTokens/WC_Payment_Token_ACH.php',
			'wc_payment_token_acss'                        => __DIR__ . '/PaymentTokens/WC_Payment_Token_ACSS.php',
			'wc_payment_token_amazon_pay'                  => __DIR__ . '/PaymentTokens/WC_Payment_Token_Amazon_Pay.php',
			'wc_payment_token_bacs_debit'                  => __DIR__ . '/PaymentTokens/WC_Payment_Token_Bacs_Debit.php',
			'wc_payment_token_becs_debit'                  => __DIR__ . '/PaymentTokens/WC_Payment_Token_Becs_Debit.php',
			'wc_payment_token_cashapp'                     => __DIR__ . '/PaymentTokens/WC_Payment_Token_CashApp.php',
			'wc_payment_token_link'                        => __DIR__ . '/PaymentTokens/WC_Payment_Token_Link.php',
			'wc_payment_token_sepa'                        => __DIR__ . '/PaymentTokens/WC_Payment_Token_SEPA.php',
			'wc_rest_stripe_account_controller'            => __DIR__ . '/Admin/WC_REST_Stripe_Account_Controller.php',
			'wc_rest_stripe_account_keys_controller'       => __DIR__ . '/Admin/WC_REST_Stripe_Account_Keys_Controller.php',
			'wc_rest_stripe_connection_tokens_controller'  => __DIR__ . '/Admin/WC_REST_Stripe_Connection_Tokens_Controller.php',
			'wc_rest_stripe_locations_controller'          => __DIR__ . '/Admin/WC_REST_Stripe_Locations_Controller.php',
			'wc_rest_stripe_orders_controller'             => __DIR__ . '/Admin/WC_REST_Stripe_Orders_Controller.php',
			'wc_rest_stripe_settings_controller'           => __DIR__ . '/Admin/WC_REST_Stripe_Settings_Controller.php',
			'wc_rest_stripe_tokens_controller'             => __DIR__ . '/Admin/WC_REST_Stripe_Tokens_Controller.php',
			'wc_stripe'                                    => __DIR__ . '/WC_Stripe.php',
			'wc_stripe_account'                            => __DIR__ . '/WC_Stripe_Account.php',
			'wc_stripe_action_scheduler_service'           => __DIR__ . '/WC_Stripe_Action_Scheduler_Service.php',
			'wc_stripe_admin_upe_compatibility_controller' => __DIR__ . '/Admin/WC_Stripe_UPE_Compatibility_Controller.php',
			'wc_stripe_amazon_pay_controller'              => __DIR__ . '/Admin/WC_Stripe_Amazon_Pay_Controller.php',
			'wc_stripe_api'                                => __DIR__ . '/WC_Stripe_API.php',
			'wc_stripe_apple_pay'                          => __DIR__ . '/Deprecated/WC_Stripe_Apple_Pay.php',
			'wc_stripe_apple_pay_registration'             => __DIR__ . '/WC_Stripe_Apple_Pay_Registration.php',
			'wc_stripe_blocks_support'                     => __DIR__ . '/WC_Stripe_Blocks_Support.php',
			'wc_stripe_co_branded_cc_compatibility'        => __DIR__ . '/WC_Stripe_Co_Branded_CC_Compatibility.php',
			'wc_stripe_connect'                            => __DIR__ . '/Connect/WC_Stripe_Connect.php',
			'wc_stripe_connect_api'                        => __DIR__ . '/Connect/WC_Stripe_Connect_API.php',
			'wc_stripe_connect_rest_controller'            => __DIR__ . '/Abstracts/WC_Stripe_Connect_REST_Controller.php',
			'wc_stripe_connect_rest_oauth_connect_controller' => __DIR__ . '/Connect/WC_Stripe_Connect_REST_Oauth_Connect_Controller.php',
			'wc_stripe_connect_rest_oauth_init_controller' => __DIR__ . '/Connect/WC_Stripe_Connect_REST_Oauth_Init_Controller.php',
			'wc_stripe_currency_code'                      => __DIR__ . '/Constants/WC_Stripe_Currency_Code.php',
			'wc_stripe_customer'                           => __DIR__ . '/WC_Stripe_Customer.php.php',
			'wc_stripe_database_cache'                     => __DIR__ . '/WC_Stripe_Database_Cache.php',
			'wc_stripe_email_failed_authentication'        => __DIR__ . '/Compat/WC_Stripe_Email_Failed_Authentication.php',
			'wc_stripe_email_failed_authentication_retry'  => __DIR__ . '/Compat/WC_Stripe_Email_Failed_Authentication_Retry.php',
			'wc_stripe_email_failed_preorder_authentication' => __DIR__ . '/Compat/WC_Stripe_Email_Failed_Preorder_Authentication.php',
			'wc_stripe_email_failed_renewal_authentication' => __DIR__ . '/Compat/WC_Stripe_Email_Failed_Renewal_Authentication.php',
			'wc_stripe_exception'                          => __DIR__ . '/WC_Stripe_Exception.php.php',
			'wc_stripe_express_checkout_ajax_handler'      => __DIR__ . '/PaymentMethods/WC_Stripe_Express_Checkout_Ajax_Handler.php',
			'wc_stripe_express_checkout_element'           => __DIR__ . '/PaymentMethods/WC_Stripe_Express_Checkout_Element.php',
			'wc_stripe_express_checkout_helper'            => __DIR__ . '/PaymentMethods/WC_Stripe_Express_Checkout_Helper.php',
			'wc_stripe_feature_flags'                      => __DIR__ . '/WC_Stripe_Feature_Flags.php',
			'wc_stripe_fingerprint_trait'                  => __DIR__ . '/PaymentTokens/WC_Stripe_Fingerprint_Trait.php',
			'wc_stripe_helper'                             => __DIR__ . '/WC_Stripe_Helper.php',
			'wc_stripe_hong_kong_states'                   => __DIR__ . '/Constants/WC_Stripe_Hong_Kong_States.php',
			'wc_stripe_inbox_notes'                        => __DIR__ . '/Admin/WC_Stripe_Inbox_Notes.php',
			'wc_stripe_intent_controller'                  => __DIR__ . '/WC_Stripe_Intent_Controller.php',
			'wc_stripe_intent_status'                      => __DIR__ . '/Constants/WC_Stripe_Intent_Status.php',
			'wc_stripe_logger'                             => __DIR__ . '/WC_Stripe_Logger.php',
			'wc_stripe_mode'                               => __DIR__ . '/WC_Stripe_Mode.php',
			'wc_stripe_order'                              => __DIR__ . '/WC_Stripe_Order.php',
			'wc_stripe_order_handler'                      => __DIR__ . '/WC_Stripe_Order_Handler.php',
			'wc_stripe_payment_gateway'                    => __DIR__ . '/Abstracts/WC_Stripe_Payment_Gateway.php',
			'wc_stripe_payment_gateway_voucher'            => __DIR__ . '/Abstracts/WC_Stripe_Payment_Gateway_Voucher.php',
			'wc_stripe_payment_gateways_controller'        => __DIR__ . '/Admin/WC_Stripe_Payment_Gateways_Controller.php',
			'wc_stripe_payment_method_comparison_interface' => __DIR__ . '/PaymentTokens/WC_Stripe_Payment_Method_Comparison_Interface.php',
			'wc_stripe_payment_method_configurations'      => __DIR__ . '/WC_Stripe_Payment_Method_Configurations.php',
			'wc_stripe_payment_methods'                    => __DIR__ . '/Constants/WC_Stripe_Payment_Methods.php',
			'wc_stripe_payment_request'                    => __DIR__ . '/PaymentMethods/WC_Stripe_Payment_Request.php',
			'wc_stripe_payment_request_button_states'      => __DIR__ . '/Constants/WC_Stripe_Payment_Request_Button_States.php',
			'wc_stripe_payment_requests_controller'        => __DIR__ . '/Admin/WC_Stripe_Payment_Requests_Controller.php',
			'wc_stripe_payment_token_cc'                   => __DIR__ . '/PaymentTokens/WC_Stripe_Payment_Token_CC.php',
			'wc_stripe_payment_tokens'                     => __DIR__ . '/PaymentTokens/WC_Stripe_Payment_Tokens.php',
			'wc_stripe_pre_orders_trait'                   => __DIR__ . '/Compat/WC_Stripe_Pre_Orders_Trait.php',
			'wc_stripe_rest_base_controller'               => __DIR__ . '/Admin/WC_Stripe_REST_Base_Controller.php',
			'wc_stripe_rest_upe_flag_toggle_controller'    => __DIR__ . '/Admin/WC_Stripe_REST_UPE_Flag_Toggle_Controller.php',
			'wc_stripe_settings_controller'                => __DIR__ . '/Admin/WC_Stripe_Settings_Controller.php',
			'wc_stripe_status'                             => __DIR__ . '/WC_Stripe_Status.php',
			'wc_stripe_subscriptions_helper'               => __DIR__ . '/Compat/WC_Stripe_Subscriptions_Helper.php',
			'wc_stripe_subscriptions_legacy_sepa_token_update' => __DIR__ . '/Compat/WC_Stripe_Subscriptions_Legacy_SEPA_Token_Update.php',
			'wc_stripe_subscriptions_trait'                => __DIR__ . '/Compat/WC_Stripe_Subscriptions_Trait.php',
			'wc_stripe_subscriptions_utilities_trait'      => __DIR__ . '/Compat/WC_Stripe_Subscriptions_Utilities_Trait.php',
			'wc_stripe_upe_availability_note'              => __DIR__ . '/Notes/WC_Stripe_UPE_Availability_Note.php',
			'wc_stripe_upe_compatibility'                  => __DIR__ . '/WC_Stripe_UPE_Compatibility.php',
			'wc_stripe_upe_compatibility_controller'       => __DIR__ . '/Admin/WC_Stripe_UPE_Compatibility_Controller.php',
			'wc_stripe_upe_payment_gateway'                => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Gateway.php',
			'wc_stripe_upe_payment_method'                 => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method.php',
			'wc_stripe_upe_payment_method_ach'             => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_ACH.php',
			'wc_stripe_upe_payment_method_acss'            => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_ACSS.php',
			'wc_stripe_upe_payment_method_affirm'          => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_Affirm.php',
			'wc_stripe_upe_payment_method_afterpay_clearpay' => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_Afterpay_Clearpay.php',
			'wc_stripe_upe_payment_method_alipay'          => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_Alipay.php',
			'wc_stripe_upe_payment_method_amazon_pay'      => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_Amazon_Pay.php',
			'wc_stripe_upe_payment_method_bacs_debit'      => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_Bacs_Debit.php',
			'wc_stripe_upe_payment_method_bancontact'      => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_Bancontact.php',
			'wc_stripe_upe_payment_method_becs_debit'      => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_Becs_Debit.php',
			'wc_stripe_upe_payment_method_blik'            => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_BLIK.php',
			'wc_stripe_upe_payment_method_boleto'          => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_Boleto.php',
			'wc_stripe_upe_payment_method_cash_app_pay'    => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_Cash_App_Pay.php',
			'wc_stripe_upe_payment_method_cc'              => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_CC.php',
			'wc_stripe_upe_payment_method_eps'             => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_Eps.php',
			'wc_stripe_upe_payment_method_giropay'         => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_Giropay.php',
			'wc_stripe_upe_payment_method_ideal'           => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_Ideal.php',
			'wc_stripe_upe_payment_method_klarna'          => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_Klarna.php',
			'wc_stripe_upe_payment_method_link'            => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_Link.php',
			'wc_stripe_upe_payment_method_multibanco'      => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_Multibanco.php',
			'wc_stripe_upe_payment_method_oxxo'            => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_Oxxo.php',
			'wc_stripe_upe_payment_method_p24'             => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_P24.php',
			'wc_stripe_upe_payment_method_sepa'            => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_Sepa.php',
			'wc_stripe_upe_payment_method_sofort'          => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_Sofort.php',
			'wc_stripe_upe_payment_method_wechat_pay'      => __DIR__ . '/PaymentMethods/WC_Stripe_UPE_Payment_Method_Wechat_Pay.php',
			'wc_stripe_upe_stripelink_note'                => __DIR__ . '/Notes/WC_Stripe_UPE_StripeLink_Note.php',
			'wc_stripe_webhook_handler'                    => __DIR__ . '/WC_Stripe_Webhook_Handler.php',
			'wc_stripe_webhook_state'                      => __DIR__ . '/WC_Stripe_Webhook_State.php',
			'wc_stripe_woo_compat_utils'                   => __DIR__ . '/Compat/WC_Stripe_Woo_Compat_Utils.php',
			'wc_stripe_database_cache'                     => __DIR__ . '/WC_Stripe_Database_Cache.php',
		];
	}

	/**
	 * Returns the classmap for admin-specific classes for the plugin.
	 *
	 * @return array
	 */
	private static function get_admin_classmap() {
		return [
			'wc_stripe_admin_inbox_notes' => __DIR__ . '/Admin/WC_Stripe_Inbox_Notes.php',
			'wc_stripe_admin_notices'     => __DIR__ . '/Admin/WC_Stripe_Admin_Notices.php',
			'wc_stripe_privacy'           => __DIR__ . '/Admin/WC_Stripe_Privacy.php',
		];
	}
}
