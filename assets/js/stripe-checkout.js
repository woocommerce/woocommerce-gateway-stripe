jQuery( function( $ ) {
	'use strict';
	
	/**
	 * Object to handle Stripe payment forms.
	 */
	var wc_stripe_form = {
		/**
		 * Get WC AJAX endpoint URL.
		 *
		 * @param  {String} endpoint Endpoint.
		 * @return {String}
		 */
		getAjaxURL: function( endpoint ) {
			return wc_stripe_params.ajaxurl
				.toString()
				.replace( '%%endpoint%%', 'wc_stripe_' + endpoint );
		},

		/**
		 * Initialize e handlers and UI state.
		 */
		init: function( form ) {
			this.form          = form;
			this.stripe_submit = false;

			$( this.form )
				// We need to bind directly to the click (and not checkout_place_order_stripe) to avoid popup blockers
				// especially on mobile devices (like on Chrome for iOS) from blocking StripeCheckout.open from opening a tab
				.on( 'click', '#place_order', this.onSubmit )

				// WooCommerce lets us return a false on checkout_place_order_{gateway} to keep the form from submitting
				.on( 'submit checkout_place_order_stripe' );

			$( document.body ).on( 'checkout_error', this.resetModal );
		},

		isStripeChosen: function() {
			return $( '#payment_method_stripe' ).is( ':checked' ) && ( ! $( 'input[name="wc-stripe-payment-token"]:checked' ).length || 'new' === $( 'input[name="wc-stripe-payment-token"]:checked' ).val() );
		},

		isStripeModalNeeded: function( e ) {
			var token = wc_stripe_form.form.find( 'input.stripe_token' ),
				$required_inputs;

			// If this is a stripe submission (after modal) and token exists, allow submit.
			if ( wc_stripe_form.stripe_submit && token ) {
				return false;
			}

			// Don't affect submission if modal is not needed.
			if ( ! wc_stripe_form.isStripeChosen() ) {
				return false;
			}

			return true;
		},

		isMobile: function() {
			if( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ) {
				return true;
			}

			return false;
		},

		block: function() {
			if ( wc_stripe_form.isMobile() ) {
				$.blockUI({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});
			} else {
				wc_stripe_form.form.block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});
			}
		},

		unblock: function() {
			if ( wc_stripe_form.isMobile() ) {
				$.unblockUI();
			} else {
				wc_stripe_form.form.unblock();
			}
		},

		onClose: function() {
			wc_stripe_form.unblock();
		},

		onSubmit: function( e ) {
			if ( wc_stripe_form.isStripeModalNeeded() ) {
				e.preventDefault();

				// Since in mobile actions cannot be deferred, no dynamic validation applied.
				if ( wc_stripe_form.isMobile() ) {
					wc_stripe_form.openModal();
				} else {
					wc_stripe_form.validateCheckout();
				}

				return false;
			}

			return true;
		},

		openModal: function() {
			// Capture submittal and open stripecheckout
			var $form = wc_stripe_form.form,
				$data = $( '#stripe-payment-data' ),
				token = $form.find( 'input.stripe_token' );

			token.val( '' );

			var token_action = function( res ) {
				$form.find( 'input.stripe_token' ).remove();
				$form.append( '<input type="hidden" class="stripe_token" name="stripe_token" value="' + res.id + '"/>' );
				wc_stripe_form.stripe_submit = true;
				$form.submit();
			};

			StripeCheckout.open({
				key               : wc_stripe_params.key,
				billingAddress    : 'yes' === wc_stripe_params.stripe_checkout_require_billing_address,
				amount            : $data.data( 'amount' ),
				name              : $data.data( 'name' ),
				description       : $data.data( 'description' ),
				currency          : $data.data( 'currency' ),
				image             : $data.data( 'image' ),
				bitcoin           : $data.data( 'bitcoin' ),
				locale            : $data.data( 'locale' ),
				email             : $( '#billing_email' ).val() || $data.data( 'email' ),
				panelLabel        : $data.data( 'panel-label' ),
				allowRememberMe   : $data.data( 'allow-remember-me' ),
				token             : token_action,
				closed            : wc_stripe_form.onClose()
			});
		},

		resetModal: function() {
			wc_stripe_form.form.find( 'input.stripe_token' ).remove();
			wc_stripe_form.stripe_submit = false;
		},

		getRequiredFields: function() {
			return wc_stripe_form.form.find( '.form-row.validate-required > input, .form-row.validate-required > select' );
		},

		validateCheckout: function() {
			var data = {
				'nonce': wc_stripe_params.stripe_nonce,
				'required_fields': wc_stripe_form.getRequiredFields().serialize(),
				'all_fields': wc_stripe_form.form.serialize()
			};

			$.ajax({
				type:		'POST',
				url:		wc_stripe_form.getAjaxURL( 'validate_checkout' ),
				data:		data,
				dataType:   'json',
				success:	function( result ) {
					if ( 'success' === result ) {
						wc_stripe_form.openModal();
					} else if ( result.messages ) {
						wc_stripe_form.resetModal();
						wc_stripe_form.submitError( result.messages );
					}
				}
			});	
		},

		submitError: function( error_message ) {
			$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
			wc_stripe_form.form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' );
			//wc_stripe_form.form.removeClass( 'processing' ).unblock();
			wc_stripe_form.form.find( '.input-text, select, input:checkbox' ).blur();
			$( 'html, body' ).animate({
				scrollTop: ( $( 'form.checkout' ).offset().top - 100 )
			}, 1000 );
			$( document.body ).trigger( 'checkout_error' );
		}
	};

	wc_stripe_form.init( $( "form.checkout, form#order_review, form#add_payment_method" ) );
} );
