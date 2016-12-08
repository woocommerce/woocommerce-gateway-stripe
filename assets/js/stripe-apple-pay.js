/* global wc_stripe_apple_pay_params, Stripe */
Stripe.setPublishableKey( wc_stripe_apple_pay_params.key );

jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle Stripe payment forms.
	 */
	var wc_stripe_apple_pay = {
		/**
		 * Initialize event handlers and UI state.
		 */
		init: function() {
			Stripe.applePay.checkAvailability( function( available ) {
				if ( available ) {
					$( '.apple-pay-button' ).show();

					wc_stripe_apple_pay.generate_cart();
				}
			});

			$( document.body ).on( 'click', '.apple-pay-button', function( e ) {
				e.preventDefault();

				// Clear any errors first.
				$( '.wc-stripe-apple-pay-error' ).remove();

				// If shipping is needed, we need to force customer to calculate shipping on cart page.
				if ( 'yes' === wc_stripe_apple_pay_params.is_cart_page && 
					 'yes' === wc_stripe_apple_pay_params.needs_shipping && 
					 ( $( '#shipping_method input[type="radio"]' ).length && ( ! $( '#shipping_method input[type="radio"]' ).is( ':checked' ) ) ||
					 0 === $( wc_stripe_apple_pay_params.chosen_shipping ).length )
				) {
					$( '.apple-pay-button' ).before( '<p class="woocommerce-error wc-stripe-apple-pay-error">' + wc_stripe_apple_pay_params.needs_shipping_msg + '</p>' );

					// Scroll to error so user can see it.
					$( document.body ).animate({ scrollTop: $( '.wc-stripe-apple-pay-error' ).offset().top }, 500 );
					return;
				}

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
						'action': 'wc_stripe_apple_pay',
						'nonce': wc_stripe_apple_pay_params.stripe_apple_pay_nonce,
						'result': result
					};

					$.post( wc_stripe_apple_pay_params.ajaxurl, data ).done( function( response ) {
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

					}).fail( function( response ) {
						completion( ApplePaySession.STATUS_FAILURE );
					});

					}, function(error) {
						console.log(error.message);
					});

				applePaySession.begin();
			});
		},

		generate_cart: function() {
			var data = {
				'action': 'wc_stripe_generate_apple_pay_cart',
				'nonce': wc_stripe_apple_pay_params.stripe_apple_pay_cart_nonce
			};

			$.post( wc_stripe_apple_pay_params.ajaxurl, data, function( response ) {
				wc_stripe_apple_pay_params.total = response.total;
				wc_stripe_apple_pay_params.line_items = response.line_items;
				wc_stripe_apple_pay_params.chosen_shipping = response.chosen_shipping;
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
