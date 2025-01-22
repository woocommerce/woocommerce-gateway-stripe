import { __ } from '@wordpress/i18n';
import { addAction, removeAction } from '@wordpress/hooks';
import { debounce } from 'lodash';
import jQuery from 'jquery';
import WCStripeAPI from '../../api';
import {
	displayExpressCheckoutNotice,
	displayLoginConfirmation,
	expressCheckoutNoticeDelay,
	getExpressCheckoutButtonAppearance,
	getExpressCheckoutButtonStyleSettings,
	getExpressCheckoutData,
	getExpressPaymentMethodTypes,
	normalizeLineItems,
	setExpressCheckoutData,
} from 'wcstripe/express-checkout/utils';
import {
	getCartApiHandler,
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
import './styles.scss';
import ExpressCheckoutCartApi from 'wcstripe/express-checkout/cart-api';
import {
	transformCartDataForDisplayItems,
	transformCartDataForShippingRates,
	transformPrice,
} from 'wcstripe/express-checkout/transformers/wc-to-stripe';

const getServerSideExpressCheckoutProductData = () => {
	const requestShipping =
		getExpressCheckoutData( 'product' )?.needs_shipping ?? false;
	const displayItems = (
		getExpressCheckoutData( 'product' )?.displayItems ?? []
	).map( ( { label, amount } ) => ( {
		name: label,
		amount,
	} ) );
	const shippingRates = requestShipping
		? [
				{
					id: 'pending',
					displayName: __( 'Pending', 'woocommerce-payments' ),
					amount: 0,
				},
		  ]
		: undefined;

	return {
		total: getExpressCheckoutData( 'product' )?.total.amount,
		currency: getExpressCheckoutData( 'product' )?.currency,
		requestShipping,
		shippingRates,
		requestPhone:
			getExpressCheckoutData( 'checkout' )?.needs_payer_phone ?? false,
		displayItems,
	};
};

jQuery( function ( $ ) {
	// Don't load if blocks checkout is being loaded.
	if (
		getExpressCheckoutData( 'has_block' ) &&
		! getExpressCheckoutData( 'is_pay_for_order' )
	) {
		return;
	}

	const publishableKey = getExpressCheckoutData( 'stripe' ).publishable_key;

	if ( ! publishableKey ) {
		// If no configuration is present, probably this is not the checkout page.
		return;
	}

	let cachedCartData = null;
	const noop = () => null;
	const fetchNewCartData = async () => {
		if ( getExpressCheckoutData( 'button_context' ) !== 'product' ) {
			return await getCartApiHandler().getCart();
		}

		// creating a new cart and clearing it afterward,
		// to avoid scenarios where the stock for a product with limited (or low) availability is added to the cart,
		// preventing other customers from purchasing.
		const temporaryCart = new ExpressCheckoutCartApi();
		temporaryCart.useSeparateCart();

		const cartData = await temporaryCart.addProductToCart();

		// no need to wait for the request to end, it can be done asynchronously.
		// using `.finally( noop )` to avoid annoying IDE warnings.
		temporaryCart.emptyCart().finally( noop );

		return cartData;
	};

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

		renderButton: ( eceButton ) => {
			if ( $( '#wc-stripe-express-checkout-element' ).length ) {
				eceButton.mount( '#wc-stripe-express-checkout-element' );
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
		startExpressCheckoutElement: ( options ) => {
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
						id: `rate-shipping`,
						amount: i.amount,
						displayName: i.label,
					} ) );
			};

			const shippingRates = getShippingRates();

			// This is a bit of a hack, but we need some way to get the shipping information before rendering the button, and
			// since we don't have any address information at this point it seems best to rely on what came with the cart response.
			// Relying on what's provided in the cart response seems safest since it should always include a valid shipping
			// rate if one is required and available.
			// If no shipping rate is found we can't render the button so we just exit.
			if ( options.requestShipping && ! shippingRates ) {
				return;
			}

			const elements = api.getStripe().elements( {
				mode: options.mode ? options.mode : 'payment',
				amount: options.total,
				currency: options.currency,
				paymentMethodCreation: 'manual',
				appearance: getExpressCheckoutButtonAppearance(),
				locale: getExpressCheckoutData( 'stripe' )?.locale ?? 'en',
				paymentMethodTypes: getExpressPaymentMethodTypes(),
			} );

			const eceButton = wcStripeECE.createButton(
				elements,
				getExpressCheckoutButtonStyleSettings()
			);

			wcStripeECE.renderButton( eceButton );

			eceButton.on( 'loaderror', () => {
				wcStripeECEError = __(
					'The cart is incompatible with express checkout.',
					'woocommerce-gateway-stripe'
				);
			} );

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
					// Wait for the notice to be displayed before proceeding.
					await expressCheckoutNoticeDelay();
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
					wcStripeECE.addToCart(); // @todo
				}

				const clickOptions = {
					lineItems: normalizeLineItems( options.displayItems ), // @todo
					emailRequired: true,
					shippingAddressRequired: options.requestShipping,
					phoneNumberRequired: options.requestPhone,
					shippingRates,
				};

				onClickHandler( event );
				event.resolve( clickOptions );
			} );

			eceButton.on(
				'shippingaddresschange',
				async ( event ) =>
					await shippingAddressChangeHandler( event, elements )
			);

			eceButton.on(
				'shippingratechange',
				async ( event ) =>
					await shippingRateChangeHandler( event, elements )
			);

			eceButton.on( 'confirm', async ( event ) => {
				return await onConfirmHandler(
					api,
					api.getStripe(),
					elements,
					wcStripeECE.completePayment,
					wcStripeECE.abortPayment,
					event
				);
			} );

			eceButton.on( 'cancel', () => {
				wcStripeECE.paymentAborted = true;
				if (
					getExpressCheckoutData( 'button_context' ) === 'product'
				) {
					// clearing the cart to avoid issues with products with low or limited availability
					// being held hostage by customers cancelling the ECE.
					getCartApiHandler().emptyCart();
				}
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

			removeAction(
				'wcstripe.express-checkout.update-button-data',
				'automattic/wcstripe/express-checkout'
			);
			addAction(
				'wcstripe.express-checkout.update-button-data',
				'automattic/wcstripe/express-checkout',
				async () => {
					// if the product cannot be added to cart (because of missing variation selection, etc),
					// don't try to add it to the cart to get new data - the call will likely fail.
					if (
						getExpressCheckoutData( 'button_context' ) === 'product'
					) {
						const addToCartButton = $(
							'.single_add_to_cart_button'
						);

						// First check if product can be added to cart.
						if ( addToCartButton.is( '.disabled' ) ) {
							return;
						}
					}

					try {
						wcStripeECE.blockExpressCheckoutButton();

						cachedCartData = await fetchNewCartData();
						// checking if items needed shipping, before assigning new cart data.
						const didItemsNeedShipping = options.requestShipping;

						/**
						 * If the customer aborted the payment request, we need to re init the payment request button to ensure the shipping
						 * options are re-fetched. If the customer didn't abort the payment request, and the product's shipping status is
						 * consistent, we can simply update the payment request button with the new total and display items.
						 */
						if (
							! wcStripeECE.paymentAborted &&
							didItemsNeedShipping ===
								cachedCartData.needs_shipping
						) {
							elements.update( {
								total: {
									label: getExpressCheckoutData(
										'total_label'
									),
									amount: transformPrice(
										parseInt(
											cachedCartData.totals.total_price,
											10
										) -
											parseInt(
												cachedCartData.totals
													.total_refund || 0,
												10
											),
										cachedCartData.totals
									),
								},
								displayItems: transformCartDataForDisplayItems(
									cachedCartData
								),
							} );
						} else {
							// the cachedCartData from the Store API will be used from now on,
							// instead of the `product` attributes.
							setExpressCheckoutData( 'product', null );

							await wcStripeECE.init();
						}

						wcStripeECE.unblockExpressCheckoutButton();
					} catch ( e ) {
						wcStripeECE.hide();
					}
				}
			);
		},

		/**
		 * Initialize event handlers and UI state
		 */
		init: async () => {
			// on product pages, we should be able to have `getExpressCheckoutData( 'product' )` from the backend,
			// which saves us some AJAX calls.
			if ( ! getExpressCheckoutData( 'product' ) && ! cachedCartData ) {
				try {
					cachedCartData = await fetchNewCartData();
				} catch ( e ) {
					// if something fails here, we can likely fall back on `getExpressCheckoutData( 'product' )`.
				}
			}

			// once (and if) cart data has been fetched, we can safely clear product data from the backend.
			if ( cachedCartData ) {
				setExpressCheckoutData( 'product', undefined );
			}

			if ( getExpressCheckoutData( 'button_context' ) === 'product' ) {
				// on product pages, we need to interact with an anonymous cart to check out the product,
				// so that we don't affect the products in the main cart.
				// On cart, checkout, place order pages we instead use the cart itself.
				getCartApiHandler().useSeparateCart();
			}

			if ( cachedCartData ) {
				// If this is the cart page, or checkout page, or pay-for-order page, we need to request the cart details.
				// but if the data is not available, we can't render the button.
				const total = transformPrice(
					parseInt( cachedCartData.totals.total_price, 10 ) -
						parseInt( cachedCartData.totals.total_refund || 0, 10 ),
					cachedCartData.totals
				);
				if ( total === 0 ) {
					wcStripeECE.hide();
					wcStripeECE.getButtonSeparator().hide();
				} else {
					await wcStripeECE.startExpressCheckoutElement( {
						total,
						currency: cachedCartData.totals.currency_code.toLowerCase(),
						// pay-for-order should never display the shipping selection.
						requestShipping:
							getExpressCheckoutData( 'button_context' ) !==
								'pay_for_order' &&
							cachedCartData.needs_shipping,
						shippingRates: transformCartDataForShippingRates(
							cachedCartData
						),
						requestPhone:
							getExpressCheckoutData( 'checkout' )
								?.needs_payer_phone ?? false,
						displayItems: transformCartDataForDisplayItems(
							cachedCartData
						),
					} );
				}
			} else if (
				getExpressCheckoutData( 'button_context' ) === 'product' &&
				getExpressCheckoutData( 'product' )
			) {
				await wcStripeECE.startExpressCheckoutElement(
					getServerSideExpressCheckoutProductData()
				);
			} else {
				wcStripeECE.hide();
				wcStripeECE.getButtonSeparator().hide();
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
			wcStripeECE.show();
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
