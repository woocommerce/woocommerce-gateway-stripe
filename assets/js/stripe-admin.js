+/* global wc_stripe_settings_params */

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
					test_webhook_secret = $( '#woocommerce_stripe_test_webhook_secret' ).parents( 'tr' ).eq( 0 ),
					live_secret_key = $( '#woocommerce_stripe_secret_key' ).parents( 'tr' ).eq( 0 ),
					live_publishable_key = $( '#woocommerce_stripe_publishable_key' ).parents( 'tr' ).eq( 0 ),
					live_webhook_secret = $( '#woocommerce_stripe_webhook_secret' ).parents( 'tr' ).eq( 0 );

				if ( $( this ).is( ':checked' ) ) {
					test_secret_key.show();
					test_publishable_key.show();
					test_webhook_secret.show();
					live_secret_key.hide();
					live_publishable_key.hide();
					live_webhook_secret.hide();
				} else {
					test_secret_key.hide();
					test_publishable_key.hide();
					test_webhook_secret.hide();
					live_secret_key.show();
					live_publishable_key.show();
					live_webhook_secret.show();
				}
			} );

			$( '#woocommerce_stripe_testmode' ).trigger( 'change' );

			// Toggle Payment Request buttons settings.
			$( '#woocommerce_stripe_payment_request' ).on( 'change', function() {
				if ( $( this ).is( ':checked' ) ) {
					$( '#woocommerce_stripe_payment_request_button_theme, #woocommerce_stripe_payment_request_button_type, #woocommerce_stripe_payment_request_button_height' ).closest( 'tr' ).show();
				} else {
					$( '#woocommerce_stripe_payment_request_button_theme, #woocommerce_stripe_payment_request_button_type, #woocommerce_stripe_payment_request_button_height' ).closest( 'tr' ).hide();
				}
			} ).trigger( 'change' );

			// Toggle Custom Payment Request configs.
			$( '#woocommerce_stripe_payment_request_button_type' ).on( 'change', function() {
				if ( 'custom' === $( this ).val() ) {
					$( '#woocommerce_stripe_payment_request_button_label' ).closest( 'tr' ).show();
				} else {
					$( '#woocommerce_stripe_payment_request_button_label' ).closest( 'tr' ).hide();
				}
			} ).trigger( 'change' )

			// Toggle Branded Payment Request configs.
			$( '#woocommerce_stripe_payment_request_button_type' ).on( 'change', function() {
				if ( 'branded' === $( this ).val() ) {
					$( '#woocommerce_stripe_payment_request_button_branded_type' ).closest( 'tr' ).show();
				} else {
					$( '#woocommerce_stripe_payment_request_button_branded_type' ).closest( 'tr' ).hide();
				}
			} ).trigger( 'change' )

			// Make the 3DS notice dismissable.
			$( '.wc-stripe-3ds-missing' ).each( function() {
				var $setting = $( this );

				$setting.find( '.notice-dismiss' ).on( 'click.wc-stripe-dismiss-notice', function() {
					$.ajax( {
						type: 'head',
						url: window.location.href + '&stripe_dismiss_3ds=' + $setting.data( 'nonce' ),
					} );
				} );
			} );

			// Add secret visibility toggles.
			$( '#woocommerce_stripe_test_secret_key, #woocommerce_stripe_secret_key, #woocommerce_stripe_test_webhook_secret, #woocommerce_stripe_webhook_secret' ).after(
				'<button class="wc-stripe-toggle-secret" style="height: 30px; margin-left: 2px; cursor: pointer"><span class="dashicons dashicons-visibility"></span></button>'
			);
			$( '.wc-stripe-toggle-secret' ).on( 'click', function( event ) {
				event.preventDefault();

				var $dashicon = $( this ).closest( 'button' ).find( '.dashicons' );
				var $input = $( this ).closest( 'tr' ).find( '.input-text' );
				var inputType = $input.attr( 'type' );

				if ( 'text' == inputType ) {
					$input.attr( 'type', 'password' );
					$dashicon.removeClass( 'dashicons-hidden' );
					$dashicon.addClass( 'dashicons-visibility' );
				} else {
					$input.attr( 'type', 'text' );
					$dashicon.removeClass( 'dashicons-visibility' );
					$dashicon.addClass( 'dashicons-hidden' );
				}
			} );

			$( 'form' ).find( 'input, select' ).on( 'change input', function disableConnect() {

				$( '#wc_stripe_connect_button' ).addClass( 'disabled' );

				$( '#wc_stripe_connect_button' ).on( 'click', function() { return false; } );

				$( '#woocommerce_stripe_api_credentials' )
					.next( 'p' )
					.append( ' (Please save changes before selecting this button.)' );

				$( 'form' ).find( 'input, select' ).off( 'change input', disableConnect );
			} );

			// Webhook verification checks for timestamp within 5 minutes so warn if
			// server time is off from browser time by > 4 minutes.
			var timeDifference = Date.now() / 1000 - wc_stripe_settings_params.time;
			var isTimeOutOfSync = Math.abs( timeDifference ) > 4 * 60;
			$( '#woocommerce_stripe_test_webhook_secret, #woocommerce_stripe_webhook_secret' )
				.on( 'change input', function() {
					var $td = $( this ).closest( 'td' );
					var $warning = $td.find( '.webhook_secret_time_sync_warning' );
					var hasWebhookSecretValue = $( this ).val().length > 0;

					if ( hasWebhookSecretValue ){
						var isWarningShown = $warning.length > 0;
						if ( isTimeOutOfSync && ! isWarningShown ) {
							$td.append( '<p class="webhook_secret_time_sync_warning">' + wc_stripe_settings_params.i18n_out_of_sync + '</p>' );
						}
					} else {
						$warning.remove();
					}
				} )
				.change();
		}
	};

	wc_stripe_admin.init();
} );
