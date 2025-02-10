/* global wcStripeExpressCheckoutPayForOrderParams */
/* global wc_stripe_express_checkout_params */

import { __ } from '@wordpress/i18n';
import { debounce } from 'lodash';
import jQuery from 'jquery';
import WCStripeAPI from '../../api';
import {
	displayExpressCheckoutNotice,
	displayLoginConfirmation,
	getExpressCheckoutButtonAppearance,
	getExpressCheckoutButtonStyleSettings,
	getExpressCheckoutData,
	getPaymentMethodTypesForExpressMethod,
	isManualPaymentMethodCreation,
	normalizeLineItems,
} from 'wcstripe/express-checkout/utils';
import {
	onAbortPaymentHandler,
	onCancelHandler,
	onClickHandler,
	onCompletePaymentHandler,
	onConfirmHandler,
	onReadyHandler,
	shippingAddressChangeHandler,
	shippingRateChangeHandler,
} from 'wcstripe/express-checkout/event-handler';
import { getStripeServerData } from 'wcstripe/stripe-utils';
import { getAddToCartVariationParams } from 'wcstripe/utils';
import 'wcstripe/express-checkout/compatibility/wc-order-attribution';
import 'wcstripe/express-checkout/compatibility/wc-product-page';
import './styles.scss';
import {
	EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_LINK,
} from 'wcstripe/stripe-utils/constants';
import {
	transformCartDataForDisplayItems,
	transformLabeledDisplayItems,
	transformPrice,
} from 'wcstripe/express-checkout/transformers/wc-to-stripe';

