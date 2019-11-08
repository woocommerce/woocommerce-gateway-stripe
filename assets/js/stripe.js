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

		/**
		 * Unmounts all Stripe elements when the checkout page is being updated.
		 */
		unmountElements: function() {
			if ( 'yes' === wc_stripe_params.inline_cc_form ) {
				stripe_card.unmount( '#stripe-card-element' );
			} else {
				stripe_card.unmount( '#stripe-card-element' );
				stripe_exp.unmount( '#stripe-exp-element' );
				stripe_cvc.unmount( '#stripe-cvc-element' );
			}
		},

		/**
		 * Mounts all elements to their DOM nodes on initial loads and updates.
		 */
		mountElements: function() {
			if ( ! $( '#stripe-card-element' ).length ) {
				return;
			}

			if ( 'yes' === wc_stripe_params.inline_cc_form ) {
				return stripe_card.mount( '#stripe-card-element' );
			}

			stripe_card.mount( '#stripe-card-element' );
			stripe_exp.mount( '#stripe-exp-element' );
			stripe_cvc.mount( '#stripe-cvc-element' );
		},

		/**
		 * Creates all Stripe elements that will be used to enter cards or IBANs.
		 */
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
					// Don't re-mount if already mounted in DOM.
					if ( $( '#stripe-card-element' ).children().length ) {
						return;
					}

					// Unmount prior to re-mounting.
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

		/**
		 * Updates the card brand logo with non-inline CC forms.
		 *
		 * @param {string} brand The identifier of the chosen brand.
		 */
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

			// Subscription early renewals modal.
			$( '#early_renewal_modal_submit' ).on( 'click', this.onEarlyRenewalSubmit );

			wc_stripe_form.createElements();

			// Listen for hash changes in order to handle payment intents
			window.addEventListener( 'hashchange', wc_stripe_form.onHashChange );
			wc_stripe_form.maybeConfirmIntent();
		},

		/**
		 * Check to see if Stripe in general is being used for checkout.
		 *
		 * @return {boolean}
		 */
		isStripeChosen: function() {
			return $( '#payment_method_stripe, #payment_method_stripe_bancontact, #payment_method_stripe_sofort, #payment_method_stripe_giropay, #payment_method_stripe_ideal, #payment_method_stripe_alipay, #payment_method_stripe_sepa, #payment_method_stripe_eps, #payment_method_stripe_multibanco' ).is( ':checked' ) || ( $( '#payment_method_stripe' ).is( ':checked' ) && 'new' === $( 'input[name="wc-stripe-payment-token"]:checked' ).val() ) || ( $( '#payment_method_stripe_sepa' ).is( ':checked' ) && 'new' === $( 'input[name="wc-stripe-payment-token"]:checked' ).val() );
		},

		/**
		 * Currently only support saved cards via credit cards and SEPA. No other payment method.
		 *
		 * @return {boolean}
		 */
		isStripeSaveCardChosen: function() {
			return (
				$( '#payment_method_stripe' ).is( ':checked' )
				&& $( 'input[name="wc-stripe-payment-token"]' ).is( ':checked' )
				&& 'new' !== $( 'input[name="wc-stripe-payment-token"]:checked' ).val()
			) || (
				$( '#payment_method_stripe_sepa' ).is( ':checked' )
				&& $( 'input[name="wc-stripe_sepa-payment-token"]' ).is( ':checked' )
				&& 'new' !== $( 'input[name="wc-stripe_sepa-payment-token"]:checked' ).val()
			);
		},

		/**
		 * Check if Stripe credit card is being used used.
		 *
		 * @return {boolean}
		 */
		isStripeCardChosen: function() {
			return $( '#payment_method_stripe' ).is( ':checked' );
		},

		/**
		 * Check if Stripe Bancontact is being used used.
		 *
		 * @return {boolean}
		 */
		isBancontactChosen: function() {
			return $( '#payment_method_stripe_bancontact' ).is( ':checked' );
		},

		/**
		 * Check if Stripe Giropay is being used used.
		 *
		 * @return {boolean}
		 */
		isGiropayChosen: function() {
			return $( '#payment_method_stripe_giropay' ).is( ':checked' );
		},

		/**
		 * Check if Stripe iDeal is being used used.
		 *
		 * @return {boolean}
		 */
		isIdealChosen: function() {
			return $( '#payment_method_stripe_ideal' ).is( ':checked' );
		},

		/**
		 * Check if Stripe SOFORT is being used used.
		 *
		 * @return {boolean}
		 */
		isSofortChosen: function() {
			return $( '#payment_method_stripe_sofort' ).is( ':checked' );
		},

		/**
		 * Check if Stripe Alipay is being used used.
		 *
		 * @return {boolean}
		 */
		isAlipayChosen: function() {
			return $( '#payment_method_stripe_alipay' ).is( ':checked' );
		},

		/**
		 * Check if Stripe SEPA Direct Debit is being used used.
		 *
		 * @return {boolean}
		 */
		isSepaChosen: function() {
			return $( '#payment_method_stripe_sepa' ).is( ':checked' );
		},

		/**
		 * Check if Stripe P24 is being used used.
		 *
		 * @return {boolean}
		 */
		isP24Chosen: function() {
			return $( '#payment_method_stripe_p24' ).is( ':checked' );
		},

		/**
		 * Check if Stripe EPS is being used used.
		 *
		 * @return {boolean}
		 */
		isEpsChosen: function() {
			return $( '#payment_method_stripe_eps' ).is( ':checked' );
		},

		/**
		 * Check if Stripe Multibanco is being used used.
		 *
		 * @return {boolean}
		 */
		isMultibancoChosen: function() {
			return $( '#payment_method_stripe_multibanco' ).is( ':checked' );
		},

		/**
		 * Checks if a source ID is present as a hidden input.
		 * Only used when SEPA Direct Debit is chosen.
		 *
		 * @return {boolean}
		 */
		hasSource: function() {
			return 0 < $( 'input.stripe-source' ).length;
		},

		/**
		 * Check whether a mobile device is being used.
		 *
		 * @return {boolean}
		 */
		isMobile: function() {
			if( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test( navigator.userAgent ) ) {
				return true;
			}

			return false;
		},

		/**
		 * Blocks payment forms with an overlay while being submitted.
		 */
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

		/**
		 * Removes overlays from payment forms.
		 */
		unblock: function() {
			wc_stripe_form.form && wc_stripe_form.form.unblock();
		},

		/**
		 * Returns the selected payment method HTML element.
		 *
		 * @return {HTMLElement}
		 */
		getSelectedPaymentElement: function() {
			return $( '.payment_methods input[name="payment_method"]:checked' );
		},

		/**
		 * Retrieves "owner" data from either the billing fields in a form or preset settings.
		 *
		 * @return {Object}
		 */
		getOwnerDetails: function() {
			var first_name = $( '#billing_first_name' ).length ? $( '#billing_first_name' ).val() : wc_stripe_params.billing_first_name,
				last_name  = $( '#billing_last_name' ).length ? $( '#billing_last_name' ).val() : wc_stripe_params.billing_last_name,
				owner      = { name: '', address: {}, email: '', phone: '' };

			owner.name = first_name;

			if ( first_name && last_name ) {
				owner.name = first_name + ' ' + last_name;
			} else {
				owner.name = $( '#stripe-payment-data' ).data( 'full-name' );
			}

			owner.email = $( '#billing_email' ).val();
			owner.phone = $( '#billing_phone' ).val();

			/* Stripe does not like empty string values so
			 * we need to remove the parameter if we're not
			 * passing any value.
			 */
			if ( typeof owner.phone === 'undefined' || 0 >= owner.phone.length ) {
				delete owner.phone;
			}

			if ( typeof owner.email === 'undefined' || 0 >= owner.email.length ) {
				if ( $( '#stripe-payment-data' ).data( 'email' ).length ) {
					owner.email = $( '#stripe-payment-data' ).data( 'email' );
				} else {
					delete owner.email;
				}
			}

			if ( typeof owner.name === 'undefined' || 0 >= owner.name.length ) {
				delete owner.name;
			}

			owner.address.line1       = $( '#billing_address_1' ).val() || wc_stripe_params.billing_address_1;
			owner.address.line2       = $( '#billing_address_2' ).val() || wc_stripe_params.billing_address_2;
			owner.address.state       = $( '#billing_state' ).val()     || wc_stripe_params.billing_state;
			owner.address.city        = $( '#billing_city' ).val()      || wc_stripe_params.billing_city;
			owner.address.postal_code = $( '#billing_postcode' ).val()  || wc_stripe_params.billing_postcode;
			owner.address.country     = $( '#billing_country' ).val()   || wc_stripe_params.billing_country;

			return {
				owner: owner,
			};
		},

		/**
		 * Initiates the creation of a Source object.
		 *
		 * Currently this is only used for credit cards and SEPA Direct Debit,
		 * all other payment methods work with redirects to create sources.
		 */
		createSource: function() {
			var extra_details = wc_stripe_form.getOwnerDetails();

			// Handle SEPA Direct Debit payments.
			if ( wc_stripe_form.isSepaChosen() ) {
				extra_details.currency = $( '#stripe-sepa_debit-payment-data' ).data( 'currency' );
				extra_details.mandate  = { notification_method: wc_stripe_params.sepa_mandate_notification };
				extra_details.type     = 'sepa_debit';

				return stripe.createSource( iban, extra_details ).then( wc_stripe_form.sourceResponse );
			}

			// Handle card payments.
			return stripe.createSource( stripe_card, extra_details )
				.then( wc_stripe_form.sourceResponse );
		},

		/**
		 * Handles responses, based on source object.
		 *
		 * @param {Object} response The `stripe.createSource` response.
		 */
		sourceResponse: function( response ) {
			if ( response.error ) {
				return $( document.body ).trigger( 'stripeError', response );
			}

			wc_stripe_form.reset();

			wc_stripe_form.form.append(
				$( '<input type="hidden" />' )
					.addClass( 'stripe-source' )
					.attr( 'name', 'stripe_source' )
					.val( response.source.id )
			);

			if ( $( 'form#add_payment_method' ).length ) {
				$( wc_stripe_form.form ).off( 'submit', wc_stripe_form.form.onSubmit );
			}

			wc_stripe_form.form.submit();
		},

		/**
		 * Performs payment-related actions when a checkout/payment form is being submitted.
		 *
		 * @return {boolean} An indicator whether the submission should proceed.
		 *                   WooCommerce's checkout.js stops only on `false`, so this needs to be explicit.
		 */
		onSubmit: function() {
			if ( ! wc_stripe_form.isStripeChosen() ) {
				return true;
			}

			// If a source is already in place, submit the form as usual.
			if ( wc_stripe_form.isStripeSaveCardChosen() || wc_stripe_form.hasSource() ) {
				return true;
			}

			// For methods that needs redirect, we will create the source server side so we can obtain the order ID.
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
				return true;
			}

			wc_stripe_form.block();
			wc_stripe_form.createSource();

			return false;
		},

		/**
		 * If a new credit card is entered, reset sources.
		 */
		onCCFormChange: function() {
			wc_stripe_form.reset();
		},

		/**
		 * Removes all Stripe errors and hidden fields with IDs from the form.
		 */
		reset: function() {
			$( '.wc-stripe-error, .stripe-source' ).remove();
		},

		/**
		 * Displays a SEPA-specific error message.
		 *
		 * @param {Event} e The event with the error.
		 */
		onSepaError: function( e ) {
			var errorContainer = wc_stripe_form.getSelectedPaymentElement().parents( 'li' ).eq( 0 ).find( '.stripe-source-errors' );

			if ( ! e.error ) {
				return $( errorContainer ).html( '' );
			}

			console.log( e.error.message ); // Leave for troubleshooting.
			$( errorContainer ).html( '<ul class="woocommerce_error woocommerce-error wc-stripe-error"><li /></ul>' );
			$( errorContainer ).find( 'li' ).text( e.error.message ); // Prevent XSS
		},

		/**
		 * Displays stripe-related errors.
		 *
		 * @param {Event}  e      The jQuery event.
		 * @param {Object} result The result of Stripe call.
		 */
		onError: function( e, result ) {
			var message = result.error.message;
			var selectedMethodElement = wc_stripe_form.getSelectedPaymentElement().closest( 'li' );
			var savedTokens = selectedMethodElement.find( '.woocommerce-SavedPaymentMethods-tokenInput' );
			var errorContainer;

			if ( savedTokens.length ) {
				// In case there are saved cards too, display the message next to the correct one.
				var selectedToken = savedTokens.filter( ':checked' );

				if ( selectedToken.closest( '.woocommerce-SavedPaymentMethods-new' ).length ) {
					// Display the error next to the CC fields if a new card is being entered.
					errorContainer = $( '#wc-stripe-cc-form .stripe-source-errors' );
				} else {
					// Display the error next to the chosen saved card.
					errorContainer = selectedToken.closest( 'li' ).find( '.stripe-source-errors' );
				}
			} else {
				// When no saved cards are available, display the error next to CC fields.
				errorContainer = selectedMethodElement.find( '.stripe-source-errors' );
			}

			/*
			 * If payment method is SEPA and owner name is not completed,
			 * source cannot be created. So we need to show the normal
			 * Billing name is required error message on top of form instead
			 * of inline.
			 */
			if ( wc_stripe_form.isSepaChosen() ) {
				if ( 'invalid_owner_name' === result.error.code && wc_stripe_params.hasOwnProperty( result.error.code ) ) {
					var error = '<ul class="woocommerce-error"><li /></ul>';
					error.find( 'li' ).text( wc_stripe_params[ result.error.code ] ); // Prevent XSS

					return wc_stripe_form.submitError( error );
				}
			}

			// Notify users that the email is invalid.
			if ( 'email_invalid' === result.error.code ) {
				message = wc_stripe_params.email_invalid;
			} else if (
				/*
				 * Customers do not need to know the specifics of the below type of errors
				 * therefore return a generic localizable error message.
				 */
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
			$( errorContainer ).html( '<ul class="woocommerce_error woocommerce-error wc-stripe-error"><li /></ul>' );
			$( errorContainer ).find( 'li' ).text( message ); // Prevent XSS

			if ( $( '.wc-stripe-error' ).length ) {
				$( 'html, body' ).animate({
					scrollTop: ( $( '.wc-stripe-error' ).offset().top - 200 )
				}, 200 );
			}
			wc_stripe_form.unblock();
			$.unblockUI(); // If arriving via Payment Request Button.
		},

		/**
		 * Displays an error message in the beginning of the form and scrolls to it.
		 *
		 * @param {Object} error_message An error message jQuery object.
		 */
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
		},

		/**
		 * Handles changes in the hash in order to show a modal for PaymentIntent/SetupIntent confirmations.
		 *
		 * Listens for `hashchange` events and checks for a hash in the following format:
		 * #confirm-pi-<intentClientSecret>:<successRedirectURL>
		 *
		 * If such a hash appears, the partials will be used to call `stripe.handleCardPayment`
		 * in order to allow customers to confirm an 3DS/SCA authorization, or stripe.handleCardSetup if
		 * what needs to be confirmed is a SetupIntent.
		 *
		 * Those redirects/hashes are generated in `WC_Gateway_Stripe::process_payment`.
		 */
		onHashChange: function() {
			var partials = window.location.hash.match( /^#?confirm-(pi|si)-([^:]+):(.+)$/ );

			if ( ! partials || 4 > partials.length ) {
				return;
			}

			var type               = partials[1];
			var intentClientSecret = partials[2];
			var redirectURL        = decodeURIComponent( partials[3] );

			// Cleanup the URL
			window.location.hash = '';

			wc_stripe_form.openIntentModal( intentClientSecret, redirectURL, false, 'si' === type );
		},

		maybeConfirmIntent: function() {
			if ( ! $( '#stripe-intent-id' ).length || ! $( '#stripe-intent-return' ).length ) {
				return;
			}

			var intentSecret = $( '#stripe-intent-id' ).val();
			var returnURL    = $( '#stripe-intent-return' ).val();

			wc_stripe_form.openIntentModal( intentSecret, returnURL, true, false );
		},

		/**
		 * Opens the modal for PaymentIntent authorizations.
		 *
		 * @param {string}  intentClientSecret The client secret of the intent.
		 * @param {string}  redirectURL        The URL to ping on fail or redirect to on success.
		 * @param {boolean} alwaysRedirect     If set to true, an immediate redirect will happen no matter the result.
		 *                                     If not, an error will be displayed on failure.
		 * @param {boolean} isSetupIntent      If set to true, ameans that the flow is handling a Setup Intent.
		 *                                     If false, it's a Payment Intent.
		 */
		openIntentModal: function( intentClientSecret, redirectURL, alwaysRedirect, isSetupIntent ) {
			stripe[ isSetupIntent ? 'handleCardSetup' : 'handleCardPayment' ]( intentClientSecret )
				.then( function( response ) {
					if ( response.error ) {
						throw response.error;
					}

					var intent = response[ isSetupIntent ? 'setupIntent' : 'paymentIntent' ];
					if ( 'requires_capture' !== intent.status && 'succeeded' !== intent.status ) {
						return;
					}

					window.location = redirectURL;
				} )
				.catch( function( error ) {
					if ( alwaysRedirect ) {
						return window.location = redirectURL;
					}

					$( document.body ).trigger( 'stripeError', { error: error } );
					wc_stripe_form.form && wc_stripe_form.form.removeClass( 'processing' );

					// Report back to the server.
					$.get( redirectURL + '&is_ajax' );
				} );
		},

		/**
		 * Prevents the standard behavior of the "Renew Now" button in the
		 * early renewals modal by using AJAX instead of a simple redirect.
		 *
		 * @param {Event} e The event that occured.
		 */
		onEarlyRenewalSubmit: function( e ) {
			e.preventDefault();

			$.ajax( {
				url: $( '#early_renewal_modal_submit' ).attr( 'href' ),
				method: 'get',
				success: function( html ) {
					var response = $.parseJSON( html );

					if ( response.stripe_sca_required ) {
						wc_stripe_form.openIntentModal( response.intent_secret, response.redirect_url, true, false );
					} else {
						window.location = response.redirect_url;
					}
				},
			} );

			return false;
		},
	};

	wc_stripe_form.init();
} );
