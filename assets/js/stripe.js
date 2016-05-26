/* global wc_stripe_params */
Stripe.setPublishableKey( wc_stripe_params.key );

jQuery( function( $ ) {

	/* Open and close for legacy class */
	jQuery( "form.checkout, form#order_review" ).on('change', 'input[name="wc-stripe-payment-token"]', function() {
		if ( jQuery( '.stripe-legacy-payment-fields input[name="wc-stripe-payment-token"]:checked' ).val() == 'new' ) {
			jQuery( '.stripe-legacy-payment-fields #stripe-payment-data' ).slideDown( 200 );
		} else {
			jQuery( '.stripe-legacy-payment-fields #stripe-payment-data' ).slideUp( 200 );
		}
	} );

	/**
	 * Object to handle Stripe payment forms.
	 */
	var wc_stripe_form = {

		/**
		 * Initialize event handlers and UI state.
		 */
		init: function( form ) {
			this.form = form;

			$( this.form )
				.on(
					'submit checkout_place_order_stripe',
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
			$( '.woocommerce-error, .stripe_token' ).remove();
			$( '#stripe-card-number' ).closest( 'p' ).before( '<ul class="woocommerce_error woocommerce-error"><li>' + responseObject.response.error.message + '</li></ul>' );
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
					address    = {

					},
					data       = {
						number   : card,
						cvc      : cvc,
						exp_month: parseInt( expires['month'] ) || 0,
						exp_year : parseInt( expires['year'] ) || 0,
						name     : first_name + ' ' + last_name

					};

				if ( jQuery('#billing_address_1').length > 0 ) {
					data.address_line1   = $( '#billing_address_1' ).val();
					data.address_line2   = $( '#billing_address_2' ).val();
					data.address_state   = $( '#billing_state' ).val();
					data.address_city    = $( '#billing_city' ).val();
					data.address_zip     = $( '#billing_postcode' ).val();
					data.address_country = $( '#billing_country' ).val();
				} else if ( data.address_line1 ) {
					data.address_line1   = wc_stripe_params.billing_address_1;
					data.address_line2   = wc_stripe_params.billing_address_2;
					data.address_state   = wc_stripe_params.billing_state;
					data.address_city    = wc_stripe_params.billing_city;
					data.address_zip     = wc_stripe_params.billing_postcode;
					data.address_country = wc_stripe_params.billing_country;
				}

				Stripe.createToken( data, wc_stripe_form.onStripeReponse );

				// Prevent form submitting
				return false;
			}
		},

		onCCFormChange: function() {
			$( '.woocommerce-error, .stripe_token' ).remove();
		},

		onStripeReponse: function( status, response ) {
			if ( response.error ) {
				$( document ).trigger( 'stripeError', { response: response } );
			} else {
				// token contains id, last4, and card type
				var token = response['id'];

				// insert the token into the form so it gets submitted to the server
				wc_stripe_form.form.append( "<input type='hidden' class='stripe_token' name='stripe_token' value='" + token + "'/>" );
				wc_stripe_form.form.submit();
			}
		}
	};

	wc_stripe_form.init( $( "form.checkout, form#order_review, form#add_payment_method" ) );
} );
