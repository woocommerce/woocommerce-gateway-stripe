/* global wc_stripe_payment_request_params, Stripe */
jQuery( function( $ ) {
	'use strict';

	var stripe = Stripe( wc_stripe_payment_request_params.stripe.key, {
		locale: wc_stripe_payment_request_params.stripe.locale
	} ),
		paymentRequestType,
		showButtonsOnInit = wc_stripe_payment_request_params.product.validVariationSelected ?? true;

	/**
	 * Object to handle Stripe payment forms.
	 */
	var wc_stripe_payment_request = {
		/**
		 * Whether the payment request window was canceled/dismissed by the customer.
		 */
		paymentCanceled: false,

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

		processPaymentMethod: function( paymentMethod, paymentRequestType ) {
			var data = wc_stripe_payment_request.getOrderData( paymentMethod, paymentRequestType );

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
		 * @param {Object} evt The event object containing payment details and user information.
		 * @param {string} paymentRequestType The type of payment request, either 'google_pay' or 'apple_pay'.
		 *
		 * @return {Object} Processed order data for further handling, including shipping, billing, and payment method information.
		 */
		getOrderData: function( evt, paymentRequestType ) {
			var paymentMethod = evt.paymentMethod;
			var email         = paymentMethod.billing_details.email;
			var phone         = paymentMethod.billing_details.phone;
			var billing       = paymentMethod.billing_details.address;
			var name          = paymentMethod.billing_details.name ?? evt.payerName;
			var shipping      = evt.shippingAddress;
			var data          = {
				_wpnonce:                       wc_stripe_payment_request_params.nonce.checkout,
				billing_first_name:             name?.split( ' ' )?.slice( 0, 1 )?.join( ' ' ) ?? '',
				billing_last_name:              name?.split( ' ' )?.slice( 1 )?.join( ' ' ) || '-',
				billing_company:                '',
				billing_email:                  null !== email   ? email : evt.payerEmail,
				billing_phone:                  null !== phone   ? phone : evt.payerPhone && evt.payerPhone.replace( '/[() -]/g', '' ),
				billing_country:                null !== billing ? billing.country : '',
				billing_address_1:              null !== billing ? billing.line1 : '',
				billing_address_2:              null !== billing ? billing.line2 : '',
				billing_city:                   null !== billing ? billing.city : '',
				billing_state:                  null !== billing ? billing.state : '',
				billing_postcode:               null !== billing ? billing.postal_code : '',
				shipping_first_name:            '',
				shipping_last_name:             '',
				shipping_company:               '',
				shipping_country:               '',
				shipping_address_1:             '',
				shipping_address_2:             '',
				shipping_city:                  '',
				shipping_state:                 '',
				shipping_postcode:              '',
				shipping_method:                [ null === evt.shippingOption ? null : evt.shippingOption.id ],
				order_comments:                 '',
				payment_method:                 'stripe',
				ship_to_different_address:      1,
				terms:                          1,
				'wc-stripe-payment-method':     paymentMethod.id,
				stripe_source:                  paymentMethod.id, // Needed to process the payment in legacy checkout mode.
				payment_request_type:           paymentRequestType,
				'wc-stripe-is-deferred-intent': true,
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

			data = wc_stripe_payment_request.getRequiredFieldDataFromCheckoutForm( data );

			return { ...data, ...wc_stripe_payment_request.extractOrderAttributionData() };
		},

		/**
		 * Get required field values from the checkout form if they are filled and add to the order data.
		 *
		 * @param {Object} data Order data.
		 *
		 * @return {Object}
		 */
		getRequiredFieldDataFromCheckoutForm: function( data ) {
			const requiredfields = $( 'form.checkout' ).find( '.validate-required' );

			if ( requiredfields.length ) {
				requiredfields.each( function() {
					const field = $( this ).find( ':input' );
					const name = field.attr( 'name' );

					let value = '';
					if ( field.attr( 'type' ) === 'checkbox' ) {
						value = field.is( ':checked' );
					} else {
						value = field.val();
					}

					if ( value && name ) {
						if ( ! data[ name ] ) {
							data[ name ] = value;
						}

						// if shipping same as billing is selected, copy the billing field to shipping field.
						const shipToDiffAddress = $( '#ship-to-different-address' ).find( 'input' ).is( ':checked' );
						if ( ! shipToDiffAddress ) {
							var shippingFieldName = name.replace( 'billing_', 'shipping_' );
							if ( ! data[ shippingFieldName ] && data[ name ] ) {
								data[ shippingFieldName ] = data[ name ];
							}
						}
					}
				});
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
		 * Display error messages.
		 *
		 * @since 4.8.0
		 * @param {Object} message DOM object with error message to display.
		 */
		displayErrorMessage: function( message ) {
			$( '.woocommerce-error' ).remove();

			const $prependErrorMessageTo = wc_stripe_payment_request_params.is_product_page ?
				$( '.product' ).first() :
				$( '.shop_table' ).closest( 'form' );

			// Couldn't found the element to which prepend the error. This shouldn't happen.
			if ( ! $prependErrorMessageTo.length ) {
				// But log the error so there's at least some indication of what's going on.
				console.error( 'Could not prepend the error message element:', message );
				return;
			}

			$prependErrorMessageTo.before( message );

			$( 'html, body' ).animate({
				scrollTop: $prependErrorMessageTo.prev( '.woocommerce-error' ).offset().top
			}, 600 );
		},

		/**
		 * Abort payment and display error messages.
		 *
		 * @since 3.1.0
		 * @version 4.8.0
		 * @param {PaymentResponse} payment Payment response instance.
		 * @param {Object}          message DOM object with error message to display.
		 */
		abortPayment: function( payment, message ) {
			payment.complete( 'fail' );
			wc_stripe_payment_request.displayErrorMessage( message );
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
				payment_request_type: paymentRequestType,
				is_product_page: wc_stripe_payment_request_params.is_product_page,
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
				payment_request_type: paymentRequestType,
				is_product_page: wc_stripe_payment_request_params.is_product_page,
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

			// add addons data to the POST body
			var formData = $( 'form.cart' ).serializeArray();
			$.each( formData, function( i, field ) {
				if ( /^addon-/.test( field.name ) ) {
					if ( /\[\]$/.test( field.name ) ) {
						var fieldName = field.name.substring( 0, field.name.length - 2);
						if ( data[ fieldName ] ) {
							data[ fieldName ].push( field.value );
						} else {
							data[ fieldName ] = [ field.value ];
						}
					} else {
						data[ field.name ] = field.value;
					}
				}
			} );

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
				requestPayerPhone: wc_stripe_payment_request_params.checkout.needs_payer_phone,
				requestShipping: wc_stripe_payment_request_params.product.requestShipping,
				displayItems: wc_stripe_payment_request_params.product.displayItems
			};
		},

		/**
		 * Starts the payment request
		 *
		 * @since 4.0.0
		 * @version 4.8.0
		 */
		startPaymentRequest: function( cart, showButtonsOnInit ) {
			var paymentDetails,
				options;

			// Whether to show the payment request buttons on init. Set to true by default.
			showButtonsOnInit = showButtonsOnInit ?? true;

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
					requestPayerPhone: wc_stripe_payment_request_params.checkout.needs_payer_phone,
					requestShipping: cart.shipping_required ? true : false,
					displayItems: cart.order_data.displayItems
				};

				paymentDetails = cart.order_data;
			}

			const disableWallets = [];

			// Prevent displaying Link in the PRBs if disabled in the plugin settings.
			if ( ! wc_stripe_payment_request_params?.stripe?.is_link_enabled ) {
				disableWallets.push( 'link' );
			}

			// Prevent displaying Apple Pay and Google Pay in the PRBs if disabled in the plugin settings.
			if ( ! wc_stripe_payment_request_params?.stripe?.is_payment_request_enabled ) {
				disableWallets.push( 'applePay', 'googlePay' );
			}

			options.disableWallets = disableWallets;

			// Puerto Rico (PR) is the only US territory/possession that's supported by Stripe.
			// Since it's considered a US state by Stripe, we need to do some special mapping.
			if ( 'PR' === options.country ) {
				options.country = 'US';
			}

			// Handle errors thrown by Stripe, so we don't break the product page
			try {
				var paymentRequest = stripe.paymentRequest( options );

				var elements = stripe.elements( { locale: wc_stripe_payment_request_params.button.locale } );
				var prButton = wc_stripe_payment_request.createPaymentRequestButton( elements, paymentRequest );

				// Check the availability of the Payment Request API first.
				paymentRequest.canMakePayment().then( function( result ) {
					if ( ! result ) {
						return;
					}

					if ( result.applePay ) {
						paymentRequestType = 'apple_pay';
					} else if ( result.googlePay ) {
						paymentRequestType = 'google_pay';
					} else {
						paymentRequestType = 'payment_request_api';
					}

					wc_stripe_payment_request.attachPaymentRequestButtonEventListeners( prButton, paymentRequest );

					if ( showButtonsOnInit ) {
						wc_stripe_payment_request.showPaymentRequestButton( prButton );
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

				paymentRequest.on( 'paymentmethod', function( evt ) {
					// Check if we allow prepaid cards.
					if ( 'no' === wc_stripe_payment_request_params.stripe.allow_prepaid_card && 'prepaid' === evt.source.card.funding ) {
						wc_stripe_payment_request.abortPayment( evt, wc_stripe_payment_request.getErrorMessageHTML( wc_stripe_payment_request_params.i18n.no_prepaid_card ) );
					} else {
						$.when( wc_stripe_payment_request.processPaymentMethod( evt, paymentRequestType ) ).then( function( response ) {
							if ( 'success' === response.result ) {
								wc_stripe_payment_request.completePayment( evt, response.redirect );
							} else {
								wc_stripe_payment_request.abortPayment( evt, response.messages );
							}
						} );
					}
				} );

				paymentRequest.on( 'cancel', function() {
					/**
					 * If the customer closes the Payment Request window set paymentCanceled to true.
					 *
					 * This helps us determine whether we need to rebuild the payment request UI after it's been closed. This is to fix issues like the
					 * 'shippingaddresschange' event not triggering when the customer closes the Google Pay/Apple Pay window and chooses a different variation product.
					 */
					wc_stripe_payment_request.paymentCanceled = true;
				} );
			} catch( e ) {
				// Leave for troubleshooting
				console.error( e );
			}
		},

		getSelectedProductData: function() {
			var product_id = $( '.single_add_to_cart_button' ).val();

			// Check if product is a variable product.
			if ( $( '.single_variation_wrap' ).length ) {
				product_id = $( '.single_variation_wrap' ).find( 'input[name="product_id"]' ).val();
			}

			var addons = $( '#product-addons-total' ).data('price_data') || [];
			var addon_value = addons.reduce( function ( sum, addon ) { return sum + addon.cost; }, 0 );

			var data = {
				security: wc_stripe_payment_request_params.nonce.get_selected_product_data,
				product_id: product_id,
				qty: $( '.quantity .qty' ).val(),
				attributes: $( '.variations_form' ).length ? wc_stripe_payment_request.getAttributes().data : [],
				addon_value: addon_value,
			};

			return $.ajax( {
				type: 'POST',
				data: data,
				url:  wc_stripe_payment_request.getAjaxURL( 'get_selected_product_data' )
			} );
		},

		/**
		 * Creates a wrapper around a function that ensures a function can not
		 * called in rappid succesion. The function can only be executed once and then agin after
		 * the wait time has expired.  Even if the wrapper is called multiple times, the wrapped
		 * function only excecutes once and then blocks until the wait time expires.
		 *
		 * @param {int} wait       Milliseconds wait for the next time a function can be executed.
		 * @param {function} func       The function to be wrapped.
		 * @param {bool} immediate Overriding the wait time, will force the function to fire everytime.
		 *
		 * @return {function} A wrapped function with execution limited by the wait time.
		 */
		debounce: function( wait, func, immediate ) {
			var timeout;
			return function() {
				var context = this, args = arguments;
				var later = function() {
					timeout = null;
					if (!immediate) func.apply(context, args);
				};
				var callNow = immediate && !timeout;
				clearTimeout(timeout);
				timeout = setTimeout(later, wait);
				if (callNow) func.apply(context, args);
			};
		},

		/**
		 * Creates stripe paymentRequest element or connects to custom button
		 *
		 * @param {object} elements       Stripe elements instance.
		 * @param {object} paymentRequest Stripe paymentRequest object.
		 *
		 * @return {object} Stripe paymentRequest element or custom button jQuery element.
		 */
		createPaymentRequestButton: function( elements, paymentRequest ) {
			var button;
			if ( wc_stripe_payment_request_params.button.is_custom ) {
				button = $( wc_stripe_payment_request_params.button.css_selector );
				if ( button.length ) {
					// We fallback to default paymentRequest button if no custom button is found in the UI.
					// Add flag to be sure that created button is custom button rather than fallback element.
					button.data( 'isCustom', true );
					return button;
				}
			}

			if ( wc_stripe_payment_request_params.button.is_branded ) {
				if ( wc_stripe_payment_request.shouldUseGooglePayBrand() ) {
					button = wc_stripe_payment_request.createGooglePayButton();
					// Add flag to be sure that created button is branded rather than fallback element.
					button.data( 'isBranded', true );
					return button;
				} else {
					// Not implemented branded buttons default to Stripe's button
					// Apple Pay buttons can also fall back to Stripe's button, as it's already branded
					// Set button type to default or buy, depending on branded type, to avoid issues with Stripe
					wc_stripe_payment_request_params.button.type = 'long' === wc_stripe_payment_request_params.button.branded_type ? 'buy' : 'default';
				}
			}

			return elements.create( 'paymentRequestButton', {
				paymentRequest: paymentRequest,
				style: {
					paymentRequestButton: {
						type: wc_stripe_payment_request_params.button.type,
						theme: wc_stripe_payment_request_params.button.theme,
						height: wc_stripe_payment_request_params.button.height + 'px',
					},
				},
			} );
		},

		/**
		 * Checks if button is custom payment request button.
		 *
		 * @param {object} prButton Stripe paymentRequest element or custom jQuery element.
		 *
		 * @return {boolean} True when prButton is custom button jQuery element.
		 */
		isCustomPaymentRequestButton: function ( prButton ) {
			return prButton && 'function' === typeof prButton.data && prButton.data( 'isCustom' );
		},

		isBrandedPaymentRequestButton: function ( prButton ) {
			return prButton && 'function' === typeof prButton.data && prButton.data( 'isBranded' );
		},

		shouldUseGooglePayBrand: function () {
			var ua = window.navigator.userAgent.toLowerCase();
			var isChrome = /chrome/.test( ua ) && ! /edge|edg|opr|brave\//.test( ua ) && 'Google Inc.' === window.navigator.vendor;
			// newer versions of Brave do not have the userAgent string
			var isBrave = isChrome && window.navigator.brave;
			return isChrome && ! isBrave;
		},

		createGooglePayButton: function () {
			var allowedThemes = [ 'dark', 'light', 'light-outline' ];
			var allowedTypes = [ 'short', 'long' ];

			var theme  = wc_stripe_payment_request_params.button.theme;
			var type   = wc_stripe_payment_request_params.button.branded_type;
			var locale = wc_stripe_payment_request_params.button.locale;
			var height = wc_stripe_payment_request_params.button.height;
			theme = allowedThemes.includes( theme ) ? theme : 'light';
			var gpaySvgTheme = 'dark' === theme ? 'dark' : 'light';
			type = allowedTypes.includes( type ) ? type : 'long';

			var button = $( '<button type="button" id="wc-stripe-branded-button" aria-label="Google Pay" class="gpay-button"></button>' );
			button.css( 'height', height + 'px' );
			button.addClass( theme + ' ' + type );
			if ( 'long' === type ) {
				var url = 'https://www.gstatic.com/instantbuy/svg/' + gpaySvgTheme + '/' + locale + '.svg';
				var fallbackUrl = 'https://www.gstatic.com/instantbuy/svg/' + gpaySvgTheme + '/en.svg';
				// Check if locale GPay button exists, default to en if not
				setBackgroundImageWithFallback( button, url, fallbackUrl );
			}

			return button;
		},

		attachPaymentRequestButtonEventListeners: function( prButton, paymentRequest ) {
			// First, mark the body so we know a payment request button was used.
			// This way error handling can any display errors in the most appropriate place.
			prButton.on( 'click', function ( evt ) {
				$( 'body' ).addClass( 'woocommerce-stripe-prb-clicked' );
			});

			// Then, attach specific handling for selected pages and button types
			if ( wc_stripe_payment_request_params.is_product_page ) {
				wc_stripe_payment_request.attachProductPageEventListeners( prButton, paymentRequest );
			} else {
				wc_stripe_payment_request.attachCartPageEventListeners( prButton, paymentRequest );
			}
		},

		attachProductPageEventListeners: function( prButton, paymentRequest ) {
			var paymentRequestError = [];
			var addToCartButton = $( '.single_add_to_cart_button' );

			prButton.on( 'click', function ( evt ) {
				// If login is required for checkout, display redirect confirmation dialog.
				if ( wc_stripe_payment_request_params.login_confirmation ) {
					evt.preventDefault();
					displayLoginConfirmation( paymentRequestType );
					return;
				}

				// First check if product can be added to cart.
				if ( addToCartButton.is( '.disabled' ) ) {
					evt.preventDefault(); // Prevent showing payment request modal.
					if ( addToCartButton.is( '.wc-variation-is-unavailable' ) ) {
						window.alert( wc_add_to_cart_variation_params.i18n_unavailable_text );
					} else if ( addToCartButton.is( '.wc-variation-selection-needed' ) ) {
						window.alert( wc_add_to_cart_variation_params.i18n_make_a_selection_text );
					}
					return;
				}

				if ( 0 < paymentRequestError.length ) {
					evt.preventDefault();
					window.alert( paymentRequestError );
					return;
				}

				wc_stripe_payment_request.addToCart();

				if ( wc_stripe_payment_request.isCustomPaymentRequestButton( prButton ) || wc_stripe_payment_request.isBrandedPaymentRequestButton( prButton ) ) {
					evt.preventDefault();
					paymentRequest.show();
				}
			});

			$( document.body ).on( 'wc_stripe_unblock_payment_request_button wc_stripe_enable_payment_request_button', function () {
				wc_stripe_payment_request.unblockPaymentRequestButton();
			} );

			$( document.body ).on( 'wc_stripe_block_payment_request_button', function () {
				wc_stripe_payment_request.blockPaymentRequestButton( 'wc_request_button_is_blocked' );
			} );

			$( document.body ).on( 'wc_stripe_disable_payment_request_button', function () {
				wc_stripe_payment_request.blockPaymentRequestButton( 'wc_request_button_is_disabled' );
			} );

			$( document.body ).on( 'woocommerce_variation_has_changed', function () {
				$( document.body ).trigger( 'wc_stripe_block_payment_request_button' );

				$.when( wc_stripe_payment_request.getSelectedProductData() ).then( function ( response ) {
					if ( response.error ) {
						$( document.body ).trigger( 'wc_stripe_unblock_payment_request_button' );
						wc_stripe_payment_request.hidePaymentRequestButton();
					} else {
						/**
						 * If the customer canceled the payment request, we need to re-init the payment request buttons to ensure the shipping
						 * options are fetched again. If the customer didn't close the payment request, and the product's shipping status is
						 * consistent, we can simply update the payment request button with the new total and display items.
						 */
						if ( ! wc_stripe_payment_request.paymentCanceled && wc_stripe_payment_request_params.product.requestShipping === response.requestShipping ) {
							$.when(
								paymentRequest.update( {
									total: response.total,
									displayItems: response.displayItems,
								} )
							).then( function () {
								$( document.body ).trigger( 'wc_stripe_unblock_payment_request_button' );
								wc_stripe_payment_request.showPaymentRequestButton();
							} );
						} else {
							/**
							 * Re init the payment request button.
							 *
							 * This ensures that when the customer clicks on the payment button, the available shipping options are
							 * refetched based on the selected variable product's data and the chosen address.
							 */
							wc_stripe_payment_request_params.product.requestShipping = response.requestShipping;
							wc_stripe_payment_request_params.product.total           = response.total;
							wc_stripe_payment_request_params.product.displayItems    = response.displayItems;

							wc_stripe_payment_request.init();
							$( document.body ).trigger( 'wc_stripe_unblock_payment_request_button' );
						}
					}
				});
			});

			const blockPaymentRequestButton = function () {
				$( document.body ).trigger( 'wc_stripe_block_payment_request_button' );
			}

			const cartChangedHandler = function () {
				$(document.body).trigger('wc_stripe_block_payment_request_button');
				paymentRequestError = [];

				$.when(wc_stripe_payment_request.getSelectedProductData()).then(function (response) {
					if (response.error) {
						paymentRequestError = [response.error];
						$(document.body).trigger('wc_stripe_unblock_payment_request_button');
					} else {
						$.when(
							paymentRequest.update({
								total: response.total,
								displayItems: response.displayItems,
							})
						).then(function () {
							$(document.body).trigger('wc_stripe_unblock_payment_request_button');
						});
					}
				});
			};

			// Block the payment request button as soon as an "input" event is fired, to avoid sync issues
			// when the customer clicks on the button before the debounced event is processed.
			$( '.quantity' ).on( 'input', '.qty', blockPaymentRequestButton );
			$( '.quantity' ).on('input', '.qty', wc_stripe_payment_request.debounce(250, cartChangedHandler));

			// Update payment request buttons if product add-ons are modified.
			$( '.cart:not(.cart_group)' ).on( 'updated_addons', blockPaymentRequestButton );
			$( '.cart:not(.cart_group)' ).on( 'updated_addons', wc_stripe_payment_request.debounce( 250, cartChangedHandler ));

			if ( $('.variations_form').length ) {
				$( '.variations_form' ).on( 'found_variation.wc-variation-form', function ( evt, variation ) {
					if ( variation.is_in_stock ) {
						wc_stripe_payment_request.unhidePaymentRequestButton();
					} else {
						wc_stripe_payment_request.hidePaymentRequestButton();
					}
				} );
			}
		},

		attachCartPageEventListeners: function ( prButton, paymentRequest ) {
			prButton.on( 'click', function ( evt ) {
				// If login is required for checkout, display redirect confirmation dialog.
				if ( wc_stripe_payment_request_params.login_confirmation ) {
					evt.preventDefault();
					displayLoginConfirmation( paymentRequestType );
					return;
				}

				if (
					wc_stripe_payment_request.isCustomPaymentRequestButton(
						prButton
					) ||
					wc_stripe_payment_request.isBrandedPaymentRequestButton(
						prButton
					)
				) {
					evt.preventDefault();
					paymentRequest.show();
				}
			} );
		},

		/**
		 * Get order attribution data from the hidden inputs.
		 *
		 * @return {Object} Order attribution data.
		 */
		extractOrderAttributionData: function() {
			const $orderAttributionWrapper = $( 'wc-order-attribution-inputs' );
			if ( ! $orderAttributionWrapper.length ) {
				return {};
			}

			const orderAttributionData = {};
			$orderAttributionWrapper.children( 'input' ).each( function () {
				orderAttributionData[ $(this).attr( 'name' ) ] = $(this).val();
			});
			return orderAttributionData;
		},

		showPaymentRequestButton: function( prButton ) {
			if ( wc_stripe_payment_request.isCustomPaymentRequestButton( prButton ) ) {
				prButton.addClass( 'is-active' );
				$( '#wc-stripe-payment-request-wrapper, #wc-stripe-payment-request-button-separator' ).show();
			} else if ( wc_stripe_payment_request.isBrandedPaymentRequestButton( prButton ) ) {
				$( '#wc-stripe-payment-request-wrapper, #wc-stripe-payment-request-button-separator' ).show();
				$( '#wc-stripe-payment-request-button' ).html( prButton );
			} else if ( $( '#wc-stripe-payment-request-button' ).length ) {
				$( '#wc-stripe-payment-request-wrapper, #wc-stripe-payment-request-button-separator' ).show();
				prButton.mount( '#wc-stripe-payment-request-button' );
			}
		},

		hidePaymentRequestButton: function () {
			$( '#wc-stripe-payment-request-wrapper, #wc-stripe-payment-request-button-separator' ).hide();
		},

		unhidePaymentRequestButton: function () {
			const stripe_wrapper = $( '#wc-stripe-payment-request-wrapper' );
			const stripe_separator = $( '#wc-stripe-payment-request-button-separator' );
			// If either element is hidden, ensure both show.
			if ( stripe_wrapper.is(':hidden') || stripe_separator.is(':hidden') ) {
				stripe_wrapper.show();
				stripe_separator.show();
			}
		},

		blockPaymentRequestButton: function( cssClassname ) {
			// check if element isn't already blocked before calling block() to avoid blinking overlay issues
			// blockUI.isBlocked is either undefined or 0 when element is not blocked
			if ( $( '#wc-stripe-payment-request-button' ).data( 'blockUI.isBlocked' ) ) {
				return;
			}

			$( '#wc-stripe-payment-request-button' )
				.addClass( cssClassname )
				.block( { message: null } );
		},

		unblockPaymentRequestButton: function() {
			$( '#wc-stripe-payment-request-button' )
				.removeClass( ['wc_request_button_is_blocked', 'wc_request_button_is_disabled'] )
				.unblock();
		},

		/**
		 * Initialize event handlers and UI state
		 *
		 * @since 4.0.0
		 * @version 4.0.0
		 * @param {boolean} showButtonsOnInit Whether to show the payment request buttons on init.
		 */
		init: function( showButtonsOnInit ) {
			if ( wc_stripe_payment_request_params.is_product_page ) {
				wc_stripe_payment_request.startPaymentRequest( '', showButtonsOnInit );
			} else {
				wc_stripe_payment_request.getCartDetails();
			}

			wc_stripe_payment_request.paymentCanceled = false;
		},
	};

	wc_stripe_payment_request.init( showButtonsOnInit );

	// We need to refresh payment request data when total is updated.
	$( document.body ).on( 'updated_cart_totals', function() {
		wc_stripe_payment_request.init();
	} );

	// We need to refresh payment request data when total is updated.
	$( document.body ).on( 'updated_checkout', function() {
		wc_stripe_payment_request.init();
	} );

	function setBackgroundImageWithFallback( element, background, fallback ) {
		element.css( 'background-image', 'url(' + background + ')' );
		// Need to use an img element to avoid CORS issues
		var testImg = document.createElement("img");
		testImg.onerror = function () {
			element.css( 'background-image', 'url(' + fallback + ')' );
		}
		testImg.src = background;
	}

	// TODO: Replace this by `client/blocks/payment-request/login-confirmation.js`
	// when we start using webpack to build this file.
	function displayLoginConfirmation( paymentRequestType ) {
		if ( ! wc_stripe_payment_request_params.login_confirmation ) {
			return;
		}

		var message = wc_stripe_payment_request_params.login_confirmation.message;

		// Replace dialog text with specific payment request type "Apple Pay" or "Google Pay".
		if ( 'payment_request_api' !== paymentRequestType ) {
			message = message.replace(
				/\*\*.*?\*\*/,
				'apple_pay' === paymentRequestType ? 'Apple Pay' : 'Google Pay'
			);
		}

		// Remove asterisks from string.
		message = message.replace( /\*\*/g, '' );

		if ( confirm( message ) ) {
			// Redirect to my account page.
			window.location.href = wc_stripe_payment_request_params.login_confirmation.redirect_url;
		}
	}
} );
