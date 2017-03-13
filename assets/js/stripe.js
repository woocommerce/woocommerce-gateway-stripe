/* global wc_stripe_params */
Stripe.setPublishableKey( wc_stripe_params.key );

jQuery( function( $ ) {
	'use strict';

	/* Open and close for legacy class */
	$( 'form.checkout, form#order_review' ).on( 'change', 'input[name="wc-stripe-payment-token"]', function() {
		if ( 'new' === $( '.stripe-legacy-payment-fields input[name="wc-stripe-payment-token"]:checked' ).val() ) {
			$( '.stripe-legacy-payment-fields #stripe-payment-data' ).slideDown( 200 );
		} else {
			$( '.stripe-legacy-payment-fields #stripe-payment-data' ).slideUp( 200 );
		}
	} );

	/**
	 * Object to handle Stripe payment forms.
	 */
	var wc_stripe_form = {

		/**
		 * Initialize event handlers and UI state.
		 */
		init: function() {
			// checkout page
			if ( $( 'form.woocommerce-checkout' ).length ) {
				this.form = $( 'form.woocommerce-checkout' );
			}

			$( 'form.woocommerce-checkout' )
				.on(
					'checkout_place_order_stripe',
					this.onSubmit
				);

			// pay order page
			if ( $( 'form#order_review' ).length ) {
				this.form = $( 'form#order_review' );
			}

			$( 'form#order_review' )
				.on(
					'submit',
					this.onSubmit
				);

			// add payment method page
			if ( $( 'form#add_payment_method' ).length ) {
				this.form = $( 'form#add_payment_method' );
			}

			$( 'form#add_payment_method' )
				.on(
					'submit',
					this.onSubmit
				);

			$( document )
				.on(
					'change',
					'#wc-stripe-cc-form :input',
					this.onCCFormChange
				)
				.on(
					'stripeError',
					this.onError
				)
				.on(
					'checkout_error',
					this.clearToken
				);
		},

		isStripeChosen: function() {
			return $( '#payment_method_stripe' ).is( ':checked' ) && ( ! $( 'input[name="wc-stripe-payment-token"]:checked' ).length || 'new' === $( 'input[name="wc-stripe-payment-token"]:checked' ).val() );
		},

		hasToken: function() {
			return 0 < $( 'input.stripe_token' ).length;
		},

		block: function() {
			wc_stripe_form.form.block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
		},

		unblock: function() {
			wc_stripe_form.form.unblock();
		},

		onError: function( e, responseObject ) {
			var message = responseObject.response.error.message;

			// Customers do not need to know the specifics of the below type of errors
			// therefore return a generic localizable error message.
			if ( 
				'invalid_request_error' === responseObject.response.error.type ||
				'api_connection_error'  === responseObject.response.error.type ||
				'api_error'             === responseObject.response.error.type ||
				'authentication_error'  === responseObject.response.error.type ||
				'rate_limit_error'      === responseObject.response.error.type
			) {
				message = wc_stripe_params.invalid_request_error;
			}

			if ( 'card_error' === responseObject.response.error.type && wc_stripe_params.hasOwnProperty( responseObject.response.error.code ) ) {
				message = wc_stripe_params[ responseObject.response.error.code ];
			}

			$( '.wc-stripe-error, .stripe_token' ).remove();
			$( '#stripe-card-number' ).closest( 'p' ).before( '<ul class="woocommerce_error woocommerce-error wc-stripe-error"><li>' + message + '</li></ul>' );
			wc_stripe_form.unblock();
		},

		onSubmit: function( e ) {
			if ( wc_stripe_form.isStripeChosen() && ! wc_stripe_form.hasToken() ) {
				e.preventDefault();
				wc_stripe_form.block();

				var card       = $( '#stripe-card-number' ).val(),
					cvc        = $( '#stripe-card-cvc' ).val(),
					expires    = $( '#stripe-card-expiry' ).payment( 'cardExpiryVal' ),
					first_name = $( '#billing_first_name' ).length ? $( '#billing_first_name' ).val() : wc_stripe_params.billing_first_name,
					last_name  = $( '#billing_last_name' ).length ? $( '#billing_last_name' ).val() : wc_stripe_params.billing_last_name,
					data       = {
						number   : card,
						cvc      : cvc,
						exp_month: parseInt( expires.month, 10 ) || 0,
						exp_year : parseInt( expires.year, 10 ) || 0
					};

				if ( first_name && last_name ) {
					data.name = first_name + ' ' + last_name;
				}

				if ( $( '#billing_address_1' ).length > 0 ) {
					data.address_line1   = $( '#billing_address_1' ).val();
					data.address_line2   = $( '#billing_address_2' ).val();
					data.address_state   = $( '#billing_state' ).val();
					data.address_city    = $( '#billing_city' ).val();
					data.address_zip     = $( '#billing_postcode' ).val();
					data.address_country = $( '#billing_country' ).val();
				} else if ( wc_stripe_params.billing_address_1 ) {
					data.address_line1   = wc_stripe_params.billing_address_1;
					data.address_line2   = wc_stripe_params.billing_address_2;
					data.address_state   = wc_stripe_params.billing_state;
					data.address_city    = wc_stripe_params.billing_city;
					data.address_zip     = wc_stripe_params.billing_postcode;
					data.address_country = wc_stripe_params.billing_country;
				}

				Stripe.createToken( data, wc_stripe_form.onStripeResponse );

				// Prevent form submitting
				return false;
			}
		},

		onCCFormChange: function() {
			$( '.wc-stripe-error, .stripe_token' ).remove();
		},

		onStripeResponse: function( status, response ) {
			if ( response.error ) {
				$( document ).trigger( 'stripeError', { response: response } );
			} else {
				// check if we allow prepaid cards
				if ( 'no' === wc_stripe_params.allow_prepaid_card && 'prepaid' === response.card.funding ) {
					response.error = { message: wc_stripe_params.no_prepaid_card_msg };

					$( document ).trigger( 'stripeError', { response: response } );
					
					return false;
				}

				// token contains id, last4, and card type
				var token = response.id;

				// insert the token into the form so it gets submitted to the server
				wc_stripe_form.form.append( "<input type='hidden' class='stripe_token' name='stripe_token' value='" + token + "'/>" );
				wc_stripe_form.form.submit();
			}
		},

		clearToken: function() {
			$( '.stripe_token' ).remove();
		}
	};

	wc_stripe_form.init();
} );
