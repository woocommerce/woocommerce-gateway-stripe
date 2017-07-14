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

	var stripe   = Stripe( wc_stripe_params.key ),
		elements = stripe.elements(),
		stripe_card;

	/**
	 * Object to handle Stripe elements payment form.
	 */
	var wc_stripe_elements_form = {

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
					'stripeError',
					this.onError
				)
				.on(
					'checkout_error',
					this.clearToken
				);

			var style = {
				base: {
					iconColor: '#666EE8',
					color: '#31325F',
					lineHeight: '50px',
					fontSize: '15px',
					'::placeholder': {
				  		color: '#CFD7E0',
					}
				}
			};

			stripe_card = elements.create( 'card', { style: style, hidePostalCode: true } );

			stripe_card.addEventListener( 'change', function( event ) {
				wc_stripe_elements_form.onCCFormChange();

				if ( event.error ) {
					$( document.body ).trigger( 'stripeError', event );
				}
			});

			$( document.body ).on( 'updated_checkout', function() {
				// Don't mount elements a second time.
				if ( stripe_card ) {
					stripe_card.unmount( '#stripe-card-element' );
				}

				stripe_card.mount( '#stripe-card-element' );
			});
		},

		isStripeChosen: function() {
			return $( '#payment_method_stripe' ).is( ':checked' ) && ( ! $( 'input[name="wc-stripe-payment-token"]:checked' ).length || 'new' === $( 'input[name="wc-stripe-payment-token"]:checked' ).val() );
		},

		hasToken: function() {
			return 0 < $( 'input.stripe_token' ).length;
		},

		block: function() {
			wc_stripe_elements_form.form.block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
		},

		unblock: function() {
			wc_stripe_elements_form.form.unblock();
		},

		onError: function( e, result ) {
			var message = result.error.message;

			// Customers do not need to know the specifics of the below type of errors
			// therefore return a generic localizable error message.
			if ( 
				'invalid_request_error' === result.error.type ||
				'api_connection_error'  === result.error.type ||
				'api_error'             === result.error.type ||
				'authentication_error'  === result.error.type ||
				'rate_limit_error'      === result.error.type
			) {
				message = wc_stripe_params.invalid_request_error;
			}

			if ( 'card_error' === result.error.type && wc_stripe_params.hasOwnProperty( result.error.code ) ) {
				message = wc_stripe_params[ result.error.code ];
			}

			$( '.wc-stripe-error, .stripe_token' ).remove();
			$( '#stripe-card-errors' ).html( '<ul class="woocommerce_error woocommerce-error wc-stripe-error"><li>' + message + '</li></ul>' );
			wc_stripe_elements_form.unblock();
		},

		onSubmit: function( e ) {
			if ( wc_stripe_elements_form.isStripeChosen() && ! wc_stripe_elements_form.hasToken() ) {
				e.preventDefault();
				wc_stripe_elements_form.block();

				var first_name = $( '#billing_first_name' ).length ? $( '#billing_first_name' ).val() : wc_stripe_params.billing_first_name,
					last_name  = $( '#billing_last_name' ).length ? $( '#billing_last_name' ).val() : wc_stripe_params.billing_last_name;

				if ( first_name && last_name ) {
					stripe_card.name = first_name + ' ' + last_name;
				}

				if ( $( '#billing_address_1' ).length > 0 ) {
					stripe_card.address_line1   = $( '#billing_address_1' ).val();
					stripe_card.address_line2   = $( '#billing_address_2' ).val();
					stripe_card.address_state   = $( '#billing_state' ).val();
					stripe_card.address_city    = $( '#billing_city' ).val();
					stripe_card.address_zip     = $( '#billing_postcode' ).val();
					stripe_card.address_country = $( '#billing_country' ).val();
				} else if ( wc_stripe_params.billing_address_1 ) {
					stripe_card.address_line1   = wc_stripe_params.billing_address_1;
					stripe_card.address_line2   = wc_stripe_params.billing_address_2;
					stripe_card.address_state   = wc_stripe_params.billing_state;
					stripe_card.address_city    = wc_stripe_params.billing_city;
					stripe_card.address_zip     = wc_stripe_params.billing_postcode;
					stripe_card.address_country = wc_stripe_params.billing_country;
				}

				stripe.createToken( stripe_card ).then( function( result ) {
					if ( result.error ) {
						$( document.body ).trigger( 'stripeError', result );
					} else if ( 'no' === wc_stripe_params.allow_prepaid_card && 'prepaid' === result.token.card.funding ) {
						result.error = { message: wc_stripe_params.no_prepaid_card_msg };

						$( document.body ).trigger( 'stripeError', result );	
					} else {
						wc_stripe_elements_form.onStripeResponse( result.token );
					}
				} );

				// Prevent form submitting
				return false;
			}
		},

		onCCFormChange: function() {
			$( '.wc-stripe-error, .stripe_token' ).remove();
		},

		onStripeResponse: function( token ) {
			wc_stripe_elements_form.clearToken();

			// insert the token into the form so it gets submitted to the server
			wc_stripe_elements_form.form.append( "<input type='hidden' class='stripe_token' name='stripe_token' value='" + token.id + "'/>" );
			wc_stripe_elements_form.form.submit();
		},

		clearToken: function() {
			$( '.stripe_token' ).remove();
		}
	};

	wc_stripe_elements_form.init();
} );
