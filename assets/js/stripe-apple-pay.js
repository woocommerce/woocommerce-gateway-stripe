/* global wc_stripe_apple_pay_params, Stripe */
Stripe.setPublishableKey( wc_stripe_apple_pay_params.key );

jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle Stripe payment forms.
	 */
	var wc_stripe_apple_pay = {
		/**
		 * Get WC AJAX endpoint URL.
		 *
		 * @param  {String} endpoint Endpoint.
		 * @return {String}
		 */
		getAjaxURL: function( endpoint ) {
			return wc_stripe_apple_pay_params.ajaxurl
				.toString()
				.replace( '%%endpoint%%', 'wc_stripe_' + endpoint );
		},

		/**
		 * Initialize event handlers and UI state.
		 */
		init: function() {
			Stripe.applePay.checkAvailability( function( available ) {
				if ( available ) {
					$( '.apple-pay-button' ).show();
					// This is so it is centered on the checkout page.
					$( '.woocommerce-checkout .apple-pay-button' ).css( 'visibility', 'visible' );
					$( '.apple-pay-button-checkout-separator' ).show();

					wc_stripe_apple_pay.generate_cart();
				}
			});

			$( document.body ).on( 'click', '.apple-pay-button', function( e ) {
				e.preventDefault();

				var paymentRequest = {
						countryCode: wc_stripe_apple_pay_params.country_code,
						currencyCode: wc_stripe_apple_pay_params.currency_code,
						total: {
							label: wc_stripe_apple_pay_params.label,
							amount: wc_stripe_apple_pay_params.total
						},
						lineItems: wc_stripe_apple_pay_params.line_items,
						requiredBillingContactFields: ['postalAddress'],
						requiredShippingContactFields: 'yes' === wc_stripe_apple_pay_params.needs_shipping ? ['postalAddress', 'phone', 'email', 'name'] : ['phone', 'email', 'name']
					};

				var applePaySession = Stripe.applePay.buildSession( paymentRequest, function( result, completion ) {
					var data = {
						'nonce': wc_stripe_apple_pay_params.stripe_apple_pay_nonce,
						'result': result
					};

					$.ajax({
						type:    'POST',
						data:    data,
						url:     wc_stripe_apple_pay.getAjaxURL( 'apple_pay' ),
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
				}, function( error ) {
					var data = {
						'nonce': wc_stripe_apple_pay_params.stripe_apple_pay_cart_nonce,
						'errors': error.message
					};

					$.ajax({
						type:    'POST',
						data:    data,
						url:     wc_stripe_apple_pay.getAjaxURL( 'log_apple_pay_errors' )
					});
				});

				// If shipping is needed -- get shipping methods.
				if ( 'yes' === wc_stripe_apple_pay_params.needs_shipping ) {
					// After the shipping contact/address has been selected
					applePaySession.onshippingcontactselected = function( shipping ) {
						var data = {
							'nonce': wc_stripe_apple_pay_params.stripe_apple_pay_get_shipping_methods_nonce,
							'address': shipping.shippingContact
						};

						$.ajax({
							type:    'POST',
							data:    data,
							url:     wc_stripe_apple_pay.getAjaxURL( 'apple_pay_get_shipping_methods' ),
							success: function( response ) {
								var total = { 
									'label': wc_stripe_apple_pay_params.label,
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
							'nonce': wc_stripe_apple_pay_params.stripe_apple_pay_update_shipping_method_nonce,
							'selected_shipping_method': event.shippingMethod
						};

						$.ajax({
							type:    'POST',
							data:    data,
							url:     wc_stripe_apple_pay.getAjaxURL( 'apple_pay_update_shipping_method' ),
							success: function( response ) {
								var newTotal = {
									'label': wc_stripe_apple_pay_params.label,
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

		generate_cart: function() {
			var data = {
					'nonce': wc_stripe_apple_pay_params.stripe_apple_pay_cart_nonce
				};

			$.ajax({
				type:    'POST',
				data:    data,
				url:     wc_stripe_apple_pay.getAjaxURL( 'generate_apple_pay_cart' ),
				success: function( response ) {
					wc_stripe_apple_pay_params.total      = response.total;
					wc_stripe_apple_pay_params.line_items = response.line_items;
				}
			});
		}
	};

	wc_stripe_apple_pay.init();

	// We need to refresh Apple Pay data when total is updated.
	$( document.body ).on( 'updated_cart_totals', function() {
		wc_stripe_apple_pay.init();
	});

	// We need to refresh Apple Pay data when total is updated.
	$( document.body ).on( 'updated_checkout', function() {
		wc_stripe_apple_pay.init();
	});
});
