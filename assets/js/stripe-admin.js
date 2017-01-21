jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle Stripe admin functions.
	 */
	var wc_stripe_admin = {
		/**
		 * Initialize.
		 */
		init: function() {
			$( document.body ).on( 'change', '#woocommerce_stripe_testmode', function() {
				var test_secret_key = $( '#woocommerce_stripe_test_secret_key' ).parents( 'tr' ).eq( 0 ),
					test_publishable_key = $( '#woocommerce_stripe_test_publishable_key' ).parents( 'tr' ).eq( 0 ),
					live_secret_key = $( '#woocommerce_stripe_secret_key' ).parents( 'tr' ).eq( 0 ),
					live_publishable_key = $( '#woocommerce_stripe_publishable_key' ).parents( 'tr' ).eq( 0 );

				if ( $( this ).is( ':checked' ) ) {
					test_secret_key.show();
					test_publishable_key.show();
					live_secret_key.hide();
					live_publishable_key.hide();
				} else {
					test_secret_key.hide();
					test_publishable_key.hide();
					live_secret_key.show();
					live_publishable_key.show();
				}
			} );

			$( '#woocommerce_stripe_testmode' ).change();

			$( '#woocommerce_stripe_stripe_checkout' ).change( function() {
				if ( $( this ).is( ':checked' ) ) {
					$( '#woocommerce_stripe_stripe_checkout_locale, #woocommerce_stripe_stripe_bitcoin, #woocommerce_stripe_stripe_checkout_image, #woocommerce_stripe_allow_remember_me' ).closest( 'tr' ).show();
					$( '#woocommerce_stripe_request_payment_api' ).closest( 'tr' ).hide();
				} else {
					$( '#woocommerce_stripe_stripe_checkout_locale, #woocommerce_stripe_stripe_bitcoin, #woocommerce_stripe_stripe_checkout_image, #woocommerce_stripe_allow_remember_me' ).closest( 'tr' ).hide();
					$( '#woocommerce_stripe_request_payment_api' ).closest( 'tr' ).show();
				}
			}).change();

			$( '#woocommerce_stripe_apple_pay' ).change( function() {
				if ( $( this ).is( ':checked' ) ) {
					$( '#woocommerce_stripe_apple_pay_button, #woocommerce_stripe_apple_pay_button_lang, #wc-gateway-stripe-apple-pay-domain' ).closest( 'tr' ).show();
				} else {
					$( '#woocommerce_stripe_apple_pay_button, #woocommerce_stripe_apple_pay_button_lang, #wc-gateway-stripe-apple-pay-domain' ).closest( 'tr' ).hide();
				}
			}).change();

			$( '#woocommerce_stripe_secret_key, #woocommerce_stripe_publishable_key' ).change( function() {
				var value = $( this ).val();

				if ( value.indexOf( '_test_' ) >= 0 ) {
					$( this ).css( 'border-color', 'red' ).after( '<span class="description stripe-error-description" style="color:red; display:block;">' + wc_stripe_admin_params.localized_messages.not_valid_live_key_msg + '</span>' );
				} else {
					$( this ).css( 'border-color', '' );
					$( '.stripe-error-description', $( this ).parent() ).remove();
				}
			}).change();

			$( '#woocommerce_stripe_test_secret_key, #woocommerce_stripe_test_publishable_key' ).change( function() {
				var value = $( this ).val();

				if ( value.indexOf( '_live_' ) >= 0 ) {
					$( this ).css( 'border-color', 'red' ).after( '<span class="description stripe-error-description" style="color:red; display:block;">' + wc_stripe_admin_params.localized_messages.not_valid_test_key_msg + '</span>' );
				} else {
					$( this ).css( 'border-color', '' );
					$( '.stripe-error-description', $( this ).parent() ).remove();
				}
			}).change();

			$( '#wc-gateway-stripe-apple-pay-domain' ).click( function( e ) {
				e.preventDefault();

				// Remove any previous messages.
				$( '.wc-stripe-apple-pay-domain-message' ).remove();

				var data = {
					'nonce': wc_stripe_admin_params.nonce.apple_pay_domain_nonce,
					'action': 'wc_stripe_apple_pay_domain'
				};

				$.ajax({
					type:    'POST',
					data:    data,
					url:     wc_stripe_admin_params.ajaxurl,
					success: function( response ) {
						if ( true === response.success ) {
							$( '#wc-gateway-stripe-apple-pay-domain' ).html( wc_stripe_admin_params.localized_messages.re_verify_button_text ).after( '<p class="wc-stripe-apple-pay-domain-message" style="color:green;">' + response.message + '</p>' );

							$( '.wc-gateway-stripe-apple-pay-domain-set' ).val( 1 );

						}

						if ( false === response.success ) {
							$( '#wc-gateway-stripe-apple-pay-domain' ).after( '<p class="wc-stripe-apple-pay-domain-message" style="color:red;">' + response.message + '</p>' );

							$( '.wc-gateway-stripe-apple-pay-domain-set' ).val( 0 );
						}
					}
				});
			});
		}
	};

	wc_stripe_admin.init();
});
