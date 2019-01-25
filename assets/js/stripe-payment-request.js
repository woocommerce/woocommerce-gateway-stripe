/* global wc_stripe_payment_request_params, Stripe */
jQuery( function( $ ) {
	'use strict';

	var stripe = Stripe( wc_stripe_payment_request_params.stripe.key ),
		paymentRequestType;

	/**
	 * Object to handle Stripe payment forms.
	 */
	var wc_stripe_payment_request = {
		/**
		 * Get WC AJAX endpoint URL.
		 *
		 * @param  {String} endpoint Endpoint.
		 * @return {String}
		 */
		getAjaxURL: function( endpoint ) {
			return wc_stripe_payment_request_params.ajax_url
				.toString()
				.replace( '%%endpoint%%', 'wc_stripe_' + endpoint );
		},

		getCartDetails: function() {
			var data = {
				security: wc_stripe_payment_request_params.nonce.payment
			};

			$.ajax( {
				type:    'POST',
				data:    data,
				url:     wc_stripe_payment_request.getAjaxURL( 'get_cart_details' ),
				success: function( response ) {
					wc_stripe_payment_request.startPaymentRequest( response );
				}
			} );
		},

		getAttributes: function() {
			var select = $( '.variations_form' ).find( '.variations select' ),
				data   = {},
				count  = 0,
				chosen = 0;

			select.each( function() {
				var attribute_name = $( this ).data( 'attribute_name' ) || $( this ).attr( 'name' );
				var value          = $( this ).val() || '';

				if ( value.length > 0 ) {
					chosen ++;
				}

				count ++;
				data[ attribute_name ] = value;
			});

			return {
				'count'      : count,
				'chosenCount': chosen,
				'data'       : data
			};			
		},

		processSource: function( source, paymentRequestType ) {
			var data = wc_stripe_payment_request.getOrderData( source, paymentRequestType );

			return $.ajax( {
				type:    'POST',
				data:    data,
				dataType: 'json',
				url:     wc_stripe_payment_request.getAjaxURL( 'create_order' )
			} );
		},

		/**
		 * Get order data.
		 *
		 * @since 3.1.0
		 * @version 4.0.0
		 * @param {PaymentResponse} source Payment Response instance.
		 *
		 * @return {Object}
		 */
		getOrderData: function( evt, paymentRequestType ) {
			var source   = evt.source;
			var email    = source.owner.email;
			var phone    = source.owner.phone;
			var billing  = source.owner.address;
			var name     = source.owner.name;
			var shipping = evt.shippingAddress;
			var data     = {
				_wpnonce:                  wc_stripe_payment_request_params.nonce.checkout,
				billing_first_name:        null !== name ? name.split( ' ' ).slice( 0, 1 ).join( ' ' ) : '',
				billing_last_name:         null !== name ? name.split( ' ' ).slice( 1 ).join( ' ' ) : '',
				billing_company:           '',
				billing_email:             null !== email   ? email : evt.payerEmail,
				billing_phone:             null !== phone   ? phone : evt.payerPhone.replace( '/[() -]/g', '' ),
				billing_country:           null !== billing ? billing.country : '',
				billing_address_1:         null !== billing ? billing.line1 : '',
				billing_address_2:         null !== billing ? billing.line2 : '',
				billing_city:              null !== billing ? billing.city : '',
				billing_state:             null !== billing ? billing.state : '',
				billing_postcode:          null !== billing ? billing.postal_code : '',
				shipping_first_name:       '',
				shipping_last_name:        '',
				shipping_company:          '',
				shipping_country:          '',
				shipping_address_1:        '',
				shipping_address_2:        '',
				shipping_city:             '',
				shipping_state:            '',
				shipping_postcode:         '',
				shipping_method:           [ null === evt.shippingOption ? null : evt.shippingOption.id ],
				order_comments:            '',
				payment_method:            'stripe',
				ship_to_different_address: 1,
				terms:                     1,
				stripe_source:             source.id,
				payment_request_type:      paymentRequestType
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
		 * Generate error message HTML.
		 *
		 * @since 3.1.0
		 * @version 4.0.0
		 * @param  {String} message Error message.
		 * @return {Object}
		 */
		getErrorMessageHTML: function( message ) {
			return $( '<div class="woocommerce-error" />' ).text( message );
		},

		/**
		 * Abort payment and display error messages.
		 *
		 * @since 3.1.0
		 * @version 4.0.0
		 * @param {PaymentResponse} payment Payment response instance.
		 * @param {String}          message Error message to display.
		 */
		abortPayment: function( payment, message ) {
			payment.complete( 'fail' );

			$( '.woocommerce-error' ).remove();

			if ( wc_stripe_payment_request_params.is_product_page ) {
				var element = $( '.product' );

				element.before( message );

				$( 'html, body' ).animate({
					scrollTop: element.prev( '.woocommerce-error' ).offset().top
				}, 600 );
			} else {
				var $form = $( '.shop_table.cart' ).closest( 'form' );

				$form.before( message );

				$( 'html, body' ).animate({
					scrollTop: $form.prev( '.woocommerce-error' ).offset().top
				}, 600 );
			}
		},

		/**
		 * Complete payment.
		 *
		 * @since 3.1.0
		 * @version 4.0.0
		 * @param {PaymentResponse} payment Payment response instance.
		 * @param {String}          url     Order thank you page URL.
		 */
		completePayment: function( payment, url ) {
			wc_stripe_payment_request.block();

			payment.complete( 'success' );

			// Success, then redirect to the Thank You page.
			window.location = url;
		},

		block: function() {
			$.blockUI( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			} );
		},

		/**
		 * Update shipping options.
		 *
		 * @param {Object}         details Payment details.
		 * @param {PaymentAddress} address Shipping address.
		 */
		updateShippingOptions: function( details, address ) {
			var data = {
				security:  wc_stripe_payment_request_params.nonce.shipping,
				country:   address.country,
				state:     address.region,
				postcode:  address.postalCode,
				city:      address.city,
				address:   typeof address.addressLine[0] === 'undefined' ? '' : address.addressLine[0],
				address_2: typeof address.addressLine[1] === 'undefined' ? '' : address.addressLine[1],
				payment_request_type: paymentRequestType
			};

			return $.ajax( {
				type:    'POST',
				data:    data,
				url:     wc_stripe_payment_request.getAjaxURL( 'get_shipping_options' )
			} );
		},

		/**
		 * Updates the shipping price and the total based on the shipping option.
		 *
		 * @param {Object}   details        The line items and shipping options.
		 * @param {String}   shippingOption User's preferred shipping option to use for shipping price calculations.
		 */
		updateShippingDetails: function( details, shippingOption ) {
			var data = {
				security: wc_stripe_payment_request_params.nonce.update_shipping,
				shipping_method: [ shippingOption.id ],
				payment_request_type: paymentRequestType
			};

			return $.ajax( {
				type: 'POST',
				data: data,
				url:  wc_stripe_payment_request.getAjaxURL( 'update_shipping_method' )
			} );
		},

		/**
		 * Adds the item to the cart and return cart details.
		 *
		 */
		addToCart: function() {
			var product_id = $( '.single_add_to_cart_button' ).val();

			// Check if product is a variable product.
			if ( $( '.single_variation_wrap' ).length ) {
				product_id = $( '.single_variation_wrap' ).find( 'input[name="product_id"]' ).val();
			}

			var data = {
				security: wc_stripe_payment_request_params.nonce.add_to_cart,
				product_id: product_id,
				qty: $( '.quantity .qty' ).val(),
				attributes: $( '.variations_form' ).length ? wc_stripe_payment_request.getAttributes().data : []
			};

			return $.ajax( {
				type: 'POST',
				data: data,
				url:  wc_stripe_payment_request.getAjaxURL( 'add_to_cart' )
			} );
		},

		clearCart: function() {
			var data = {
					'security': wc_stripe_payment_request_params.nonce.clear_cart
				};

			return $.ajax( {
				type:    'POST',
				data:    data,
				url:     wc_stripe_payment_request.getAjaxURL( 'clear_cart' ),
				success: function( response ) {}
			} );
		},

		getRequestOptionsFromLocal: function() {
			return {
				total: wc_stripe_payment_request_params.product.total,
				currency: wc_stripe_payment_request_params.checkout.currency_code,
				country: wc_stripe_payment_request_params.checkout.country_code,
				requestPayerName: true,
				requestPayerEmail: true,
				requestPayerPhone: true,
				requestShipping: wc_stripe_payment_request_params.product.requestShipping,
				displayItems: wc_stripe_payment_request_params.product.displayItems
			};
		},

		/**
		 * Starts the payment request
		 *
		 * @since 4.0.0
		 * @version 4.0.0
		 */
		startPaymentRequest: function( cart ) {
			var paymentDetails,
				options;

			if ( wc_stripe_payment_request_params.is_product_page ) {
				options = wc_stripe_payment_request.getRequestOptionsFromLocal();

				paymentDetails = options;
			} else {
				options = {
					total: cart.order_data.total,
					currency: cart.order_data.currency,
					country: cart.order_data.country_code,
					requestPayerName: true,
					requestPayerEmail: true,
					requestPayerPhone: true,
					requestShipping: cart.shipping_required ? true : false,
					displayItems: cart.order_data.displayItems
				};

				paymentDetails = cart.order_data;
			}

			var paymentRequest = stripe.paymentRequest( options );

			var elements = stripe.elements( { locale: wc_stripe_payment_request_params.button.locale } );
			var prButton = elements.create( 'paymentRequestButton', {
				paymentRequest: paymentRequest,
				style: {
					paymentRequestButton: {
						type: wc_stripe_payment_request_params.button.type,
						theme: wc_stripe_payment_request_params.button.theme,
						height: wc_stripe_payment_request_params.button.height + 'px'
					},
				}
			} );

			// Check the availability of the Payment Request API first.
			paymentRequest.canMakePayment().then( function( result ) {
				var paymentRequestError = [];

				if ( result ) {
					paymentRequestType = result.applePay ? 'apple_pay' : 'payment_request_api';

					if ( wc_stripe_payment_request_params.is_product_page ) {
						var addToCartButton = $( '.single_add_to_cart_button' );

						prButton.on( 'click', function( e ) {
							// First check if product can be added to cart.
							if ( addToCartButton.is( '.disabled' ) ) {
								e.preventDefault();
								if ( addToCartButton.is( '.wc-variation-is-unavailable' ) ) {
									window.alert( wc_add_to_cart_variation_params.i18n_unavailable_text );
								} else if ( addToCartButton.is( '.wc-variation-selection-needed' ) ) {
									window.alert( wc_add_to_cart_variation_params.i18n_make_a_selection_text );
								}
							} else if ( 0 < paymentRequestError.length ) {
								e.preventDefault();
								window.alert( paymentRequestError );
							} else {
								wc_stripe_payment_request.addToCart();
							}
						} );

						$( document.body ).on( 'woocommerce_variation_has_changed', function() {
							$( '#wc-stripe-payment-request-button' ).block( { message: null } );

							$.when( wc_stripe_payment_request.getSelectedProductData() ).then( function( response ) {
								$.when( paymentRequest.update( {
									total: response.total,
									displayItems: response.displayItems
								} ) ).then( function() {
									$( '#wc-stripe-payment-request-button' ).unblock();
								} );
							} );
						} );

						$( '.quantity' ).on( 'keyup', '.qty', function() {
							$( '#wc-stripe-payment-request-button' ).block( { message: null } );
							paymentRequestError = [];

							$.when( wc_stripe_payment_request.getSelectedProductData() ).then( function( response ) {
								if ( response.error ) {
									paymentRequestError = [ response.error ];
									$( '#wc-stripe-payment-request-button' ).unblock();
								} else {
									$.when( paymentRequest.update( {
										total: response.total,
										displayItems: response.displayItems
									} ) ).then( function() {
										$( '#wc-stripe-payment-request-button' ).unblock();
									} );
								}
							} );
						} );
					}

					if ( $( '#wc-stripe-payment-request-button' ).length ) {
						prButton.mount( '#wc-stripe-payment-request-button' );
						$( '#wc-stripe-payment-request-button-separator' ).show();
					}
				} else {
					$( '#wc-stripe-payment-request-button' ).hide();
					$( '#wc-stripe-payment-request-button-separator' ).hide();
				}
			} );

			// Possible statuses success, fail, invalid_payer_name, invalid_payer_email, invalid_payer_phone, invalid_shipping_address.
			paymentRequest.on( 'shippingaddresschange', function( evt ) {
				$.when( wc_stripe_payment_request.updateShippingOptions( paymentDetails, evt.shippingAddress ) ).then( function( response ) {
					evt.updateWith( { status: response.result, shippingOptions: response.shipping_options, total: response.total, displayItems: response.displayItems } );
				} );
			} );

			paymentRequest.on( 'shippingoptionchange', function( evt ) {
				$.when( wc_stripe_payment_request.updateShippingDetails( paymentDetails, evt.shippingOption ) ).then( function( response ) {
					if ( 'success' === response.result ) {
						evt.updateWith( { status: 'success', total: response.total, displayItems: response.displayItems } );
					}

					if ( 'fail' === response.result ) {
						evt.updateWith( { status: 'fail' } );
					}
				} );												
			} );

			paymentRequest.on( 'source', function( evt ) {
				// Check if we allow prepaid cards.
				if ( 'no' === wc_stripe_payment_request_params.stripe.allow_prepaid_card && 'prepaid' === evt.source.card.funding ) {
					wc_stripe_payment_request.abortPayment( evt, wc_stripe_payment_request.getErrorMessageHTML( wc_stripe_payment_request_params.i18n.no_prepaid_card ) );
				} else {
					$.when( wc_stripe_payment_request.processSource( evt, paymentRequestType ) ).then( function( response ) {
						if ( 'success' === response.result ) {
							wc_stripe_payment_request.completePayment( evt, response.redirect );
						} else {
							wc_stripe_payment_request.abortPayment( evt, response.messages );
						}
					} );
				}
			} );
		},

		getSelectedProductData: function() {
			var product_id = $( '.single_add_to_cart_button' ).val();

			// Check if product is a variable product.
			if ( $( '.single_variation_wrap' ).length ) {
				product_id = $( '.single_variation_wrap' ).find( 'input[name="product_id"]' ).val();
			}

			var data = {
				security: wc_stripe_payment_request_params.nonce.get_selected_product_data,
				product_id: product_id,
				qty: $( '.quantity .qty' ).val(),
				attributes: $( '.variations_form' ).length ? wc_stripe_payment_request.getAttributes().data : []
			};

			return $.ajax( {
				type: 'POST',
				data: data,
				url:  wc_stripe_payment_request.getAjaxURL( 'get_selected_product_data' )
			} );
		},

		/**
		 * Initialize event handlers and UI state
		 *
		 * @since 4.0.0
		 * @version 4.0.0
		 */
		init: function() {
			if ( wc_stripe_payment_request_params.is_product_page ) {
				wc_stripe_payment_request.startPaymentRequest( '' );
			} else {
				wc_stripe_payment_request.getCartDetails();
			}

		},
	};

	wc_stripe_payment_request.init();

	// We need to refresh payment request data when total is updated.
	$( document.body ).on( 'updated_cart_totals', function() {
		wc_stripe_payment_request.init();
	} );

	// We need to refresh payment request data when total is updated.
	$( document.body ).on( 'updated_checkout', function() {
		wc_stripe_payment_request.init();
	} );
} );
