/* global wc_stripe_apple_pay_single_params, Stripe */
Stripe.setPublishableKey( wc_stripe_apple_pay_single_params.key );

jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle Stripe payment forms.
	 */
	var wc_stripe_apple_pay_single = {
		/**
		 * Get WC AJAX endpoint URL.
		 *
		 * @param  {String} endpoint Endpoint.
		 * @return {String}
		 */
		getAjaxURL: function( endpoint ) {
			return wc_stripe_apple_pay_single_params.ajaxurl
				.toString()
				.replace( '%%endpoint%%', 'wc_stripe_' + endpoint );
		},

		/**
		 * Initialize event handlers and UI state.
		 */
		init: function() {
			Stripe.applePay.checkAvailability( function( available ) {
				if ( available ) {
					$( document.body ).on( 'woocommerce_variation_has_changed', function() {
						wc_stripe_apple_pay_single.generate_cart();
					})

					.on( 'change', '.quantity .qty', function() {
						wc_stripe_apple_pay_single.generate_cart();
					});

					wc_stripe_apple_pay_single.generate_cart();

					$( '.apple-pay-button' ).show();
				}
			});

			$( document.body ).on( 'click', '.apple-pay-button', function( e ) {
				e.preventDefault();

				var addToCartButton = $( '.single_add_to_cart_button' );

				// First check if product can be added to cart.
				if ( addToCartButton.is( '.disabled' ) ) {
					if ( addToCartButton.is( '.wc-variation-is-unavailable' ) ) {
						window.alert( wc_add_to_cart_variation_params.i18n_unavailable_text );
					} else if ( addToCartButton.is( '.wc-variation-selection-needed' ) ) {
						window.alert( wc_add_to_cart_variation_params.i18n_make_a_selection_text );
					}

					return;
				}

				var paymentRequest = {
						countryCode: wc_stripe_apple_pay_single_params.country_code,
						currencyCode: wc_stripe_apple_pay_single_params.currency_code,
						total: {
							label: wc_stripe_apple_pay_single_params.label,
							amount: wc_stripe_apple_pay_single_params.total
						},
						lineItems: wc_stripe_apple_pay_single_params.line_items,
						requiredBillingContactFields: ['postalAddress'],
						requiredShippingContactFields: 'yes' === wc_stripe_apple_pay_single_params.needs_shipping ? ['postalAddress', 'phone', 'email', 'name'] : ['phone', 'email', 'name']
					};

				var applePaySession = Stripe.applePay.buildSession( paymentRequest, function( result, completion ) {
					var data = {
						'nonce': wc_stripe_apple_pay_single_params.stripe_apple_pay_nonce,
						'result': result
					};

					$.ajax({
						type:    'POST',
						data:    data,
						url:     wc_stripe_apple_pay_single.getAjaxURL( 'apple_pay' ),
						success: function( response ) {
							if ( 'true' === response.success ) {
								completion( ApplePaySession.STATUS_SUCCESS );
								window.location.href = response.redirect;
							}

							if ( 'false' === response.success ) {
								completion( ApplePaySession.STATUS_FAILURE );

								$( '.apple-pay-button' ).before( '<p class="woocommerce-error wc-stripe-apple-pay-error">' + response.msg + '</p>' );

								// Scroll to error so user can see it.
								$( document.body ).animate({ scrollTop: $( '.wc-stripe-apple-pay-error' ).offset().top }, 500 );
							}
						}
					});
				});

				// If shipping is needed -- get shipping methods.
				if ( 'yes' === wc_stripe_apple_pay_single_params.needs_shipping ) {
					// After the shipping contact/address has been selected
					applePaySession.onshippingcontactselected = function( shipping ) {
						var data = {
							'nonce': wc_stripe_apple_pay_single_params.stripe_apple_pay_get_shipping_methods_nonce,
							'address': shipping.shippingContact
						};

						$.ajax({
							type:    'POST',
							data:    data,
							url:     wc_stripe_apple_pay_single.getAjaxURL( 'apple_pay_get_shipping_methods' ),
							success: function( response ) {
								var total = { 
									'label': wc_stripe_apple_pay_single_params.label,
									'amount': response.total
								};

								if ( 'true' === response.success ) {
									applePaySession.completeShippingContactSelection( ApplePaySession.STATUS_SUCCESS, response.shipping_methods, total, response.line_items );
								}

								if ( 'false' === response.success ) {
									applePaySession.completeShippingContactSelection( ApplePaySession.STATUS_INVALID_SHIPPING_POSTAL_ADDRESS, response.shipping_methods, total, response.line_items );
								}
							}
						});
					};

					// After the shipping method has been selected
					applePaySession.onshippingmethodselected = function( event ) {
						var data = {
							'nonce': wc_stripe_apple_pay_single_params.stripe_apple_pay_update_shipping_method_nonce,
							'selected_shipping_method': event.shippingMethod
						};

						$.ajax({
							type:    'POST',
							data:    data,
							url:     wc_stripe_apple_pay_single.getAjaxURL( 'apple_pay_update_shipping_method' ),
							success: function( response ) {
								var newTotal = {
									'label': wc_stripe_apple_pay_single_params.label,
									'amount': parseFloat( response.total ).toFixed(2)
								};

								if ( 'true' === response.success ) {
									applePaySession.completeShippingMethodSelection( ApplePaySession.STATUS_SUCCESS, newTotal, response.line_items );
								}

								if ( 'false' === response.success ) {
									applePaySession.completeShippingMethodSelection( ApplePaySession.STATUS_INVALID_SHIPPING_POSTAL_ADDRESS, newTotal, response.line_items );
								}
							}
						});
					};
				}

				applePaySession.begin();
			});
		},

		get_attributes: function() {
			var select = $( '.variations_form' ).find( '.variations select' ),
				data   = {},
				count  = 0,
				chosen = 0;

			select.each( function() {
				var attribute_name = $( this ).data( 'attribute_name' ) || $( this ).attr( 'name' );
				var value          = $( this ).val() || '';

				if ( value.length > 0 ) {
					chosen ++;
				}

				count ++;
				data[ attribute_name ] = value;
			});

			return {
				'count'      : count,
				'chosenCount': chosen,
				'data'       : data
			};			
		},

		generate_cart: function() {
			var data = {
					'nonce':      wc_stripe_apple_pay_single_params.stripe_apple_pay_cart_nonce,
					'qty':        $( '.quantity .qty' ).val(),
					'attributes': $( '.variations_form' ).length ? wc_stripe_apple_pay_single.get_attributes().data : []
				};

			return $.ajax({
				type:    'POST',
				data:    data,
				url:     wc_stripe_apple_pay_single.getAjaxURL( 'generate_apple_pay_single' ),
				success: function( response ) {
					wc_stripe_apple_pay_single_params.total      = response.total;
					wc_stripe_apple_pay_single_params.line_items = response.line_items;
				}
			});
		}
	};

	wc_stripe_apple_pay_single.init();
});
