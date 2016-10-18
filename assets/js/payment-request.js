/*global jQuery, wcStripePaymentRequestParams, PaymentRequest */
/*jshint es3: false */
/*jshint devel: true */
(function( $ ) {
	var wcStripePaymentRequest = {
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
			return 'PaymentRequest' in window && 'https:' === window.location.protocol;
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
			return wcStripePaymentRequestParams.wc_ajax_url
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
				security: wcStripePaymentRequestParams.payment_nonce
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
			var self = wcStripePaymentRequest;

			var supportedInstruments = [{
				supportedMethods: self.getSupportedMethods()
			}];

			var options = {
				requestPayerPhone: true,
				requestPayerEmail: true
			};

			new PaymentRequest( supportedInstruments, details, options )
				.show()
				.then( function( response ) {
					console.log( response );
					self.sendPayment( response );
				})
				.catch( function( err ) {
					// @TODO
					console.log( err );
				});
		},

		/**
		 * Send payment to Stripe.
		 *
		 * @param {PaymentResponse} payment Payment Response instance.
		 */
		sendPayment: function( payment ) {
			var self = wcStripePaymentRequest;

			var data = {
				security:       wcStripePaymentRequestParams.payment_nonce,
				creditCard:     {
					brand:      payment.methodName,
					number:     payment.details.cardNumber,
					cvc:        payment.details.cardSecurityCode,
					holderName: payment.details.cardholderName,
					expiry:     {
						month: payment.details.expiryMonth,
						year:  payment.details.expiryYear
					}
				},
				contactInfo:    {
					email: payment.payerEmail,
					phone: payment.payerPhone
				},
				billingAddress: payment.details.billingAddress
			};

			$.ajax({
				type:    'POST',
				data:    data,
				url:     self.getAjaxURL( 'create_order' ),
				success: function() {
				// success: function( response ) {
					// if ( ! response.success ) {
					// 	console.log( 'ERRO!' );
					// }

					payment.complete( 'success' )
						.then( function() {
							// document.getElementById('result').innerHTML =
							// instrumentToJsonString(instrumentResponse);
						})
						.catch( function( err ) {
							console.log( err );
						});
				}
			});
		}
	};

	wcStripePaymentRequest.init();

})( jQuery );
