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
		 * Open Payment Request modal.
		 *
		 * @param {Object} details Payment request details.
		 */
		openPaymentRequest: function( details ) {
			var self = wcStripePaymentRequest;

			var supportedInstruments = [{
				supportedMethods: self.getSupportedMethods()
			}];

			new PaymentRequest( supportedInstruments, details )
				.show()
				.then( function( instrumentResponse ) {
					// @TODO
					// sendPaymentToServer( instrumentResponse );
					console.log( instrumentResponse );
				})
				.catch( function( err ) {
					// @TODO
					console.log( err );
				});
		},

		/**
		 * Initialize the PaymentRequest.
		 *
		 * @param {Object} evt DOM events.
		 */
		initPaymentRequest: function( evt ) {
			evt.preventDefault();
			var self = wcStripePaymentRequest;

			$.ajax({
				type: 'get',
				url:  self.getAjaxURL( 'get_cart_details' ),
				success: function( response ) {
					self.openPaymentRequest( response );
				}
			});
		}
	};

	wcStripePaymentRequest.init();

})( jQuery );
