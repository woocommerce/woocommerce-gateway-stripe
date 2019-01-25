/* global wc_stripe_params */

jQuery( function( $ ) {
	'use strict';

	try {
		var stripe = Stripe( wc_stripe_params.key );
	} catch( error ) {
		console.log( error );
		return;
	}

	var stripe_elements_options = Object.keys( wc_stripe_params.elements_options ).length ? wc_stripe_params.elements_options : {},
		sepa_elements_options   = Object.keys( wc_stripe_params.sepa_elements_options ).length ? wc_stripe_params.sepa_elements_options : {},
		elements                = stripe.elements( stripe_elements_options ),
		iban                    = elements.create( 'iban', sepa_elements_options ),
		stripe_card,
		stripe_exp,
		stripe_cvc;

	/**
	 * Object to handle Stripe elements payment form.
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

		unmountElements: function() {
			if ( 'yes' === wc_stripe_params.inline_cc_form ) {
				stripe_card.unmount( '#stripe-card-element' );
			} else {
				stripe_card.unmount( '#stripe-card-element' );
				stripe_exp.unmount( '#stripe-exp-element' );
				stripe_cvc.unmount( '#stripe-cvc-element' );
			}
		},

		mountElements: function() {
			if ( ! $( '#stripe-card-element' ).length ) {
				return;
			}

			if ( 'yes' === wc_stripe_params.inline_cc_form ) {
				stripe_card.mount( '#stripe-card-element' );
			} else {
				stripe_card.mount( '#stripe-card-element' );
				stripe_exp.mount( '#stripe-exp-element' );
				stripe_cvc.mount( '#stripe-cvc-element' );
			}
		},

		createElements: function() {
			var elementStyles = {
				base: {
					iconColor: '#666EE8',
					color: '#31325F',
					fontSize: '15px',
					'::placeholder': {
				  		color: '#CFD7E0',
					}
				}
			};

			var elementClasses = {
				focus: 'focused',
				empty: 'empty',
				invalid: 'invalid',
			};

			elementStyles  = wc_stripe_params.elements_styling ? wc_stripe_params.elements_styling : elementStyles;
			elementClasses = wc_stripe_params.elements_classes ? wc_stripe_params.elements_classes : elementClasses;

			if ( 'yes' === wc_stripe_params.inline_cc_form ) {
				stripe_card = elements.create( 'card', { style: elementStyles, hidePostalCode: true } );

				stripe_card.addEventListener( 'change', function( event ) {
					wc_stripe_form.onCCFormChange();

					if ( event.error ) {
						$( document.body ).trigger( 'stripeError', event );
					}
				} );
			} else {
				stripe_card = elements.create( 'cardNumber', { style: elementStyles, classes: elementClasses } );
				stripe_exp  = elements.create( 'cardExpiry', { style: elementStyles, classes: elementClasses } );
				stripe_cvc  = elements.create( 'cardCvc', { style: elementStyles, classes: elementClasses } );

				stripe_card.addEventListener( 'change', function( event ) {
					wc_stripe_form.onCCFormChange();

					wc_stripe_form.updateCardBrand( event.brand );

					if ( event.error ) {
						$( document.body ).trigger( 'stripeError', event );
					}
				} );

				stripe_exp.addEventListener( 'change', function( event ) {
					wc_stripe_form.onCCFormChange();

					if ( event.error ) {
						$( document.body ).trigger( 'stripeError', event );
					}
				} );

				stripe_cvc.addEventListener( 'change', function( event ) {
					wc_stripe_form.onCCFormChange();

					if ( event.error ) {
						$( document.body ).trigger( 'stripeError', event );
					}
				} );
			}

			/**
			 * Only in checkout page we need to delay the mounting of the
			 * card as some AJAX process needs to happen before we do.
			 */
			if ( 'yes' === wc_stripe_params.is_checkout ) {
				$( document.body ).on( 'updated_checkout', function() {
					// Don't mount elements a second time.
					if ( stripe_card ) {
						wc_stripe_form.unmountElements();
					}

					wc_stripe_form.mountElements();

					if ( $( '#stripe-iban-element' ).length ) {
						iban.mount( '#stripe-iban-element' );
					}
				} );
			} else if ( $( 'form#add_payment_method' ).length || $( 'form#order_review' ).length ) {
				wc_stripe_form.mountElements();

				if ( $( '#stripe-iban-element' ).length ) {
					iban.mount( '#stripe-iban-element' );
				}
			}
		},

		updateCardBrand: function( brand ) {
			var brandClass = {
				'visa': 'stripe-visa-brand',
				'mastercard': 'stripe-mastercard-brand',
				'amex': 'stripe-amex-brand',
				'discover': 'stripe-discover-brand',
				'diners': 'stripe-diners-brand',
				'jcb': 'stripe-jcb-brand',
				'unknown': 'stripe-credit-card-brand'
			};

			var imageElement = $( '.stripe-card-brand' ),
				imageClass = 'stripe-credit-card-brand';

			if ( brand in brandClass ) {
				imageClass = brandClass[ brand ];
			}

			// Remove existing card brand class.
			$.each( brandClass, function( index, el ) {
				imageElement.removeClass( el );
			} );

			imageElement.addClass( imageClass );
		},

		/**
		 * Initialize event handlers and UI state.
		 */
		init: function() {
			// Initialize tokenization script if on change payment method page and pay for order page.
			if ( 'yes' === wc_stripe_params.is_change_payment_page || 'yes' === wc_stripe_params.is_pay_for_order_page ) {
				$( document.body ).trigger( 'wc-credit-card-form-init' );
			}

			// Stripe Checkout.
			this.stripe_checkout_submit = false;

			// checkout page
			if ( $( 'form.woocommerce-checkout' ).length ) {
				this.form = $( 'form.woocommerce-checkout' );
			}

			$( 'form.woocommerce-checkout' )
				.on(
					'checkout_place_order_stripe checkout_place_order_stripe_bancontact checkout_place_order_stripe_sofort checkout_place_order_stripe_giropay checkout_place_order_stripe_ideal checkout_place_order_stripe_alipay checkout_place_order_stripe_sepa',
					this.onSubmit
				);

			// pay order page
			if ( $( 'form#order_review' ).length ) {
				this.form = $( 'form#order_review' );
			}

			$( 'form#order_review, form#add_payment_method' )
				.on(
					'submit',
					this.onSubmit
				);

			// add payment method page
			if ( $( 'form#add_payment_method' ).length ) {
				this.form = $( 'form#add_payment_method' );
			}

			$( 'form.woocommerce-checkout' )
				.on(
					'change',
					this.reset
				);

			$( document )
				.on(
					'stripeError',
					this.onError
				)
				.on(
					'checkout_error',
					this.reset
				);

			// SEPA IBAN.
			iban.on( 'change',
				this.onSepaError
			);

			wc_stripe_form.createElements();

			if ( 'yes' === wc_stripe_params.is_stripe_checkout ) {
				$( document.body ).on( 'click', '.wc-stripe-checkout-button', function() {
					wc_stripe_form.openModal();
					return false;
				} );
			}
		},

		// Check to see if Stripe in general is being used for checkout.
		isStripeChosen: function() {
			return $( '#payment_method_stripe, #payment_method_stripe_bancontact, #payment_method_stripe_sofort, #payment_method_stripe_giropay, #payment_method_stripe_ideal, #payment_method_stripe_alipay, #payment_method_stripe_sepa, #payment_method_stripe_eps, #payment_method_stripe_multibanco' ).is( ':checked' ) || ( $( '#payment_method_stripe' ).is( ':checked' ) && 'new' === $( 'input[name="wc-stripe-payment-token"]:checked' ).val() ) || ( $( '#payment_method_stripe_sepa' ).is( ':checked' ) && 'new' === $( 'input[name="wc-stripe-payment-token"]:checked' ).val() );
		},

		// Currently only support saved cards via credit cards and SEPA. No other payment method.
		isStripeSaveCardChosen: function() {
			return ( $( '#payment_method_stripe' ).is( ':checked' ) && ( $( 'input[name="wc-stripe-payment-token"]' ).is( ':checked' ) && 'new' !== $( 'input[name="wc-stripe-payment-token"]:checked' ).val() ) ) ||
				( $( '#payment_method_stripe_sepa' ).is( ':checked' ) && ( $( 'input[name="wc-stripe_sepa-payment-token"]' ).is( ':checked' ) && 'new' !== $( 'input[name="wc-stripe_sepa-payment-token"]:checked' ).val() ) );
		},

		// Stripe credit card used.
		isStripeCardChosen: function() {
			return $( '#payment_method_stripe' ).is( ':checked' );
		},

		isBancontactChosen: function() {
			return $( '#payment_method_stripe_bancontact' ).is( ':checked' );
		},

		isGiropayChosen: function() {
			return $( '#payment_method_stripe_giropay' ).is( ':checked' );
		},

		isIdealChosen: function() {
			return $( '#payment_method_stripe_ideal' ).is( ':checked' );
		},

		isSofortChosen: function() {
			return $( '#payment_method_stripe_sofort' ).is( ':checked' );
		},

		isAlipayChosen: function() {
			return $( '#payment_method_stripe_alipay' ).is( ':checked' );
		},

		isSepaChosen: function() {
			return $( '#payment_method_stripe_sepa' ).is( ':checked' );
		},

		isP24Chosen: function() {
			return $( '#payment_method_stripe_p24' ).is( ':checked' );
		},

		isEpsChosen: function() {
			return $( '#payment_method_stripe_eps' ).is( ':checked' );
		},

		isMultibancoChosen: function() {
			return $( '#payment_method_stripe_multibanco' ).is( ':checked' );
		},

		hasSource: function() {
			return 0 < $( 'input.stripe-source' ).length;
		},

		// Legacy
		hasToken: function() {
			return 0 < $( 'input.stripe_token' ).length;
		},

		isMobile: function() {
			if( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ) {
				return true;
			}

			return false;
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

		block: function() {
			if ( ! wc_stripe_form.isMobile() ) {
				wc_stripe_form.form.block( {
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				} );
			}
		},

		unblock: function() {
			wc_stripe_form.form.unblock();
		},

		getSelectedPaymentElement: function() {
			return $( '.payment_methods input[name="payment_method"]:checked' );
		},

		// Stripe Checkout.
		openModal: function() {
			// Capture submittal and open stripecheckout
			var $form = wc_stripe_form.form,
				$data = $( '#stripe-payment-data' );

			wc_stripe_form.reset();

			var token_action = function( res ) {
				$form.find( 'input.stripe_source' ).remove();

				/* Since source was introduced in 4.0. We need to
				 * convert the token into a source.
				 */
				if ( 'token' === res.object ) {
					stripe.createSource( {
						type: 'card',
						token: res.id,
					} ).then( wc_stripe_form.sourceResponse );
				} else if ( 'source' === res.object ) {
					var response = { source: res };
					wc_stripe_form.sourceResponse( response );
				}
			};

			StripeCheckout.open( {
				key               : wc_stripe_params.key,
				billingAddress    : $data.data( 'billing-address' ),
				zipCode           : $data.data( 'verify-zip' ),
				amount            : $data.data( 'amount' ),
				name              : $data.data( 'name' ),
				description       : $data.data( 'description' ),
				currency          : $data.data( 'currency' ),
				image             : $data.data( 'image' ),
				locale            : $data.data( 'locale' ),
				email             : $( '#billing_email' ).val() || $data.data( 'email' ),
				panelLabel        : $data.data( 'panel-label' ),
				allowRememberMe   : $data.data( 'allow-remember-me' ),
				token             : token_action,
				closed            : wc_stripe_form.onClose()
			} );
		},

		// Stripe Checkout.
		resetModal: function() {
			wc_stripe_form.reset();
			wc_stripe_form.stripe_checkout_submit = false;
		},

		// Stripe Checkout.
		onClose: function() {
			wc_stripe_form.unblock();
		},

		getOwnerDetails: function() {
			var first_name = $( '#billing_first_name' ).length ? $( '#billing_first_name' ).val() : wc_stripe_params.billing_first_name,
				last_name  = $( '#billing_last_name' ).length ? $( '#billing_last_name' ).val() : wc_stripe_params.billing_last_name,
				extra_details = { owner: { name: '', address: {}, email: '', phone: '' } };

			extra_details.owner.name = first_name;

			if ( first_name && last_name ) {
				extra_details.owner.name = first_name + ' ' + last_name;
			} else {
				extra_details.owner.name = $( '#stripe-payment-data' ).data( 'full-name' );
			}

			extra_details.owner.email = $( '#billing_email' ).val();
			extra_details.owner.phone = $( '#billing_phone' ).val();

			/* Stripe does not like empty string values so
			 * we need to remove the parameter if we're not
			 * passing any value.
			 */
			if ( typeof extra_details.owner.phone === 'undefined' || 0 >= extra_details.owner.phone.length ) {
				delete extra_details.owner.phone;
			}

			if ( typeof extra_details.owner.email === 'undefined' || 0 >= extra_details.owner.email.length ) {
				if ( $( '#stripe-payment-data' ).data( 'email' ).length ) {
					extra_details.owner.email = $( '#stripe-payment-data' ).data( 'email' );
				} else {
					delete extra_details.owner.email;
				}
			}

			if ( typeof extra_details.owner.name === 'undefined' || 0 >= extra_details.owner.name.length ) {
				delete extra_details.owner.name;
			}

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

			return extra_details;
		},

		createSource: function() {
			var extra_details = wc_stripe_form.getOwnerDetails(),
				source_type   = 'card';

			if ( wc_stripe_form.isBancontactChosen() ) {
				source_type = 'bancontact';
			}

			if ( wc_stripe_form.isSepaChosen() ) {
				source_type = 'sepa_debit';
			}

			if ( wc_stripe_form.isIdealChosen() ) {
				source_type = 'ideal';
			}

			if ( wc_stripe_form.isSofortChosen() ) {
				source_type = 'sofort';
			}

			if ( wc_stripe_form.isGiropayChosen() ) {
				source_type = 'giropay';
			}

			if ( wc_stripe_form.isAlipayChosen() ) {
				source_type = 'alipay';
			}

			if ( 'card' === source_type ) {
				stripe.createSource( stripe_card, extra_details ).then( wc_stripe_form.sourceResponse );
			} else {
				switch ( source_type ) {
					case 'bancontact':
					case 'giropay':
					case 'ideal':
					case 'sofort':
					case 'alipay':
						// These redirect flow payment methods need this information to be set at source creation.
						extra_details.amount   = $( '#stripe-' + source_type + '-payment-data' ).data( 'amount' );
						extra_details.currency = $( '#stripe-' + source_type + '-payment-data' ).data( 'currency' );
						extra_details.redirect = { return_url: wc_stripe_params.return_url };

						if ( wc_stripe_params.statement_descriptor ) {
							extra_details.statement_descriptor = wc_stripe_params.statement_descriptor;
						}

						break;
				}

				// Handle special inputs that are unique to a payment method.
				switch ( source_type ) {
					case 'sepa_debit':
						extra_details.currency = $( '#stripe-' + source_type + '-payment-data' ).data( 'currency' );
						extra_details.mandate  = { notification_method: wc_stripe_params.sepa_mandate_notification };
						break;
					case 'ideal':
						extra_details.ideal = { bank: $( '#stripe-ideal-bank' ).val() };
						break;
					case 'alipay':
						extra_details.currency = $( '#stripe-' + source_type + '-payment-data' ).data( 'currency' );
						extra_details.amount = $( '#stripe-' + source_type + '-payment-data' ).data( 'amount' );
						break;
					case 'sofort':
						extra_details.sofort = { country: $( '#billing_country' ).val() };
						break;
				}

				extra_details.type = source_type;

				if ( 'sepa_debit' === source_type ) {
					stripe.createSource( iban, extra_details ).then( wc_stripe_form.sourceResponse );
				} else {
					stripe.createSource( extra_details ).then( wc_stripe_form.sourceResponse );
				}
			}
		},

		sourceResponse: function( response ) {
			if ( response.error ) {
				$( document.body ).trigger( 'stripeError', response );
			} else if ( 'no' === wc_stripe_params.allow_prepaid_card && 'card' === response.source.type && 'prepaid' === response.source.card.funding ) {
				response.error = { message: wc_stripe_params.no_prepaid_card_msg };

				if ( 'yes' === wc_stripe_params.is_stripe_checkout ) {
					wc_stripe_form.submitError( '<ul class="woocommerce-error"><li>' + wc_stripe_params.no_prepaid_card_msg + '</li></ul>' );
				} else {
					$( document.body ).trigger( 'stripeError', response );
				}
			} else {
				wc_stripe_form.processStripeResponse( response.source );
			}
		},

		processStripeResponse: function( source ) {
			wc_stripe_form.reset();

			// Insert the Source into the form so it gets submitted to the server.
			wc_stripe_form.form.append( "<input type='hidden' class='stripe-source' name='stripe_source' value='" + source.id + "'/>" );

			if ( $( 'form#add_payment_method' ).length ) {
				$( wc_stripe_form.form ).off( 'submit', wc_stripe_form.form.onSubmit );
			}

			wc_stripe_form.form.submit();
		},

		onSubmit: function( e ) {
			if ( ! wc_stripe_form.isStripeChosen() ) {
				return;
			}

			if ( ! wc_stripe_form.isStripeSaveCardChosen() && ! wc_stripe_form.hasSource() && ! wc_stripe_form.hasToken() ) {
				e.preventDefault();

				wc_stripe_form.block();

				// Stripe Checkout.
				if ( 'yes' === wc_stripe_params.is_stripe_checkout && wc_stripe_form.isStripeModalNeeded() && wc_stripe_form.isStripeCardChosen() ) {
					if ( 'yes' === wc_stripe_params.is_checkout ) {
						return true;
					} else {
						wc_stripe_form.openModal();
						return false;
					}
				}

				/*
				 * For methods that needs redirect, we will create the
				 * source server side so we can obtain the order ID.
				 */
				if (
					wc_stripe_form.isBancontactChosen() ||
					wc_stripe_form.isGiropayChosen() ||
					wc_stripe_form.isIdealChosen() ||
					wc_stripe_form.isAlipayChosen() ||
					wc_stripe_form.isSofortChosen() ||
					wc_stripe_form.isP24Chosen() ||
					wc_stripe_form.isEpsChosen() ||
					wc_stripe_form.isMultibancoChosen()
				) {
					if ( $( 'form#order_review' ).length ) {
						$( 'form#order_review' )
							.off(
								'submit',
								this.onSubmit
							);

						wc_stripe_form.form.submit();

						return false;
					}

					if ( $( 'form.woocommerce-checkout' ).length ) {
						return true;
					}

					if ( $( 'form#add_payment_method' ).length ) {
						$( 'form#add_payment_method' )
							.off(
								'submit',
								this.onSubmit
							);

						wc_stripe_form.form.submit();

						return false;
					}
				}

				wc_stripe_form.createSource();

				// Prevent form submitting
				return false;
			} else if ( $( 'form#add_payment_method' ).length ) {
				e.preventDefault();

				// Stripe Checkout.
				if ( 'yes' === wc_stripe_params.is_stripe_checkout && wc_stripe_form.isStripeModalNeeded() && wc_stripe_form.isStripeCardChosen() ) {
					wc_stripe_form.openModal();

					return false;
				}

				wc_stripe_form.block();

				wc_stripe_form.createSource();
				return false;
			}
		},

		onCCFormChange: function() {
			wc_stripe_form.reset();
		},

		reset: function() {
			$( '.wc-stripe-error, .stripe-source, .stripe_token' ).remove();

			// Stripe Checkout.
			if ( 'yes' === wc_stripe_params.is_stripe_checkout ) {
				wc_stripe_form.stripe_submit = false;
			}
		},

		onSepaError: function( e ) {
			var errorContainer = wc_stripe_form.getSelectedPaymentElement().parents( 'li' ).eq(0).find( '.stripe-source-errors' );

			if ( e.error ) {
				console.log( e.error.message ); // Leave for troubleshooting.
				$( errorContainer ).html( '<ul class="woocommerce_error woocommerce-error wc-stripe-error"><li>' + e.error.message + '</li></ul>' );
			} else {
				$( errorContainer ).html( '' );
			}
		},

		onError: function( e, result ) {
			var message = result.error.message,
				errorContainer = wc_stripe_form.getSelectedPaymentElement().parents( 'li' ).eq(0).find( '.stripe-source-errors' );

			/*
			 * If payment method is SEPA and owner name is not completed,
			 * source cannot be created. So we need to show the normal
			 * Billing name is required error message on top of form instead
			 * of inline.
			 */
			if ( wc_stripe_form.isSepaChosen() ) {
				if ( 'invalid_owner_name' === result.error.code && wc_stripe_params.hasOwnProperty( result.error.code ) ) {
					var error = '<ul class="woocommerce-error"><li>' + wc_stripe_params[ result.error.code ] + '</li></ul>';

					return wc_stripe_form.submitError( error );
				}
			}

			/*
			 * Customers do not need to know the specifics of the below type of errors
			 * therefore return a generic localizable error message.
			 */
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

			if ( 'validation_error' === result.error.type && wc_stripe_params.hasOwnProperty( result.error.code ) ) {
				message = wc_stripe_params[ result.error.code ];
			}

			wc_stripe_form.reset();
			$( '.woocommerce-NoticeGroup-checkout' ).remove();
			console.log( result.error.message ); // Leave for troubleshooting.
			$( errorContainer ).html( '<ul class="woocommerce_error woocommerce-error wc-stripe-error"><li>' + message + '</li></ul>' );

			if ( $( '.wc-stripe-error' ).length ) {
				$( 'html, body' ).animate({
					scrollTop: ( $( '.wc-stripe-error' ).offset().top - 200 )
				}, 200 );
			}
			wc_stripe_form.unblock();
		},

		submitError: function( error_message ) {
			$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
			wc_stripe_form.form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' );
			wc_stripe_form.form.removeClass( 'processing' ).unblock();
			wc_stripe_form.form.find( '.input-text, select, input:checkbox' ).blur();
			
			var selector = '';

			if ( $( '#add_payment_method' ).length ) {
				selector = $( '#add_payment_method' );
			}

			if ( $( '#order_review' ).length ) {
				selector = $( '#order_review' );
			}

			if ( $( 'form.checkout' ).length ) {
				selector = $( 'form.checkout' );
			}

			if ( selector.length ) {
				$( 'html, body' ).animate({
					scrollTop: ( selector.offset().top - 100 )
				}, 500 );
			}

			$( document.body ).trigger( 'checkout_error' );
			wc_stripe_form.unblock();
		}
	};

	wc_stripe_form.init();
} );
