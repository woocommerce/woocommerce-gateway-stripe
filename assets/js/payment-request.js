/*global jQuery, wcStripePaymentRequestParams, PaymentRequest, Stripe, Promise */
/*jshint es3: false */
/*jshint devel: true */
(function( $ ) {

	/**
	 * WooCommerce Stripe PaymentRequest class.
	 *
	 * @type {Object}
	 */
	var wcStripePaymentRequest = {

		/**
		 * Initialize class events.
		 */
		init: function() {
			var self = this;

			if ( self.hasPaymentRequestSupport() ) {
				$( document.body )
					.on( 'click', '.cart_totals a.checkout-button', self.initPaymentRequest );
			}
		},

		/**
		 * Check if browser support PaymentRequest class and if is under HTTPS.
		 *
		 * @return {Bool}
		 */
		hasPaymentRequestSupport: function() {
			return window.PaymentRequest && 'https:' === window.location.protocol;
		},

		/**
		 * Get Stripe supported methods.
		 *
		 * @return {Array}
		 */
		getSupportedMethods: function() {
			return [
				'amex',
				'diners',
				'discover',
				'jcb',
				'mastercard',
				'visa'
			];
		},

		/**
		 * Get WC AJAX endpoint URL.
		 *
		 * @param  {String} endpoint Endpoint.
		 * @return {String}
		 */
		getAjaxURL: function( endpoint ) {
			return wcStripePaymentRequestParams.ajax_url
				.toString()
				.replace( '%%endpoint%%', 'wc_stripe_' + endpoint );
		},

		/**
		 * Initialize the PaymentRequest.
		 *
		 * @param {Object} evt DOM events.
		 */
		initPaymentRequest: function( evt ) {
			evt.preventDefault();
			var self = wcStripePaymentRequest;
			var data = {
				security: wcStripePaymentRequestParams.nonce.payment
			};

			$.ajax({
				type:    'POST',
				data:    data,
				url:     self.getAjaxURL( 'get_cart_details' ),
				success: function( response ) {
					self.openPaymentRequest( response );
				}
			});
		},

		/**
		 * Open Payment Request modal.
		 *
		 * @param {Object} details Payment request details.
		 */
		openPaymentRequest: function( details ) {
			var self = this;

			// PaymentRequest options.
			var supportedInstruments = [{
				supportedMethods: self.getSupportedMethods()
			}];
			var options = {
				requestPayerPhone: true,
				requestPayerEmail: true
			};
			if ( details.shipping_required ) {
				options.requestShipping = true;
			}
			var paymentDetails = details.order_data;

			// Init PaymentRequest.
			var request = new PaymentRequest( supportedInstruments, paymentDetails, options );

			// Set up shipping.
			request.addEventListener( 'shippingaddresschange', function( evt ) {
				evt.updateWith( new Promise( function( resolve, reject ) {
					self.updateShippingOptions( paymentDetails, request.shippingAddress, resolve, reject );
				}));
			});
			request.addEventListener( 'shippingoptionchange', function( evt ) {
				evt.updateWith( new Promise( function( resolve, reject ) {
					self.updateShippingDetails( paymentDetails, request.shippingOption, resolve, reject );
				}));
			});

			// Open Payment Request UI.
			request.show().then( function( payment ) {
				self.processPayment( payment );
			})
			.catch( function( err ) {
				console.error( err );
			});
		},

		/**
		 * Update shipping options.
		 *
		 * @param {Object}         details Payment details.
		 * @param {PaymentAddress} address Shipping address.
		 * @param {Function}       resolve The callback to invoke with updated line items and shipping options.
		 * @param {Function}	   reject  The callback to invoke in case of failure.
		 */
		updateShippingOptions: function( details, address, resolve, reject ) {
			var self = this;
			var data = {
				security:  wcStripePaymentRequestParams.nonce.shipping,
				country:   address.country,
				state:     address.region,
				postcode:  address.postalCode,
				city:      address.city,
				address:   typeof address.addressLine[0] === 'undefined' ? '' : address.addressLine[0],
				address_2: typeof address.addressLine[1] === 'undefined' ? '' : address.addressLine[1]
			};

			$.ajax({
				type:    'POST',
				data:    data,
				url:     self.getAjaxURL( 'get_shipping_options' ),
				success: function( response ) {
					details.shippingOptions = response;
					if ( details.shippingOptions.length == 1 ) {
						// The sole shipping option was auto-selected. Update the details
						// (including the total).
						self.updateShippingDetails(
								details, details.shippingOptions[0].id, resolve, reject );
					} else {
						resolve( details );
					}
				}
			});
		},

		/**
		 * Updates the shipping price and the total based on the shipping option.
		 *
		 * @param {Object}   details        The line items and shipping options.
		 * @param {String}   shippingOption User's preferred shipping option to use for shipping price calculations.
		 * @param {Function} resolve        The callback to invoke with updated line items and shipping options.
		 * @param {Function} reject         The callback to invoke in case of failure.
		 */
		updateShippingDetails: function( details, shippingOption, resolve, reject ) {
			var self     = this;
			var selected = null;
			var data     = {
				security:  wcStripePaymentRequestParams.nonce.update_shipping,
				shipping_method: [
					shippingOption
				]
			};

			$.ajax({
				type:    'POST',
				data:    data,
				url:     self.getAjaxURL( 'update_shipping_method' ),
				success: function( response ) {
					details.shippingOptions.forEach( function( value, index ) {
						if ( value.id === shippingOption ) {
							selected = index;
							value.selected = true;
							details.total.amount.value = parseFloat( response.total );

							if ( response.items ) {
								details.displayItems = response.items;
							}
						} else {
							value.selected = false;
						}
					});

					if ( null === selected ) {
						reject( wcStripePaymentRequestParams.i18n.unknown_shipping.toString().replace( '[option]', shippingOption ) );
					}

					resolve( details );
				}
			});
		},

		/**
		 * Get order data.
		 *
		 * @param {PaymentResponse} payment Payment Response instance.
		 *
		 * @return {Object}
		 */
		getOrderData: function( payment ) {
			var billing  = payment.details.billingAddress;
			var shipping = payment.shippingAddress;
			var data     = {
				_wpnonce:                  wcStripePaymentRequestParams.nonce.checkout,
				billing_first_name:        billing.recipient.split( ' ' ).slice( 0, 1 ).join( ' ' ),
				billing_last_name:         billing.recipient.split( ' ' ).slice( 1 ).join( ' ' ),
				billing_company:           billing.organization,
				billing_email:             payment.payerEmail,
				billing_phone:             payment.payerPhone,
				billing_country:           billing.country,
				billing_address_1:         typeof billing.addressLine[0] === 'undefined' ? '' : billing.addressLine[0],
				billing_address_2:         typeof billing.addressLine[1] === 'undefined' ? '' : billing.addressLine[1],
				billing_city:              billing.city,
				billing_state:             billing.region,
				billing_postcode:          billing.postalCode,
				shipping_first_name:       '',
				shipping_last_name:        '',
				shipping_company:          '',
				shipping_country:          '',
				shipping_address_1:        '',
				shipping_address_2:        '',
				shipping_city:             '',
				shipping_state:            '',
				shipping_postcode:         '',
				shipping_method:           [ payment.shippingOption ],
				order_comments:            '',
				payment_method:            'stripe',
				// 'wc-stripe-payment-token': 'new',
				stripe_token:              '',
			};

			if ( shipping ) {
				data.shipping_first_name = shipping.recipient.split( ' ' ).slice( 0, 1 ).join( ' ' );
				data.shipping_last_name  = shipping.recipient.split( ' ' ).slice( 1 ).join( ' ' );
				data.shipping_company    = shipping.organization;
				data.shipping_country    = shipping.country;
				data.shipping_address_1  = typeof shipping.addressLine[0] === 'undefined' ? '' : shipping.addressLine[0];
				data.shipping_address_2  = typeof shipping.addressLine[1] === 'undefined' ? '' : shipping.addressLine[1];
				data.shipping_city       = shipping.city;
				data.shipping_state      = shipping.region;
				data.shipping_postcode   = shipping.postalCode;
			}

			return data;
		},

		/**
		 * Get credit card data.
		 *
		 * @param {PaymentResponse} payment Payment Response instance.
		 *
		 * @return {Object}
		 */
		getCardData: function( payment ) {
			var billing = payment.details.billingAddress;
			var data    = {
				number:          payment.details.cardNumber,
				cvc:             payment.details.cardSecurityCode,
				exp_month:       parseInt( payment.details.expiryMonth, 10 ) || 0,
				exp_year:        parseInt( payment.details.expiryYear, 10 ) || 0,
				name:            billing.recipient,
				address_line1:   typeof billing.addressLine[0] === 'undefined' ? '' : billing.addressLine[0],
				address_line2:   typeof billing.addressLine[1] === 'undefined' ? '' : billing.addressLine[1],
				address_state:   billing.region,
				address_city:    billing.city,
				address_zip:     billing.postalCode,
				address_country: billing.country
			};

			return data;
		},

		/**
		 * Generate error message HTML.
		 *
		 * @param  {String} message Error message.
		 * @return {Object}
		 */
		getErrorMessageHTML: function( message ) {
			return $( '<div class="woocommerce-error" />' ).text( message );
		},

		/**
		 * Abort payment and display error messages.
		 *
		 * @param {PaymentResponse} payment Payment response instance.
		 * @param {String}          message Error message to display.
		 */
		abortPayment: function( payment, message ) {
			payment.complete( 'fail' ).then( function() {
				var $form = $( '.shop_table.cart' ).closest( 'form' );
				$( '.woocommerce-error' ).remove();
				$form.before( message );
				$( 'html, body' ).animate({
					scrollTop: $form.prev( '.woocommerce-error' ).offset().top
				}, 600 );
			})
			.catch( function( err ) {
				console.error( err );
			});
		},

		/**
		 * Complete payment.
		 *
		 * @param {PaymentResponse} payment Payment response instance.
		 * @param {String}          url     Order thank you page URL.
		 */
		completePayment: function( payment, url ) {
			payment.complete( 'success' ).then( function() {
				// Success, then redirect to the Thank You page.
				window.location = url;
			})
			.catch( function( err ) {
				console.error( err );
			});
		},

		/**
		 * Process payment.
		 *
		 * @param {PaymentResponse} payment Payment response instance.
		 */
		processPayment: function( payment ) {
			var self      = this;
			var orderData = self.getOrderData( payment );
			var cardData  = self.getCardData( payment );

			Stripe.setPublishableKey( wcStripePaymentRequestParams.stripe.key );
			Stripe.createToken( cardData, function( status, response ) {
				if ( response.error ) {
					self.abortPayment( payment, self.getErrorMessageHTML( response.error.message ) );
				} else {
					// Check if we allow prepaid cards.
					if ( 'no' === wcStripePaymentRequestParams.stripe.allow_prepaid_card && 'prepaid' === response.card.funding ) {
						self.abortPayment( payment, self.getErrorMessageHTML( wcStripePaymentRequestParams.i18n.no_prepaid_card ) );
					} else {
						// Token contains id, last4, and card type.
						orderData.stripe_token = response.id;

						$.ajax({
							type:     'POST',
							data:     orderData,
							dataType: 'json',
							url:      self.getAjaxURL( 'create_order' ),
							success: function( response ) {
								if ( 'success' === response.result ) {
									self.completePayment( payment, response.redirect );
								} else {
									self.abortPayment( payment, response.messages );
								}
							},
							complete: function( jqXHR, textStatus ) {
								if ( 'success' !== textStatus ) {
									console.error( jqXHR );
								}
							}
						});
					}
				}
			});
		}
	};

	wcStripePaymentRequest.init();

})( jQuery );
