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
					$( '#woocommerce_stripe_stripe_checkout_locale, #woocommerce_stripe_stripe_bitcoin, #woocommerce_stripe_stripe_checkout_image' ).closest( 'tr' ).show();
					$( '#woocommerce_stripe_request_payment_api' ).closest( 'tr' ).hide();
				} else {
					$( '#woocommerce_stripe_stripe_checkout_locale, #woocommerce_stripe_stripe_bitcoin, #woocommerce_stripe_stripe_checkout_image' ).closest( 'tr' ).hide();
					$( '#woocommerce_stripe_request_payment_api' ).closest( 'tr' ).show();
				}
			}).change();

			// Toggle Apple Pay settings.
			$( '#woocommerce_stripe_apple_pay' ).change( function() {
				if ( $( this ).is( ':checked' ) ) {
					$( '#woocommerce_stripe_apple_pay_button, #woocommerce_stripe_apple_pay_button_lang' ).closest( 'tr' ).show();
				} else {
					$( '#woocommerce_stripe_apple_pay_button, #woocommerce_stripe_apple_pay_button_lang' ).closest( 'tr' ).hide();
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
		}
	};

	wc_stripe_admin.init();
});
