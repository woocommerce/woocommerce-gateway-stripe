jQuery( function( $ ) {
	'use strict';

	/* Open and close for legacy class */
	$( 'form.checkout, form#order_review' ).on( 'change', 'input[name="wc-stripe-payment-token"]', function() {
		if ( 'new' === $( '.stripe-legacy-payment-fields input[name="wc-stripe-payment-token"]:checked' ).val() ) {
			$( '.stripe-legacy-payment-fields #stripe-payment-data' ).slideDown( 200 );
		} else {
			$( '.stripe-legacy-payment-fields #stripe-payment-data' ).slideUp( 200 );
		}
	} );

	var stripe   = Stripe( wc_stripe_params.key ),
		elements = stripe.elements(),
		stripe_card;

	/**
	 * Object to handle Stripe elements payment form.
	 */
	var wc_stripe_elements_form = {
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
		 * Initialize event handlers and UI state.
		 */
		init: function() {
			// checkout page
			if ( $( 'form.woocommerce-checkout' ).length ) {
				this.form = $( 'form.woocommerce-checkout' );
			}

			$( 'form.woocommerce-checkout' )
				.on(
					'checkout_place_order_stripe checkout_place_order_stripe_bancontact checkout_place_order_stripe_sofort checkout_place_order_stripe_giropay checkout_place_order_stripe_ideal checkout_place_order_stripe_alipay checkout_place_order_stripe_sepa checkout_place_order_stripe_bitcoin',
					this.onSubmit
				);

			// pay order page
			if ( $( 'form#order_review' ).length ) {
				this.form = $( 'form#order_review' );
			}

			$( 'form#order_review' )
				.on(
					'submit',
					this.onSubmit
				);

			// add payment method page
			if ( $( 'form#add_payment_method' ).length ) {
				this.form = $( 'form#add_payment_method' );
			}

			$( 'form#add_payment_method' )
				.on(
					'submit',
					this.onSubmit
				);

			$( 'form.woocommerce-checkout' )
				.on(
					'change',
					'#stripe-bank-country',
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

			var style = {
				base: {
					iconColor: '#666EE8',
					color: '#31325F',
					lineHeight: '45px',
					fontSize: '15px',
					'::placeholder': {
				  		color: '#CFD7E0',
					}
				}
			};

			stripe_card = elements.create( 'card', { style: style, hidePostalCode: true } );

			stripe_card.addEventListener( 'change', function( event ) {
				wc_stripe_elements_form.onCCFormChange();

				if ( event.error ) {
					$( document.body ).trigger( 'stripeError', event );
				}
			});

			/**
			 * Only in checkout page we need to delay the mounting of the
			 * card as some AJAX process needs to happen before we do.
			 */
			if ( wc_stripe_params.is_checkout ) {
				$( document.body ).on( 'updated_checkout', function() {
					// Don't mount elements a second time.
					if ( stripe_card ) {
						stripe_card.unmount( '#stripe-card-element' );
					}

					stripe_card.mount( '#stripe-card-element' );
				});
			} else if ( $( 'form#add_payment_method' ).length || $( 'form#order_review' ).length ) {
				stripe_card.mount( '#stripe-card-element' );
			}
		},

		isStripeChosen: function() {
			return $( '#payment_method_stripe, #payment_method_stripe_bancontact, #payment_method_stripe_sofort, #payment_method_stripe_giropay, #payment_method_stripe_ideal, #payment_method_stripe_alipay, #payment_method_stripe_sepa, #payment_method_stripe_bitcoin' ).is( ':checked' ) || 'new' === $( 'input[name="wc-stripe-payment-token"]:checked' ).val();
		},
		// Currently only support saved cards via credit cards. No other payment method.
		isStripeSaveCardChosen: function() {
			return $( '#payment_method_stripe' ).is( ':checked' ) && 'new' !== $( 'input[name="wc-stripe-payment-token"]:checked' ).val();
		},

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

		isBitcoinChosen: function() {
			return $( '#payment_method_stripe_bitcoin' ).is( ':checked' );
		},

		hasSource: function() {
			return 0 < $( 'input.stripe-source' ).length;
		},

		isMobile: function() {
			if( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ) {
				return true;
			}

			return false;
		},

		block: function() {
			if ( wc_stripe_elements_form.isMobile() ) {
				$.blockUI({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});
			} else {
				wc_stripe_elements_form.form.block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});
			}
		},

		unblock: function() {
			if ( wc_stripe_elements_form.isMobile() ) {
				$.unblockUI();
			} else {
				wc_stripe_elements_form.form.unblock();
			}
		},

		getSelectedPaymentElement: function() {
			return $( '.wc_payment_methods input[name="payment_method"]:checked' );
		},

		onError: function( e, result ) {
			var message = result.error.message,
				errorContainer = wc_stripe_elements_form.getSelectedPaymentElement().parent( '.wc_payment_method' ).find( '.stripe-source-errors' );

			// Customers do not need to know the specifics of the below type of errors
			// therefore return a generic localizable error message.
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

			wc_stripe_elements_form.reset();
			console.log( result.error.message ); // Leave for troubleshooting.
			$( errorContainer ).html( '<ul class="woocommerce_error woocommerce-error wc-stripe-error"><li>' + message + '</li></ul>' );
			wc_stripe_elements_form.unblock();
		},

		getOwnerDetails: function() {
			var first_name = $( '#billing_first_name' ).length ? $( '#billing_first_name' ).val() : wc_stripe_params.billing_first_name,
				last_name  = $( '#billing_last_name' ).length ? $( '#billing_last_name' ).val() : wc_stripe_params.billing_last_name,
				extra_details = { owner: { name: '', address: {}, email: '', phone: '' } };

			extra_details.owner.name = first_name;

			if ( first_name && last_name ) {
				extra_details.owner.name = first_name + ' ' + last_name;
			}

			extra_details.owner.email = $( '#billing_email' ).val();
			extra_details.owner.phone = $( '#billing_phone' ).val();

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
			var extra_details = wc_stripe_elements_form.getOwnerDetails(),
				source_type   = 'card';

			if ( wc_stripe_elements_form.isBancontactChosen() ) {
				source_type = 'bancontact';
			}

			if ( wc_stripe_elements_form.isSepaChosen() ) {
				source_type = 'sepa_debit';
			}

			if ( wc_stripe_elements_form.isIdealChosen() ) {
				source_type = 'ideal';
			}

			if ( wc_stripe_elements_form.isSofortChosen() ) {
				source_type = 'sofort';
			}

			if ( wc_stripe_elements_form.isBitcoinChosen() ) {
				source_type = 'bitcoin';
			}

			if ( wc_stripe_elements_form.isGiropayChosen() ) {
				source_type = 'giropay';
			}

			if ( wc_stripe_elements_form.isAlipayChosen() ) {
				source_type = 'alipay';
			}

			if ( 'card' === source_type ) {
				stripe.createSource( stripe_card, extra_details ).then( wc_stripe_elements_form.sourceResponse );
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

						if ( 'bancontact' === source_type ) {
							extra_details.bancontact = { statement_descriptor: wc_stripe_params.statement_descriptor };
						}

						if ( 'giropay' === source_type ) {
							extra_details.giropay = { statement_descriptor: wc_stripe_params.statement_descriptor };
						}

						if ( 'ideal' === source_type ) {
							extra_details.ideal = { statement_descriptor: wc_stripe_params.statement_descriptor };
						}

						if ( 'sofort' === source_type ) {
							extra_details.sofort = { statement_descriptor: wc_stripe_params.statement_descriptor };
						}

						break;
				}

				// Handle special inputs that are unique to a payment method.
				switch ( source_type ) {
					case 'sepa_debit':
						extra_details.currency = $( '#stripe-' + source_type + '-payment-data' ).data( 'currency' );
						extra_details.sepa_debit = { iban: $( '#stripe-sepa-iban' ).val() };
						break;
					case 'ideal':
						extra_details.ideal = { bank: $( '#stripe-ideal-bank' ).val() };
						break;
					case 'sofort':
						extra_details.sofort = { country: $( '#stripe-sofort-country' ).val() };
						break;
					case 'bitcoin':
					case 'alipay':
						extra_details.currency = $( '#stripe-' + source_type + '-payment-data' ).data( 'currency' );
						extra_details.amount = $( '#stripe-' + source_type + '-payment-data' ).data( 'amount' );
						break;
				}

				extra_details.type = source_type;

				stripe.createSource( extra_details ).then( wc_stripe_elements_form.sourceResponse );
			}
		},

		sourceResponse: function( response ) {
			if ( response.error ) {
				$( document.body ).trigger( 'stripeError', response );
			} else if ( 'no' === wc_stripe_params.allow_prepaid_card && 'card' === response.source.type && 'prepaid' === response.source.card.funding ) {
				response.error = { message: wc_stripe_params.no_prepaid_card_msg };

				$( document.body ).trigger( 'stripeError', response );	
			} else {
				wc_stripe_elements_form.processStripeResponse( response.source );
			}
		},

		onSubmit: function( e ) {
			if ( wc_stripe_elements_form.isStripeChosen() && ! wc_stripe_elements_form.isStripeSaveCardChosen() && ! wc_stripe_elements_form.hasSource() ) {
				e.preventDefault();
				wc_stripe_elements_form.block();

				if ( wc_stripe_elements_form.isBancontactChosen() ) {
					return true;
				}

				if ( wc_stripe_elements_form.isGiropayChosen() ) {
					return true;
				}

				if ( wc_stripe_elements_form.isIdealChosen() ) {
					return true;
				}

				if ( wc_stripe_elements_form.isAlipayChosen() ) {
					return true;
				}

				if ( wc_stripe_elements_form.isSofortChosen() ) {
					// Check if Sofort bank country is chosen before proceed.
					if ( '-1' === $( '#stripe-bank-country' ).val() ) {
						var error = { error: { message: wc_stripe_params.no_bank_country_msg } };
						$( document.body ).trigger( 'stripeError', error );
						return false;
					}

					return true;
				}

				if ( wc_stripe_elements_form.isSepaChosen() ) {
					// Check if SEPA IBAN is filled before proceed.
					if ( '' === $( '#stripe-sepa-iban' ).val() ) {
						var errors = { error: { message: wc_stripe_params.no_iban_msg } };
						$( document.body ).trigger( 'stripeError', errors );
						return false;
					}

					wc_stripe_elements_form.validateCheckout();
				}

				wc_stripe_elements_form.validateCheckout();

				// Prevent form submitting
				return false;
			}
		},

		onCCFormChange: function() {
			wc_stripe_elements_form.reset();
		},

		processStripeResponse: function( source ) {
			wc_stripe_elements_form.reset();

			// Insert the Source into the form so it gets submitted to the server.
			wc_stripe_elements_form.form.append( "<input type='hidden' class='stripe-source' name='stripe_source' value='" + JSON.stringify( source ) + "'/>" );

			wc_stripe_elements_form.form.submit();
		},

		reset: function() {
			$( '.wc-stripe-error, .stripe-source ' ).remove();
		},

		getRequiredFields: function() {
			return wc_stripe_elements_form.form.find( '.form-row.validate-required > input, .form-row.validate-required > select' );
		},

		validateCheckout: function() {
			var data = {
				'nonce': wc_stripe_params.stripe_nonce,
				'required_fields': wc_stripe_elements_form.getRequiredFields().serialize(),
				'all_fields': wc_stripe_elements_form.form.serialize(),
				'source_type': wc_stripe_elements_form.getSelectedPaymentElement().val()
			};

			$.ajax({
				type:		'POST',
				url:		wc_stripe_elements_form.getAjaxURL( 'validate_checkout' ),
				data:		data,
				dataType:   'json',
				success:	function( result ) {
					if ( 'success' === result ) {
						wc_stripe_elements_form.createSource();
					} else if ( result.messages ) {
						wc_stripe_elements_form.reset();
						wc_stripe_elements_form.submitError( result.messages );
					}
				}
			});	
		},

		submitError: function( error_message ) {
			$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
			wc_stripe_elements_form.form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' );
			wc_stripe_elements_form.form.removeClass( 'processing' ).unblock();
			wc_stripe_elements_form.form.find( '.input-text, select, input:checkbox' ).blur();
			$( 'html, body' ).animate({
				scrollTop: ( $( 'form.checkout' ).offset().top - 100 )
			}, 1000 );
			$( document.body ).trigger( 'checkout_error' );
		}
	};

	wc_stripe_elements_form.init();
} );
