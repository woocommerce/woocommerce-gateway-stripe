/* global wc_stripe_success_params */
/* global Stripe */

jQuery( function ( $ ) {
	'use strict';

	try {
		var stripe = Stripe( wc_stripe_success_params.key, {
			locale: wc_stripe_success_params.stripe_locale || 'auto',
		} );
	} catch( error ) {
		console.log( error );
		return;
	}

	var wc_stripe_success = {

		init: function () {
			wc_stripe_success.handleBoleto();
		},

		/**
		 * Will show a modal for scanning a boleto bar code.
		 * After the customer closes the modal proceeds with checkout normally
		 */
		handleBoleto: function () {
			stripe.confirmBoletoPayment(
				wc_stripe_success_params.client_secret,
				{
					payment_method: {
						boleto: {
							tax_id: wc_stripe_success_params.tax_id,
						},
						billing_details: {
							name: wc_stripe_success_params.customer_name,
							email: wc_stripe_success_params.customer_email,
							address: {
								line1: wc_stripe_success_params.customer_address_line1,
								city: wc_stripe_success_params.customer_city,
								state: wc_stripe_success_params.customer_state,
								postal_code: wc_stripe_success_params.customer_postal_code,
								country: 'BR',
							},
						},
					},
				})
				.then( function ( response ) {
					if ( response.error ) {
						$('.woocommerce-error').remove();

						var message = wc_stripe_success_params.default_error_message;
						if ( wc_stripe_success_params.hasOwnProperty(response.error.code) ) {
							message = wc_stripe_success_params[ response.error.code ];
						}

						var error = $( '<div><ul class="woocommerce-error"><li>' + message + '<li /></ul></div>' );
						return;
					}
				} );
		},
	};

	wc_stripe_success.init();
} );