jQuery( function ( $ ) {
	// Don't load if blocks checkout is being loaded.
	if (
		getExpressCheckoutData( 'has_block' ) &&
		! getExpressCheckoutData( 'is_pay_for_order' )
	) {
		return;
	}

	const publishableKey = getExpressCheckoutData( 'stripe' ).publishable_key;
	const quantityInputSelector = '.quantity .qty[type=number]';

	if ( ! publishableKey ) {
		// If no configuration is present, probably this is not the checkout page.
		return;
	}

	const api = new WCStripeAPI(
		getStripeServerData(),
		// A promise-based interface to jQuery.post.
		( url, args ) => {
			return new Promise( ( resolve, reject ) => {
				jQuery.post( url, args ).then( resolve ).fail( reject );
			} );
		}
	);

	let wcStripeECEError = '';
	const defaultErrorMessage = __(
		'There was an error getting the product information.',
		'woocommerce-gateway-stripe'
	);

	/**
	 * @todo Using the legacy endpoint (non-StoreAPI) and data format when variations are present.
	 * StoreAPI will support this form correctly only after WC 9.7.0.
	 * See https://github.com/woocommerce/woocommerce-gateway-stripe/pull/3780#issuecomment-2632051359
	 */
	const useLegacyCartEndpoints = $( '.variations_form' ).length > 0;

	const wcStripeECE = {
		createButton: ( elements, options ) =>
			elements.create( 'expressCheckout', options ),

		getElements: () => $( '#wc-stripe-express-checkout-element' ),

		getButtonSeparator: () =>
			$( '#wc-stripe-express-checkout-button-separator' ),

		show: () => wcStripeECE.getElements().show(),

		hide: () => {
			wcStripeECE.getElements().hide();
			wcStripeECE.getButtonSeparator().hide();
		},

		renderButton: ( eceButton, expressPaymentType ) => {
			if ( $( '#wc-stripe-express-checkout-element' ).length ) {
				const containerName = `wc-stripe-express-checkout-element-${ expressPaymentType }`;
				$( '#wc-stripe-express-checkout-element' ).append(
					`<div id="${ containerName }"></div>`
				);

				eceButton.mount( `#${ containerName }` );

				// If the express payment type, e.g. Apple Pay, is not available,
				// remove the container.
				eceButton.on( 'ready', ( { availablePaymentMethods } ) => {
					if ( ! availablePaymentMethods ) {
						$( `#${ containerName }` ).remove();
					}
				} );

				eceButton.on( 'loaderror', () => {
					$( `#${ containerName }` ).remove();
				} );
			}
		},

		productHasDepositOption() {
			return !! $( 'form' ).has(
				'input[name=wc_deposit_option],input[name=wc_deposit_payment_plan]'
			).length;
		},

		/**
		 * Starts the Express Checkout Element
		 *
		 * @param {Object} options ECE options.
		 */
		startExpressCheckout: ( options ) => {
			const getShippingRates = () => {
				if ( ! options.requestShipping ) {
					return [];
				}

				if ( getExpressCheckoutData( 'is_product_page' ) ) {
					return getExpressCheckoutData( 'product' )?.shippingOptions;
				}

				return options.displayItems
					.filter( ( i ) => i.key && i.key === 'total_shipping' )
					.map( ( i ) => ( {
						id: 'rate-shipping',
						amount: i.amount,
						displayName: useLegacyCartEndpoints ? i.label : i.name,
					} ) );
			};

			const shippingRates = getShippingRates();

			const isPaymentRequestEnabled =
				wc_stripe_express_checkout_params?.stripe // eslint-disable-line camelcase
					?.is_payment_request_enabled;
			const isAmazonPayEnabled =
				wc_stripe_express_checkout_params?.stripe // eslint-disable-line camelcase
					?.is_amazon_pay_enabled;
			const isLinkEnabled =
				wc_stripe_express_checkout_params?.stripe?.is_link_enabled; // eslint-disable-line camelcase

			// For each supported express payment type, create their own
			// express checkout element. This is necessary as some express payment types
			// may require different options or configurations, e.g. Amazon Pay
			// does not support paymentMethodCreation: 'manual'.
			const expressPaymentTypes = [
				isPaymentRequestEnabled &&
					EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
				isPaymentRequestEnabled &&
					EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY,
				isAmazonPayEnabled && EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY,
				isLinkEnabled && EXPRESS_PAYMENT_METHOD_SETTING_LINK,
			].filter( Boolean );

			expressPaymentTypes.forEach( ( expressPaymentType ) => {
				wcStripeECE.createExpressCheckoutElement( expressPaymentType, {
					...options,
					shippingRates,
				} );
			} );
		},

		createExpressCheckoutElement: ( expressPaymentType, options ) => {
			// This is a bit of a hack, but we need some way to get the shipping information before rendering the button, and
			// since we don't have any address information at this point it seems best to rely on what came with the cart response.
			// Relying on what's provided in the cart response seems safest since it should always include a valid shipping
			// rate if one is required and available.
			// If no shipping rate is found we can't render the button so we just exit.
			if ( options.requestShipping && ! options.shippingRates ) {
				return;
			}

			const elements = api.getStripe().elements( {
				mode: options.mode ? options.mode : 'payment',
				amount: options.total,
				currency: options.currency,
				...( isManualPaymentMethodCreation( expressPaymentType ) && {
					paymentMethodCreation: 'manual',
				} ),
				appearance: getExpressCheckoutButtonAppearance(),
				locale: getExpressCheckoutData( 'stripe' )?.locale ?? 'en',
				paymentMethodTypes: getPaymentMethodTypesForExpressMethod(
					expressPaymentType
				),
			} );

			const eceButton = wcStripeECE.createButton( elements, {
				...getExpressCheckoutButtonStyleSettings(),
				paymentMethods: {
					amazonPay:
						expressPaymentType ===
						EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY
							? 'auto'
							: 'never',
					googlePay:
						expressPaymentType ===
						EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY
							? 'always'
							: 'never',
					applePay:
						expressPaymentType ===
						EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY
							? 'always'
							: 'never',
					link: expressPaymentType === 'link' ? 'auto' : 'never',
				},
			} );

			wcStripeECE.renderButton( eceButton, expressPaymentType );

			eceButton.on( 'click', async function ( event ) {
				// If login is required for checkout, display redirect confirmation dialog.
				if ( getExpressCheckoutData( 'login_confirmation' ) ) {
					displayLoginConfirmation( event.expressPaymentType );
					return;
				}

				if ( getExpressCheckoutData( 'taxes_based_on_billing' ) ) {
					displayExpressCheckoutNotice(
						__(
							'Final taxes charged can differ based on your actual billing address when using Express Checkout buttons (Link, Google Pay or Apple Pay).',
							'woocommerce-gateway-stripe'
						),
						'info',
						[ 'ece-taxes-info' ]
					);
				}

				if ( getExpressCheckoutData( 'is_product_page' ) ) {
					const addToCartButton = $( '.single_add_to_cart_button' );

					// First check if product can be added to cart.
					if ( addToCartButton.is( '.disabled' ) ) {
						if (
							addToCartButton.is( '.wc-variation-is-unavailable' )
						) {
							// eslint-disable-next-line no-alert
							window.alert(
								// eslint-disable-next-line camelcase
								getAddToCartVariationParams(
									'i18n_unavailable_text'
								) ||
									__(
										'Sorry, this product is unavailable. Please choose a different combination.',
										'woocommerce-gateway-stripe'
									)
							);
						} else {
							// eslint-disable-next-line no-alert
							window.alert(
								__(
									'Please select your product options before proceeding.',
									'woocommerce-gateway-stripe'
								)
							);
						}
						return;
					}

					if ( wcStripeECEError ) {
						// eslint-disable-next-line no-alert
						window.alert( wcStripeECEError );
						return;
					}

					// Add products to the cart if everything is right.
					await wcStripeECE.addToCart();
				}

				const clickOptions = {
					lineItems: useLegacyCartEndpoints
						? normalizeLineItems( options.displayItems )
						: options.displayItems,
					emailRequired: true,
					shippingAddressRequired: options.requestShipping,
					phoneNumberRequired: options.requestPhone,
					shippingRates: options.shippingRates,
				};

				onClickHandler( event );
				event.resolve( clickOptions );
			} );

			eceButton.on(
				'shippingaddresschange',
				async ( event ) =>
					await shippingAddressChangeHandler( api, event, elements )
			);

			eceButton.on(
				'shippingratechange',
				async ( event ) =>
					await shippingRateChangeHandler( api, event, elements )
			);

			eceButton.on( 'confirm', async ( event ) => {
				const order = options.order ? options.order : 0;
				const orderDetails = options.orderDetails ?? {};
				return await onConfirmHandler( {
					api,
					stripe: api.getStripe(),
					elements,
					completePayment: wcStripeECE.completePayment,
					abortPayment: wcStripeECE.abortPayment,
					event,
					order,
					orderDetails,
				} );
			} );

			eceButton.on( 'cancel', () => {
				wcStripeECE.paymentAborted = true;
				onCancelHandler();
			} );

			eceButton.on( 'ready', ( onReadyParams ) => {
				onReadyHandler( onReadyParams );

				if (
					onReadyParams.availablePaymentMethods &&
					Object.values(
						onReadyParams.availablePaymentMethods
					).filter( Boolean ).length
				) {
					wcStripeECE.show();
					wcStripeECE.getButtonSeparator().show();
				}
			} );

			if ( getExpressCheckoutData( 'is_product_page' ) ) {
				wcStripeECE.attachProductPageEventListeners( elements );
			}
		},

		/**
		 * Initialize event handlers and UI state
		 */
		init: () => {
			if ( getExpressCheckoutData( 'is_pay_for_order' ) ) {
				if (
					typeof wcStripeExpressCheckoutPayForOrderParams ===
					'undefined'
				) {
					return;
				}

				const {
					total: { amount: total },
					displayItems,
					order,
					orderDetails,
				} = wcStripeExpressCheckoutPayForOrderParams;

				// When paying as guest, the order key and billing email are required by the
				// Blocks API Pay for Order endpoint, which ECE uses.
				// These fields are both present when the user is logged in.
				if (
					! orderDetails?.orderKey ||
					! orderDetails?.billingEmail
				) {
					return;
				}

				wcStripeECE.startExpressCheckout( {
					mode: 'payment',
					total,
					currency: getExpressCheckoutData( 'checkout' )
						.currency_code,
					appearance: getExpressCheckoutButtonAppearance(),
					locale: getExpressCheckoutData( 'stripe' )?.locale ?? 'en',
					displayItems: transformLabeledDisplayItems(
						displayItems ?? []
					),
					order,
					orderDetails,
				} );
			} else if ( getExpressCheckoutData( 'is_product_page' ) ) {
				const isProductSupported =
					getExpressCheckoutData( 'product' )
						?.validVariationSelected ?? true;
				if ( isProductSupported ) {
					const displayItems =
						getExpressCheckoutData( 'product' ).displayItems ?? [];
					wcStripeECE.startExpressCheckout( {
						mode: 'payment',
						total: getExpressCheckoutData( 'product' )?.total
							.amount,
						currency: getExpressCheckoutData( 'product' )?.currency,
						requestShipping:
							getExpressCheckoutData( 'product' )
								?.requestShipping ?? false,
						requestPhone:
							getExpressCheckoutData( 'checkout' )
								?.needs_payer_phone ?? false,
						displayItems: useLegacyCartEndpoints
							? displayItems
							: transformLabeledDisplayItems( displayItems ),
					} );
				}
			} else {
				// Cart and Checkout page specific initialization.
				api.expressCheckoutGetCartDetails().then( ( cart ) => {
					const total = transformPrice(
						parseInt( cart.totals.total_price, 10 ) -
							parseInt( cart.totals.total_refund || 0, 10 ),
						cart.totals
					);
					wcStripeECE.startExpressCheckout( {
						mode: 'payment',
						total,
						currency: getExpressCheckoutData( 'checkout' )
							?.currency_code,
						requestShipping: cart.needs_shipping === true,
						requestPhone: getExpressCheckoutData( 'checkout' )
							?.needs_payer_phone,
						displayItems: transformCartDataForDisplayItems( cart ),
					} );
				} );
			}

			// After initializing a new express checkout button, we need to reset the paymentAborted flag.
			wcStripeECE.paymentAborted = false;
		},

		getAttributes: () => {
			const select = $( '.variations_form' ).find( '.variations select' );
			const data = {};
			let count = 0;
			let chosen = 0;

			select.each( function () {
				const attributeName =
					$( this ).data( 'attribute_name' ) ||
					$( this ).attr( 'name' );
				const value = $( this ).val() || '';

				if ( value.length > 0 ) {
					chosen++;
				}

				count++;
				data[ attributeName ] = value;
			} );

			return {
				count,
				chosenCount: chosen,
				data,
			};
		},

		getSelectedProductData: () => {
			let productId = $( '.single_add_to_cart_button' ).val();

			// Check if product is a variable product.
			if ( $( '.single_variation_wrap' ).length ) {
				productId = $( '.single_variation_wrap' )
					.find( 'input[name="product_id"]' )
					.val();
			}

			// WC Bookings Support.
			if ( $( '.wc-bookings-booking-form' ).length ) {
				productId = $( '.wc-booking-product-id' ).val();
			}

			const addons =
				$( '#product-addons-total' ).data( 'price_data' ) || [];
			const addonValue = addons.reduce(
				( sum, addon ) => sum + addon.cost,
				0
			);

			// WC Deposits Support.
			const depositObject = {};
			if ( $( 'input[name=wc_deposit_option]' ).length ) {
				depositObject.wc_deposit_option = $(
					'input[name=wc_deposit_option]:checked'
				).val();
			}
			if ( $( 'input[name=wc_deposit_payment_plan]' ).length ) {
				depositObject.wc_deposit_payment_plan = $(
					'input[name=wc_deposit_payment_plan]:checked'
				).val();
			}

			const data = {
				product_id: productId,
				qty: $( quantityInputSelector ).val(),
				attributes: $( '.variations_form' ).length
					? wcStripeECE.getAttributes().data
					: [],
				addon_value: addonValue,
				...depositObject,
			};

			return api.expressCheckoutGetSelectedProductData( data );
		},

		/**
		 * Adds the item to the cart and return cart details.
		 *
		 * @return {Promise} Promise for the request to the server.
		 */
		addToCart: () => {
			let productId = $( '.single_add_to_cart_button' ).val();

			const data = {
				qty: $( quantityInputSelector ).val(),
			};

			if ( $( '.wc-bookings-booking-form' ).length ) {
				productId = $( '.wc-booking-product-id' ).val();
			}

			// Add extension data to the POST body
			const formData = $( 'form.cart' ).serializeArray();
			$.each( formData, ( i, field ) => {
				if ( /^(addon-|wc_)/.test( field.name ) ) {
					if ( /\[\]$/.test( field.name ) ) {
						const fieldName = field.name.substring(
							0,
							field.name.length - 2
						);
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

			// Legacy support for variations.
			if ( useLegacyCartEndpoints ) {
				data.product_id = productId;
				data.attributes = wcStripeECE.getAttributes().data;

				return api.expressCheckoutAddToCartLegacy( data );
			}

			// BlocksAPI partial support (lacking support for variations).
			data.id = productId;
			data.variation = [];

			return api.expressCheckoutAddToCart( data );
		},

		/**
		 * Complete payment.
		 *
		 * @param {string} url Order thank you page URL.
		 */
		completePayment: ( url ) => {
			onCompletePaymentHandler( url );
			window.location = url;
		},

		/**
		 * Abort the payment and display error messages.
		 *
		 * @param {PaymentResponse} payment Payment response instance.
		 * @param {string} message Error message to display.
		 * @param {boolean} isOrderError Whether the error is related to the order creation.
		 */
		abortPayment: ( payment, message, isOrderError = false ) => {
			if ( ! isOrderError ) {
				payment.paymentFailed( { reason: 'fail' } );
			}
			onAbortPaymentHandler( payment, message );

			displayExpressCheckoutNotice( message, 'error' );
		},

		attachProductPageEventListeners: ( elements ) => {
			// WooCommerce Deposits support.
			// Trigger the "woocommerce_variation_has_changed" event when the deposit option is changed.
			// Needs to be defined before the `woocommerce_variation_has_changed` event handler is set.
			$(
				'input[name=wc_deposit_option],input[name=wc_deposit_payment_plan]'
			)
				.off( 'change' )
				.on( 'change', () => {
					$( 'form' )
						.has(
							'input[name=wc_deposit_option],input[name=wc_deposit_payment_plan]'
						)
						.trigger( 'woocommerce_variation_has_changed' );
				} );

			$( document.body )
				.off( 'woocommerce_variation_has_changed' )
				.on( 'woocommerce_variation_has_changed', () => {
					wcStripeECE.blockExpressCheckoutButton();

					$.when( wcStripeECE.getSelectedProductData() )
						.then( ( response ) => {
							if ( response.error ) {
								wcStripeECE.hide();
							} else {
								const isDeposits = wcStripeECE.productHasDepositOption();
								/**
								 * If the customer aborted the express checkout,
								 * we need to re init the express checkout button to ensure the shipping
								 * options are refetched. If the customer didn't abort the express checkout,
								 * and the product's shipping status is consistent,
								 * we can simply update the express checkout button with the new total and display items.
								 */
								const needsShipping =
									! wcStripeECE.paymentAborted &&
									getExpressCheckoutData( 'product' )
										.requestShipping ===
										response.requestShipping;

								if ( ! isDeposits && needsShipping ) {
									elements.update( {
										amount: response.total.amount,
									} );
								} else {
									wcStripeECE.reInitExpressCheckoutElement(
										response
									);
								}
								wcStripeECE.show();
							}
						} )
						.catch( () => {
							wcStripeECE.hide();
						} )
						.always( () => {
							wcStripeECE.unblockExpressCheckoutButton();
						} );
				} );

			$( '.quantity' )
				.off( 'input', '.qty' )
				.on(
					'input',
					'.qty',
					debounce( () => {
						wcStripeECE.blockExpressCheckoutButton();
						wcStripeECEError = '';

						$.when( wcStripeECE.getSelectedProductData() )
							.then(
								( response ) => {
									// In case the server returns an unexpected response
									if ( typeof response !== 'object' ) {
										wcStripeECEError = defaultErrorMessage;
									}

									if (
										! wcStripeECE.paymentAborted &&
										getExpressCheckoutData( 'product' )
											.requestShipping ===
											response.requestShipping
									) {
										elements.update( {
											amount: response.total.amount,
										} );
									} else {
										wcStripeECE.reInitExpressCheckoutElement(
											response
										);
									}
								},
								( response ) => {
									if ( response.responseJSON ) {
										wcStripeECEError =
											response.responseJSON.error;
									} else {
										wcStripeECEError = defaultErrorMessage;
									}
								}
							)
							.always( function () {
								wcStripeECE.unblockExpressCheckoutButton();
							} );
					}, 250 )
				);
		},

		reInitExpressCheckoutElement: ( response ) => {
			getExpressCheckoutData( 'product' ).requestShipping =
				response.requestShipping;
			getExpressCheckoutData( 'product' ).total = response.total;
			getExpressCheckoutData( 'product' ).displayItems =
				response.displayItems;
			wcStripeECE.init();
		},

		blockExpressCheckoutButton: () => {
			// check if element isn't already blocked before calling block() to avoid blinking overlay issues
			// blockUI.isBlocked is either undefined or 0 when element is not blocked
			if (
				$( '#wc-stripe-express-checkout-element' ).data(
					'blockUI.isBlocked'
				)
			) {
				return;
			}

			$( '#wc-stripe-express-checkout-element' ).block( {
				message: null,
			} );
		},

		unblockExpressCheckoutButton: () => {
			$( '#wc-stripe-express-checkout-element' ).unblock();
		},
	};

	// We don't need to initialize ECE on the checkout page now because it will be initialized by updated_checkout event.
	if (
		getExpressCheckoutData( 'is_product_page' ) ||
		getExpressCheckoutData( 'is_pay_for_order' ) ||
		getExpressCheckoutData( 'is_cart_page' )
	) {
		wcStripeECE.init();
	}

	// We need to refresh ECE data when total is updated.
	$( document.body ).on( 'updated_cart_totals', () => {
		wcStripeECE.init();
	} );

	// We need to refresh ECE data when total is updated.
	$( document.body ).on( 'updated_checkout', () => {
		wcStripeECE.init();
	} );

	// Handle bookable products on the product page.
	let wcBookingFormChanged = false;

	$( document.body )
		.off( 'wc_booking_form_changed' )
		.on( 'wc_booking_form_changed', () => {
			wcBookingFormChanged = true;
		} );

	// Listen for the WC Bookings wc_bookings_calculate_costs event to complete
	// and add the bookable product to the cart, using the response to update the
	// payment request request params with correct totals.
	$( document ).ajaxComplete( function ( event, xhr, settings ) {
		if ( wcBookingFormChanged ) {
			if (
				settings.url === window.booking_form_params.ajax_url &&
				settings.data.includes( 'wc_bookings_calculate_costs' ) &&
				xhr.responseText.includes( 'SUCCESS' )
			) {
				wcStripeECE.blockExpressCheckoutButton();
				wcBookingFormChanged = false;

				return wcStripeECE.addToCart().then( ( response ) => {
					getExpressCheckoutData( 'product' ).total = response.total;
					getExpressCheckoutData( 'product' ).displayItems =
						response.displayItems;

					// Empty the cart to avoid having 2 products in the cart when payment request is not used.
					api.expressCheckoutEmptyCart( response.bookingId );

					wcStripeECE.init();

					wcStripeECE.unblockExpressCheckoutButton();
				} );
			}
		}
	} );
} );
