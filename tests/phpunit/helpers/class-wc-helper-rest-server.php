<?php

class WC_Helper_Rest_Server {
    public static function reset_rest_server($country = 'US') {
        // Aqui se deberia quizás primero habilitar UPE?
        // Aunque ya está habilitado por defecto?
		// $settings = WC_Stripe_Helper::get_stripe_settings();
		// $settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] = 'yes';
		// WC_Stripe_Helper::update_main_stripe_settings( $settings );
        // TODO: Explicar esto
		$upe_helper = new UPE_Test_Helper();
		update_option( '_wcstripe_feature_upe', 'yes' );
		$upe_helper->enable_upe();

        // Reemplazamos la instancia interna de WC_Stripe_UPE_Payment_Gateway
        // almacenado en la propiedad $stripe_gateway de la clase WC_Stripe
        $closure = Closure::bind(
            function () {
                $this->stripe_gateway = null;
            },
            woocommerce_gateway_stripe(),
            WC_Stripe::class
        );
        $closure();

        // TODO: Ver si esto es realmente necesario
		WC()->payment_gateways()->payment_gateways = [];
		WC()->payment_gateways()->init();
		WC_Stripe_Helper::$stripe_legacy_gateways = [];

        // Actualiza la configuracion del plugin de Stripe, 
        // Esto comprende los campos de formulario de admin includes/admin/stripe-settings.php
        // y algunos otros

        // array (
        //     'enabled' => 'yes',
        //     'title' => 'Credit Card (Stripe)',
        //     'description' => 'Pay with your credit card via Stripe.',
        //     'api_credentials' => '',
        //     'testmode' => 'yes',
        //     'test_publishable_key' => 'pk_test_51Ja916HJAl4h87JObUIt4zkPVW0UU4tUbJHfoBI1t0rpWdugrxxV9WUwAy8MxcTD5MXb8yYr43Bhdh0KB03PiTpk006b5juGeu',
        //     'test_secret_key' => 'sk_test_51Ja916HJAl4h87JODCpwTxpNk41LB3sLU2TSwUhrkEy7cqyQ9uarAFiGPTp3Cl1At3qgyPWYrudsdlv0XcmZs2bH000NgEGVow',
        //     'publishable_key' => '',
        //     'secret_key' => '',
        //     'webhook' => '',
        //     'test_webhook_secret' => 'whsec_b83d6b8a2c8d51a0075b2f233156922e4848b52bbae97d484ddc8543d4639724',
        //     'webhook_secret' => '',
        //     'inline_cc_form' => 'no',
        //     'statement_descriptor' => '',
        //     'short_statement_descriptor' => '',
        //     'capture' => 'yes',
        //     'payment_request' => 'no',
        //     'payment_request_button_type' => 'default',
        //     'payment_request_button_theme' => 'dark',
        //     'payment_request_button_locations' =>
        //     array (
        //       0 => 'product',
        //       1 => 'cart',
        //       2 => 'checkout',
        //     ),
        //     'payment_request_button_size' => 'default',
        //     'saved_cards' => 'yes',
        //     'logging' => 'yes',
        //     'upe_checkout_experience_enabled' => 'yes',
        //     'upe_checkout_experience_accepted_payments' =>
        //     array (
        //       0 => 'card',
        //       1 => 'wechat_pay',
        //       2 => 'cashapp',
        //     ),
        //     'test_connection_type' => 'connect',
        //     'apple_pay_verified_domain' => 'wcstripe.test',
        //     'apple_pay_domain_set' => 'no',
        //     'test_webhook_data' =>
        //     array (
        //       'id' => 'we_1QhkMZHJAl4h87JOZ8KtlV4f',
        //       'url' => 'https://wcstripe.test/?wc-api=wc_stripe',
        //       'secret' => 'sk_test_51Ja916HJAl4h87JODCpwTxpNk41LB3sLU2TSwUhrkEy7cqyQ9uarAFiGPTp3Cl1At3qgyPWYrudsdlv0XcmZs2bH000NgEGVow',
        //     ),
        //     'stripe_upe_payment_method_order' =>
        //     array (
        //       0 => 'card',
        //       1 => 'alipay',
        //       2 => 'klarna',
        //       3 => 'afterpay_clearpay',
        //       4 => 'eps',
        //       5 => 'bancontact',
        //       6 => 'boleto',
        //       7 => 'ideal',
        //       8 => 'oxxo',
        //       9 => 'sepa_debit',
        //       10 => 'p24',
        //       11 => 'multibanco',
        //       12 => 'link',
        //       13 => 'wechat_pay',
        //       14 => 'us_bank_account',
        //       15 => 'affirm',
        //       16 => 'cashapp',
        //     ),
        //     'is_short_statement_descriptor_enabled' => 'no',
        //     'sepa_tokens_for_other_methods' => 'yes',
        //     'amazon_pay' => 'no',
        //     'amazon_pay_button_size' => 'default',
        //     'amazon_pay_button_locations' =>
        //     array (
        //       0 => 'product',
        //       1 => 'cart',
        //     ),
        //   )
		$stripe_settings                         = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = 'pk_test_key';
		$stripe_settings['test_secret_key']      = 'sk_test_key';
		// $stripe_settings['country']              = 'GB'; // parece error,

		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
    
        // actualizamos el trasient wcstripe_account_data_test el cual es usado por WC_Stripe_Account
        // TODO: Especificar por qué es necesrio hacer esto.
		$account = [
			'country'      => $country,
			'capabilities' => [],
		];
		set_transient( 'wcstripe_account_data_test', $account );

        // TODO: quiza lo de arriba sirva para el gateway?
		$new_gateway = WC()->payment_gateways()->payment_gateways()[ WC_Gateway_Stripe::ID ];

        return $new_gateway;
    }
}
