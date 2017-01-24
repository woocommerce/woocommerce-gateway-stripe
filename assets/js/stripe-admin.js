jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle Stripe admin functions.
	 */
	var wc_stripe_admin = {
		isTestMode: function() {
			return $( '#woocommerce_stripe_testmode' ).is( ':checked' );
		},

		getSecretKey: function() {
			if ( wc_stripe_admin.isTestMode() ) {
				return $( '#woocommerce_stripe_test_secret_key' ).val();
			} else {
				return $( '#woocommerce_stripe_secret_key' ).val();
			}
		},

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

			// Toggle Stripe Checkout settings.
			$( '#woocommerce_stripe_stripe_checkout' ).change( function() {
				if ( $( this ).is( ':checked' ) ) {
					$( '#woocommerce_stripe_stripe_checkout_locale, #woocommerce_stripe_stripe_bitcoin, #woocommerce_stripe_stripe_checkout_image, #woocommerce_stripe_allow_remember_me' ).closest( 'tr' ).show();
					$( '#woocommerce_stripe_request_payment_api' ).closest( 'tr' ).hide();
				} else {
					$( '#woocommerce_stripe_stripe_checkout_locale, #woocommerce_stripe_stripe_bitcoin, #woocommerce_stripe_stripe_checkout_image, #woocommerce_stripe_allow_remember_me' ).closest( 'tr' ).hide();
					$( '#woocommerce_stripe_request_payment_api' ).closest( 'tr' ).show();
				}
			}).change();

			// Toggle Apple Pay settings.
			$( '#woocommerce_stripe_apple_pay' ).change( function() {
				if ( $( this ).is( ':checked' ) ) {
					$( '#woocommerce_stripe_apple_pay_button, #woocommerce_stripe_apple_pay_button_lang, #wc-gateway-stripe-apple-pay-domain' ).closest( 'tr' ).show();
				} else {
					$( '#woocommerce_stripe_apple_pay_button, #woocommerce_stripe_apple_pay_button_lang, #wc-gateway-stripe-apple-pay-domain' ).closest( 'tr' ).hide();
				}
			}).change();

			// Validate the keys to make sure it is matching test with test field.
			$( '#woocommerce_stripe_secret_key, #woocommerce_stripe_publishable_key' ).on( 'input', function() {
				var value = $( this ).val();

				if ( value.indexOf( '_test_' ) >= 0 ) {
					$( this ).css( 'border-color', 'red' ).after( '<span class="description stripe-error-description" style="color:red; display:block;">' + wc_stripe_admin_params.localized_messages.not_valid_live_key_msg + '</span>' );
				} else {
					$( this ).css( 'border-color', '' );
					$( '.stripe-error-description', $( this ).parent() ).remove();
				}
			}).trigger( 'input' );

			// Validate the keys to make sure it is matching live with live field.
			$( '#woocommerce_stripe_test_secret_key, #woocommerce_stripe_test_publishable_key' ).on( 'input', function() {
				var value = $( this ).val();

				if ( value.indexOf( '_live_' ) >= 0 ) {
					$( this ).css( 'border-color', 'red' ).after( '<span class="description stripe-error-description" style="color:red; display:block;">' + wc_stripe_admin_params.localized_messages.not_valid_test_key_msg + '</span>' );
				} else {
					$( this ).css( 'border-color', '' );
					$( '.stripe-error-description', $( this ).parent() ).remove();
				}
			}).trigger( 'input' );

			// Domain verification is based on the secret key value in real time.
			$( '#wc-gateway-stripe-apple-pay-domain' ).click( function( e ) {
				e.preventDefault();

				// Remove any previous messages.
				$( '.wc-stripe-apple-pay-domain-message' ).remove();

				if ( ! wc_stripe_admin.getSecretKey() ) {
					$( '#wc-gateway-stripe-apple-pay-domain' ).after( '<p class="wc-stripe-apple-pay-domain-message" style="color:red;">' + wc_stripe_admin_params.localized_messages.missing_secret_key + '</p>' );

					return;
				}

				// Let the merchant know we're working on verifying the domain.
				$( '#wc-gateway-stripe-apple-pay-domain' ).html( wc_stripe_admin_params.localized_messages.verifying_button_text );

				var data = {
					'nonce': wc_stripe_admin_params.nonce.apple_pay_domain_nonce,
					'action': 'wc_stripe_apple_pay_domain',
					'secret_key': wc_stripe_admin.getSecretKey()
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
