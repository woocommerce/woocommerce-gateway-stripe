/*global wc_add_to_cart_variation_params */

import { __ } from '@wordpress/i18n';
import { debounce } from 'lodash';
import jQuery from 'jquery';
import WCStripeAPI from '../../api';
import {
	displayLoginConfirmation,
	getExpressCheckoutButtonAppearance,
	getExpressCheckoutButtonStyleSettings,
	getExpressCheckoutData,
	normalizeLineItems,
} from './utils';
import {
	onAbortPaymentHandler,
	onCancelHandler,
	onClickHandler,
	onCompletePaymentHandler,
	onConfirmHandler,
	onReadyHandler,
	shippingAddressChangeHandler,
	shippingRateChangeHandler,
} from './event-handlers';
import { getStripeServerData } from 'wcstripe/stripe-utils';

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
		'woocommerce-payments'
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
					// Despite the name of the property, this seems to be just a single option that's not in an array.
					const {
						shippingOptions: shippingOption,
					} = getExpressCheckoutData( 'product' );

					return [
						{
							id: shippingOption.id,
							amount: shippingOption.amount,
							displayName: shippingOption.label,
						},
					];
				}

				return options.displayItems
					.filter(
						( i ) =>
							i.label === __( 'Shipping', 'woocommerce-payments' )
					)
					.map( ( i ) => ( {
						id: `rate-${ i.label }`,
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
			} );

			const eceButton = wcStripeECE.createButton(
				elements,
				getExpressCheckoutButtonStyleSettings()
			);

			wcStripeECE.renderButton( eceButton );

			eceButton.on( 'loaderror', () => {
				wcStripeECEError = __(
					'The cart is incompatible with express checkout.',
					'woocommerce-payments'
				);
				if ( ! document.getElementById( 'wc-stripe-woopay-button' ) ) {
					wcStripeECE.getButtonSeparator().hide();
				}
			} );

			eceButton.on( 'click', function ( event ) {
				// If login is required for checkout, display redirect confirmation dialog.
				if ( getExpressCheckoutData( 'login_confirmation' ) ) {
					displayLoginConfirmation( event.expressPaymentType );
					return;
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
								wc_add_to_cart_variation_params.i18n_unavailable_text ||
									__(
										'Sorry, this product is unavailable. Please choose a different combination.',
										'woocommerce-payments'
									)
							);
						} else {
							// eslint-disable-next-line no-alert
							window.alert(
								__(
									'Please select your product options before proceeding.',
									'woocommerce-payments'
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
					wcStripeECE.addToCart();
				}

				const clickOptions = {
					lineItems: normalizeLineItems( options.displayItems ),
					emailRequired: true,
					shippingAddressRequired: options.requestShipping,
					phoneNumberRequired: options.requestPhone,
					shippingRates,
				};

				onClickHandler( event );
				event.resolve( clickOptions );
			} );

			eceButton.on( 'shippingaddresschange', async ( event ) =>
				shippingAddressChangeHandler( api, event, elements )
			);

			eceButton.on( 'shippingratechange', async ( event ) =>
				shippingRateChangeHandler( api, event, elements )
			);

			eceButton.on( 'confirm', async ( event ) => {
				const order = options.order ? options.order : 0;

				return onConfirmHandler(
					api,
					api.getStripe(),
					elements,
					wcStripeECE.completePayment,
					wcStripeECE.abortPayment,
					event,
					order
				);
			} );

			eceButton.on( 'cancel', async () => {
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
				// Pay for order page specific initialization.
			} else if ( getExpressCheckoutData( 'is_product_page' ) ) {
				// Product page specific initialization.
			} else {
				// Cart and Checkout page specific initialization.
				// TODO: Use real cart data.
				wcStripeECE.startExpressCheckoutElement( {
					mode: 'payment',
					total: 1223,
					currency: 'usd',
					appearance: getExpressCheckoutButtonAppearance(),
					displayItems: [ { label: 'Shipping', amount: 100 } ],
				} );
			}

			// After initializing a new express checkout button, we need to reset the paymentAborted flag.
			wcStripeECE.paymentAborted = false;
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
		 */
		abortPayment: ( payment, message ) => {
			payment.paymentFailed( { reason: 'fail' } );
			onAbortPaymentHandler( payment, message );

			$( '.woocommerce-error' ).remove();

			const $container = $( '.woocommerce-notices-wrapper' ).first();

			if ( $container.length ) {
				$container.append(
					$( '<div class="woocommerce-error" />' ).text( message )
				);

				$( 'html, body' ).animate(
					{
						scrollTop: $container
							.find( '.woocommerce-error' )
							.offset().top,
					},
					600
				);
			}
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
									.needs_shipping === response.needs_shipping;

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
											.needs_shipping ===
											response.needs_shipping
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
			getExpressCheckoutData( 'product' ).needs_shipping =
				response.needs_shipping;
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

	wcStripeECE.init();
} );
