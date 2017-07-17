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
					this.clearSource
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

			/**
			 * Only in checkout page we need to delay the mounting of the
			 * card as some AJAX process needs to happen before we do.
			 */
			if ( wc_stripe_params.is_checkout ) {
				$( document.body ).on( 'updated_checkout', function() {
					// Don't mount elements a second time.
					if ( stripe_card ) {
						stripe_card.unmount( '#stripe-card-element' );
					}

					stripe_card.mount( '#stripe-card-element' );
				});
			} else {
				stripe_card.mount( '#stripe-card-element' );
			}
		},

		isStripeChosen: function() {
			return $( '#payment_method_stripe' ).is( ':checked' ) && ( ! $( 'input[name="wc-stripe-payment-token"]:checked' ).length || 'new' === $( 'input[name="wc-stripe-payment-token"]:checked' ).val() );
		},

		isStripeCardChosen: function() {
			return $( '#payment_method_stripe' ).is( ':checked' );
		},

		hasSource: function() {
			return 0 < $( 'input.stripe-source' ).length;
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

			$( '.wc-stripe-error, .stripe-source' ).remove();
			$( '#stripe-card-errors' ).html( '<ul class="woocommerce_error woocommerce-error wc-stripe-error"><li>' + message + '</li></ul>' );
			wc_stripe_elements_form.unblock();
		},

		onSubmit: function( e ) {
			if ( wc_stripe_elements_form.isStripeChosen() && ! wc_stripe_elements_form.hasSource() ) {
				e.preventDefault();
				wc_stripe_elements_form.block();

				var first_name = $( '#billing_first_name' ).length ? $( '#billing_first_name' ).val() : wc_stripe_params.billing_first_name,
					last_name  = $( '#billing_last_name' ).length ? $( '#billing_last_name' ).val() : wc_stripe_params.billing_last_name,
					extra_details = { owner: { name: '', address: {}, email: '', phone: '' } };

				extra_details.owner.name = first_name;

				if ( first_name && last_name ) {
					extra_details.owner.name = first_name + ' ' + last_name;
				}

				extra_details.owner.email = $( '#billing_email' ).val();
				extra_details.owner.phone = $( '#billing_phone' ).val();

				if ( $( '#billing_address_1' ).length > 0 ) {
					extra_details.owner.address.line1       = $( '#billing_address_1' ).val();
					extra_details.owner.address.line2       = $( '#billing_address_2' ).val();
					extra_details.owner.address.state       = $( '#billing_state' ).val();
					extra_details.owner.address.city        = $( '#billing_city' ).val();
					extra_details.owner.address.postal_code = $( '#billing_postcode' ).val();
					extra_details.owner.address.country     = $( '#billing_country' ).val();
				} else if ( wc_stripe_params.billing_address_1 ) {
					extra_details.owner.address.line1       = wc_stripe_params.billing_address_1;
					extra_details.owner.address.line2       = wc_stripe_params.billing_address_2;
					extra_details.owner.address.state       = wc_stripe_params.billing_state;
					extra_details.owner.address.city        = wc_stripe_params.billing_city;
					extra_details.owner.address.postal_code = wc_stripe_params.billing_postcode;
					extra_details.owner.address.country     = wc_stripe_params.billing_country;
				}

				if ( 0 < $( '#stripe-payment-data' ).data( 'amount' ) ) {
					extra_details.amount = $( '#stripe-payment-data' ).data( 'amount' );
				}

				if ( $( '#stripe-payment-data' ).data( 'currency' ).length ) {
					extra_details.currency = $( '#stripe-payment-data' ).data( 'currency' );
				}

				if ( wc_stripe_elements_form.isStripeCardChosen ) {
					extra_details.type = 'card';
				}

				stripe.createSource( stripe_card, extra_details ).then( function( result ) {
					if ( result.error ) {
						$( document.body ).trigger( 'stripeError', result );
					} else if ( 'no' === wc_stripe_params.allow_prepaid_card && 'prepaid' === result.source.card.funding ) {
						result.error = { message: wc_stripe_params.no_prepaid_card_msg };

						$( document.body ).trigger( 'stripeError', result );	
					} else {
						wc_stripe_elements_form.onStripeResponse( result.source );
					}
				} );

				// Prevent form submitting
				return false;
			}
		},

		isThreeDSecure: function( sourceObject ) {

		},

		onCCFormChange: function() {
			$( '.wc-stripe-error, .stripe-source' ).remove();
		},

		onStripeResponse: function( source ) {
			wc_stripe_elements_form.clearSource();

			// insert the Source into the form so it gets submitted to the server
			wc_stripe_elements_form.form.append( "<input type='hidden' class='stripe-source' name='stripe_source' value='" + source.id + "'/>" );
			wc_stripe_elements_form.form.submit();
		},

		clearSource: function() {
			$( '.stripe-source' ).remove();
		}
	};

	wc_stripe_elements_form.init();
} );
