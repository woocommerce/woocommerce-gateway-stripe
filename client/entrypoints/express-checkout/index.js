/* global wcStripeExpressCheckoutPayForOrderParams */
/* global wc_stripe_express_checkout_params */

import jQuery from 'jquery';
import WCStripeAPI from '../../api';
import { __ } from '@wordpress/i18n';
import {
	displayExpressCheckoutNotice,
	displayLoginConfirmation,
	getExpressCheckoutButtonAppearance,
	getExpressCheckoutButtonStyleSettings,
	getExpressCheckoutData,
	getPaymentMethodTypesForExpressMethod,
	getSelectedVariationAttributes,
	getSelectedVariationId,
	hasVariationSelectionUi,
	isAddToCartUnavailable,
	isManualPaymentMethodCreation,
	isSelectedVariationUnavailable,
	normalizeLineItems,
	transformVariationAttributesForStoreApi,
	buildBookingConfiguration,
} from 'wcstripe/express-checkout/utils';
import {
	onAbortPaymentHandler,
	onCancelHandler,
	onClickHandler,
	onCompletePaymentHandler,
	onConfirmHandler,
	shippingAddressChangeHandler,
	shippingRateChangeHandler,
} from 'wcstripe/express-checkout/event-handler';
import { getAddToCartVariationParams } from 'wcstripe/utils';
import 'wcstripe/express-checkout/compatibility/wc-order-attribution';
import 'wcstripe/express-checkout/compatibility/classic-checkout-custom-fields';
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
	transformCartTotalAmount,
	transformLabeledDisplayItems,
} from 'wcstripe/express-checkout/transformers/wc-to-stripe';

jQuery( function ( $ ) {
	// Don't load if blocks checkout is being loaded.
	if (
		getExpressCheckoutData( 'has_block' ) &&
		! getExpressCheckoutData( 'is_pay_for_order' )
	) {
		return;
	}

	const stripeParams = getExpressCheckoutData( 'stripe' );
	const publishableKey = stripeParams?.publishable_key;
	const quantityInputSelector = '.quantity .qty[type=number]';

	if ( ! publishableKey ) {
		// If no configuration is present, probably this is not the checkout page.
		return;
	}

	const api = new WCStripeAPI(
		{
			key: publishableKey,
			locale: stripeParams.locale,
			ajax_url: getExpressCheckoutData( 'ajax_url' ),
		},
		// A promise-based interface to jQuery.post.
		( url, args ) => {
			return new Promise( ( resolve, reject ) => {
				jQuery.post( url, args ).then( resolve ).fail( reject );
			} );
		}
	);

	// Snapshot is first-paint only; re-inits reconcile via AJAX (see init() below).
	let cartBootstrapConsumed = false;

	// True for a variable product on both the classic and blockified templates.
	const hasVariationUi = hasVariationSelectionUi();
	const hasBookingForm = $( '.wc-bookings-booking-form' ).length > 0;

	// Variable and booking products keep the legacy display-item format: their
	// product-page preview comes from `get_selected_product_data`, which has no
	// Store API equivalent. Add-to-cart routing is handled separately in `addToCart()`.
	const useLegacyDisplayItems = hasVariationUi || hasBookingForm;

	const resolveClickEvent = ( event, options ) => {
		const getDefaultShippingRates = () => {
			// Return a default shipping option when shipping is required but no rates are provided
			const defaultShippingOption =
				getExpressCheckoutData( 'checkout' )?.default_shipping_option;
			return defaultShippingOption ? [ defaultShippingOption ] : [];
		};
		const allowedShippingCountries = getExpressCheckoutData(
			'allowed_shipping_countries'
		);

		// Product pages: the click handler writes cart-derived items into the
		// cached product params (`getExpressCheckoutData( 'product' )`) before
		// resolving, so read them from there for every product type —
		// creation-time options would show quantity-1 items under a
		// quantity-aware total. normalizeLineItems handles both the legacy
		// and the cart-derived item shapes.
		const isProductPage = getExpressCheckoutData( 'is_product_page' );
		const displayItems = isProductPage
			? getExpressCheckoutData( 'product' )?.displayItems ??
			  options.displayItems
			: options.displayItems;

		// The creation-time flag reflects the parent product, which reports
		// needing shipping even when every variation is virtual - prompting
		// for an address then hard-fails on a cart with no shippable items.
		// The cart response knows the actual selection, so it wins.
		const requestShipping = isProductPage
			? getExpressCheckoutData( 'product' )?.requestShipping ??
			  options.requestShipping
			: options.requestShipping;

		const clickOptions = {
			lineItems:
				isProductPage || useLegacyDisplayItems
					? normalizeLineItems( displayItems )
					: displayItems,
			emailRequired: true,
			shippingAddressRequired: requestShipping,
			phoneNumberRequired: options.requestPhone,
			...( requestShipping && {
				shippingRates:
					options.shippingRates?.length > 0
						? options.shippingRates
						: getDefaultShippingRates(),
			} ),
			...( requestShipping &&
				Array.isArray( allowedShippingCountries ) && {
					allowedShippingCountries,
				} ),
		};

		return event.resolve( clickOptions );
	};

	// Check if the product is waiting for a variation to be selected.
	const isVariationSelectionNeeded = () => {
		// This check only makes sense on the product page.
		const isProductPage = getExpressCheckoutData( 'is_product_page' );
		if ( ! isProductPage ) {
			return false;
		}

		return hasVariationUi && ! getSelectedVariationId();
	};

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
				if ( ! $( `#${ containerName }` ).length ) {
					$( '#wc-stripe-express-checkout-element' ).append(
						`<div id="${ containerName }"></div>`
					);
				}

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
						displayName: useLegacyDisplayItems
							? i.label ?? i.name
							: i.name,
					} ) );
			};

			const shippingRates = getShippingRates();

			// Deliberately not `is_express_checkout_enabled`: that aggregate is true when
			// any wallet's locations cover this page, which would render Apple/Google Pay
			// on pages where only another wallet (e.g. Amazon Pay) is enabled.
			const isApplePayEnabled =
				wc_stripe_express_checkout_params?.stripe?.is_apple_pay_enabled; // eslint-disable-line camelcase
			const isGooglePayEnabled =
				wc_stripe_express_checkout_params?.stripe // eslint-disable-line camelcase
					?.is_google_pay_enabled;
			const isAmazonPayEnabled =
				wc_stripe_express_checkout_params?.stripe // eslint-disable-line camelcase
					?.is_amazon_pay_enabled;
			const isLinkEnabled =
				wc_stripe_express_checkout_params?.stripe?.is_link_enabled; // eslint-disable-line camelcase
			const areTaxesBasedOnBillingAddress = getExpressCheckoutData(
				'taxes_based_on_billing'
			);
			// Amazon Pay needs a confirmation-token flow that
			// `handleChangePaymentMethodFlow` does not implement, so it must not
			// be offered on the subscription change-payment page.
			const isChangePaymentMethod = getExpressCheckoutData(
				'is_change_payment_method'
			);

			// For each supported express payment type, create their own
			// express checkout element. This is necessary as some express payment types
			// may require different options or configurations, e.g. Amazon Pay
			// does not support paymentMethodCreation: 'manual'.
			const expressPaymentTypes = [
				isApplePayEnabled && EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
				isGooglePayEnabled && EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY,
				isAmazonPayEnabled &&
					! areTaxesBasedOnBillingAddress &&
					! isChangePaymentMethod &&
					EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY,
				isLinkEnabled && EXPRESS_PAYMENT_METHOD_SETTING_LINK,
			].filter( Boolean );

			// Reset the registry so variation/qty updates only touch the buttons
			// mounted for this render.
			wcStripeECE.expressCheckoutElements = [];

			expressPaymentTypes.forEach( ( expressPaymentType ) => {
				wcStripeECE.createExpressCheckoutElement( expressPaymentType, {
					...options,
					shippingRates,
				} );
			} );
		},

		createExpressCheckoutElement: ( expressPaymentType, options ) => {
			const handleProductPageECEButtonClick = async (
				event,
				clickOptions
			) => {
				// The buttons render before a variation is selected, so this
				// guard is what prompts the shopper for their options instead
				// of opening the wallet sheet.
				if ( isAddToCartUnavailable() ) {
					// The click contract requires resolve() or reject()
					// within 1s; rejecting also closes the wallet UI some
					// methods (Link, Amazon Pay) open on the raw gesture.
					event.reject?.();

					const defaultMessage = __(
						'Please select your product options before proceeding.',
						'woocommerce-gateway-stripe'
					);
					let message;
					if ( isSelectedVariationUnavailable() ) {
						message =
							getAddToCartVariationParams(
								'i18n_unavailable_text'
							) ||
							__(
								'Sorry, this product is unavailable. Please choose a different combination.',
								'woocommerce-gateway-stripe'
							);
					} else if ( ! isVariationSelectionNeeded() ) {
						// Everything is selected (or the product has no
						// options): the block is stock or quantity, so asking
						// for options would mislead.
						message = __(
							'This product cannot be purchased with the selected options or quantity. Please adjust your selection and try again.',
							'woocommerce-gateway-stripe'
						);
					}

					// alert() pauses this page's event loop, which would also
					// freeze the wallet-UI dismissal that reject() queues for
					// methods opening on the raw gesture (e.g. Amazon Pay) —
					// leaving the sheet on screen behind the alert. Yield so
					// the dismissal lands first.
					setTimeout( () => {
						// eslint-disable-next-line no-alert
						window.alert( message || defaultMessage );
					}, 100 );
					return;
				}

				// Stripe requires resolve()/reject() within 1s of the click, so
				// the cart response gets a 700ms budget (leaving margin for the
				// resolve work). The timer winning the race doesn't mean the
				// request failed — it's still in flight, just too slow for
				// this click's deadline.
				const addToCartPromise = wcStripeECE.addToCart();
				const timeout = new Promise( ( resolve ) =>
					setTimeout( () => {
						resolve( 'timeout' );
					}, 700 )
				);
				const result = await Promise.race( [
					addToCartPromise,
					timeout,
				] );
				if ( result === 'timeout' ) {
					// Opening the sheet now would show a preview amount the cart
					// may not match, so reject and block retries until the
					// pending add settles; its response primes the next attempt.
					event.reject?.();
					wcStripeECE.blockExpressCheckoutButton();
					try {
						const response = await addToCartPromise;
						wcStripeECE.isAddToCartSuccessful =
							response?.items_count > 0 ||
							response?.result === 'success';
						wcStripeECE.refreshTotalsFromCart( response );
					} catch ( error ) {
						wcStripeECE.isAddToCartSuccessful = false;
					} finally {
						wcStripeECE.unblockExpressCheckoutButton();
					}

					return;
				}

				wcStripeECE.isAddToCartSuccessful = true;

				// The cart response embodies every price influence
				// (variation, quantity, add-ons, server-side fees), so the
				// sheet resolves from it rather than from any preview.
				wcStripeECE.refreshTotalsFromCart( result );

				return resolveClickEvent( event, clickOptions );
			};

			// This is a bit of a hack, but we need some way to get the shipping information before rendering the button, and
			// since we don't have any address information at this point it seems best to rely on what came with the cart response.
			// Relying on what's provided in the cart response seems safest since it should always include a valid shipping
			// rate if one is required and available.
			// If no shipping rate is found we can't render the button so we just exit.
			if ( options.requestShipping && ! options.shippingRates ) {
				return;
			}

			const hasFreeTrial = getExpressCheckoutData( 'has_free_trial' );
			const isChangePaymentMethod = getExpressCheckoutData(
				'is_change_payment_method'
			);

			let elementsMode;
			if ( isChangePaymentMethod ) {
				elementsMode = 'setup';
			} else if ( hasFreeTrial ) {
				elementsMode = 'subscription';
			} else {
				elementsMode = 'payment';
			}

			let stripe;
			try {
				stripe = api.getStripe();
			} catch ( error ) {
				// Stripe.js failed the origin assertion (fail closed): skip
				// rendering the express checkout button instead of throwing.
				return;
			}

			const elements = stripe.elements( {
				mode: elementsMode,
				...( elementsMode !== 'setup' && {
					amount: options.total,
				} ),
				currency: options.currency,
				...( isManualPaymentMethodCreation(
					expressPaymentType,
					isChangePaymentMethod || hasFreeTrial
				) && {
					paymentMethodCreation: 'manual',
				} ),
				appearance: getExpressCheckoutButtonAppearance(),
				locale: getExpressCheckoutData( 'stripe' )?.locale ?? 'en',
				paymentMethodTypes:
					getPaymentMethodTypesForExpressMethod( expressPaymentType ),
			} );

			// A product page can mount several express buttons (Apple Pay,
			// Google Pay, …), each with its own Elements group. Track them so a
			// variation/qty change updates every group's amount.
			wcStripeECE.expressCheckoutElements.push( elements );

			const buttonStyleSettings =
				getExpressCheckoutButtonStyleSettings( expressPaymentType );

			const eceButton = wcStripeECE.createButton( elements, {
				...buttonStyleSettings,
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
					event.reject?.();
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

				if ( ! getExpressCheckoutData( 'is_product_page' ) ) {
					onClickHandler( event );
					return resolveClickEvent( event, options );
				}

				return await handleProductPageECEButtonClick( event, options );
			} );

			const handleProductPageShippingAddressChange = async (
				event,
				stripeElements
			) => {
				if ( wcStripeECE.isAddToCartSuccessful === false ) {
					// wait 1s for the item to be added to the cart before proceeding
					await new Promise( ( resolve ) =>
						setTimeout( resolve, 1000 )
					);
				}

				return shippingAddressChangeHandler( event, stripeElements );
			};

			eceButton.on( 'shippingaddresschange', async ( event ) => {
				if ( getExpressCheckoutData( 'is_product_page' ) ) {
					return await handleProductPageShippingAddressChange(
						event,
						elements
					);
				}
				return await shippingAddressChangeHandler( event, elements );
			} );

			eceButton.on(
				'shippingratechange',
				async ( event ) =>
					await shippingRateChangeHandler( event, elements )
			);

			eceButton.on( 'confirm', async ( event ) => {
				if (
					getExpressCheckoutData( 'is_product_page' ) &&
					wcStripeECE.isAddToCartSuccessful === false
				) {
					// wait 1s for the item to be added to the cart before proceeding
					await new Promise( ( resolve ) =>
						setTimeout( resolve, 1000 )
					);

					if ( wcStripeECE.isAddToCartSuccessful === false ) {
						const message = __(
							'There was an error adding the product to the cart.',
							'woocommerce-gateway-stripe'
						);
						return wcStripeECE.abortPayment( event, message );
					}
				}

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
					hasFreeTrial,
				} );
			} );

			eceButton.on( 'cancel', () => {
				onCancelHandler();
			} );

			eceButton.on( 'ready', ( onReadyParams ) => {
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
		},

		/**
		 * Initialize event handlers and UI state
		 */
		init: () => {
			if ( getExpressCheckoutData( 'is_change_payment_method' ) ) {
				const currency =
					getExpressCheckoutData( 'checkout' )?.currency_code ??
					'usd';
				wcStripeECE.startExpressCheckout( {
					total: 0,
					currency,
					appearance: getExpressCheckoutButtonAppearance(),
					locale: getExpressCheckoutData( 'stripe' )?.locale ?? 'en',
				} );
				return;
			}

			if ( getExpressCheckoutData( 'is_pay_for_order' ) ) {
				if (
					typeof wcStripeExpressCheckoutPayForOrderParams ===
					'undefined'
				) {
					return;
				}

				const {
					total: { amount: total },
					currency,
					displayItems,
					order,
					orderDetails,
				} = wcStripeExpressCheckoutPayForOrderParams;

				// When paying as guest, the order key and billing email are required by the
				// Store API Pay for Order endpoint, which ECE uses.
				// These fields are both present when the user is logged in.
				if (
					! orderDetails?.orderKey ||
					! orderDetails?.billingEmail
				) {
					return;
				}

				wcStripeECE.startExpressCheckout( {
					total,
					currency:
						currency ??
						getExpressCheckoutData( 'checkout' ).currency_code,
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
						total: getExpressCheckoutData( 'product' )?.total
							.amount,
						currency: getExpressCheckoutData( 'product' )?.currency,
						requestShipping:
							getExpressCheckoutData( 'product' )
								?.requestShipping ?? false,
						requestPhone:
							getExpressCheckoutData( 'checkout' )
								?.needs_payer_phone ?? false,
						displayItems: useLegacyDisplayItems
							? displayItems
							: transformLabeledDisplayItems( displayItems ),
					} );
				}
			} else {
				// Cart and Checkout page specific initialization.
				const cartBootstrap = getExpressCheckoutData( 'cart' );

				// First paint renders from the snapshot, skipping GET /wc/store/v1/cart;
				// re-inits fall through to the AJAX path below for live cart updates.
				if ( cartBootstrap && ! cartBootstrapConsumed ) {
					cartBootstrapConsumed = true;

					wcStripeECE.startExpressCheckout( {
						total: cartBootstrap.total,
						currency: cartBootstrap.currency,
						requestShipping: cartBootstrap.requestShipping,
						requestPhone: cartBootstrap.requestPhone,
						displayItems: transformLabeledDisplayItems(
							cartBootstrap.displayItems ?? []
						),
					} );

					return;
				}

				api.expressCheckoutGetCartDetails().then( ( cart ) => {
					const total = transformCartTotalAmount( cart.totals );

					if (
						total === 0 &&
						! getExpressCheckoutData( 'has_free_trial' )
					) {
						wcStripeECE.hide();
						return;
					}

					wcStripeECE.startExpressCheckout( {
						total,
						currency:
							getExpressCheckoutData( 'checkout' )?.currency_code,
						requestShipping: cart.needs_shipping === true,
						requestPhone:
							getExpressCheckoutData( 'checkout' )
								?.needs_payer_phone,
						displayItems: transformCartDataForDisplayItems( cart ),
					} );
				} );
			}
		},

		getAttributes: () => getSelectedVariationAttributes(),

		/**
		 * Adds the item to the cart and return cart details.
		 *
		 * @return {Promise} Promise for the request to the server.
		 */
		addToCart: async () => {
			let productId = $( '.single_add_to_cart_button' ).val();
			let emptyCartParams = {};

			const data = {
				qty: $( quantityInputSelector ).val(),
			};

			// Check if product is a variable product.
			if ( $( '.single_variation_wrap' ).length ) {
				productId = $( '.single_variation_wrap' )
					.find( 'input[name="product_id"]' )
					.val();
			}

			if ( $( '.wc-bookings-booking-form' ).length ) {
				productId = $( '.wc-booking-product-id' ).val();
				emptyCartParams = {
					bookingId: productId,
				};
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

			if ( hasBookingForm ) {
				// Use the Store API only when Bookings supports it and the booking
				// maps to a `booking_configuration`; otherwise fall back to legacy.
				const bookingConfiguration = getExpressCheckoutData(
					'has_bookings_store_api'
				)
					? buildBookingConfiguration(
							document.querySelector(
								'.wc-bookings-booking-form'
							)
					  )
					: null;

				// Clear the cart first (with the booking id) so prior items don't
				// skew the total, matching the variable/simple path below.
				await api.expressCheckoutEmptyCartLegacy( emptyCartParams );

				if ( ! bookingConfiguration ) {
					data.product_id = productId;
					data.attributes = wcStripeECE.getAttributes().data;

					return api.expressCheckoutAddToCartLegacy( data );
				}

				data.id = productId;
				data.booking_configuration = bookingConfiguration;

				return api.expressCheckoutAddToCart( data );
			}

			data.id = productId;

			// Variable products: `productId` is the parent id, so pass the chosen
			// attributes for the Store API to resolve the variation (incl. "any" attributes).
			data.variation = hasVariationUi
				? transformVariationAttributesForStoreApi(
						wcStripeECE.getAttributes().data
				  )
				: [];

			// Clear the cart, so items that are currently in it
			//  do not interfere with computed totals.
			// Use the non-StoreAPI method as it is faster; Stripe requires
			// the click event to be resolved within 1 second.
			await api.expressCheckoutEmptyCartLegacy( emptyCartParams );

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
		 * @param {PaymentResponse} payment      Payment response instance.
		 * @param {string}          message      Error message to display.
		 * @param {boolean}         isOrderError Whether the error is related to the order creation.
		 */
		abortPayment: ( payment, message, isOrderError = false ) => {
			if ( ! isOrderError ) {
				payment.paymentFailed( { reason: 'fail' } );
			}
			onAbortPaymentHandler( payment, message );

			displayExpressCheckoutNotice( message, 'error' );
		},

		// Refresh the cached product params (the page-load bootstrap data) and
		// the element amount from a Store API cart response. No-op for
		// responses without totals (legacy/bookings) and off product pages.
		refreshTotalsFromCart: ( cart ) => {
			if ( ! cart?.totals || ! getExpressCheckoutData( 'product' ) ) {
				return;
			}

			const amount = transformCartTotalAmount( cart.totals );

			// The selection decides whether an address is needed (a virtual
			// variation must not prompt), so the cart's verdict replaces the
			// parent-product flag the page loaded with.
			if ( typeof cart.needs_shipping === 'boolean' ) {
				getExpressCheckoutData( 'product' ).requestShipping =
					cart.needs_shipping;
			}

			wcStripeECE.refreshTotals( {
				total: {
					...getExpressCheckoutData( 'product' ).total,
					amount,
					pending: false,
				},
				displayItems: transformCartDataForDisplayItems( cart ),
			} );
			wcStripeECE.updateExpressCheckoutAmount( amount );
		},

		// Keep the cached product breakdown in sync with the latest server response so the
		// click-time wallet line items match the selected variation/quantity.
		refreshTotals: ( response ) => {
			const product = getExpressCheckoutData( 'product' );
			product.total = response.total;
			product.displayItems = response.displayItems;
		},

		// Every mounted express button has its own Elements group, so the amount
		// has to be pushed to all of them. Updating only one leaves the others at
		// the previous amount, and the wallet then rejects the click because the
		// refreshed line items exceed that stale amount.
		updateExpressCheckoutAmount: ( amount ) => {
			( wcStripeECE.expressCheckoutElements ?? [] ).forEach(
				( elements ) => elements.update( { amount } )
			);
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
		getExpressCheckoutData( 'is_cart_page' ) ||
		getExpressCheckoutData( 'is_change_payment_method' )
	) {
		wcStripeECE.init();
	}

	// Warm the on-demand nonce bundle at the first sign of intent so wallet
	// event handlers (tight resolve deadlines) don't pay the round trip.
	const eceContainer = document.getElementById(
		'wc-stripe-express-checkout-element'
	);
	if ( eceContainer ) {
		[ 'pointerenter', 'touchstart', 'focusin' ].forEach( ( eventName ) =>
			eceContainer.addEventListener(
				eventName,
				// Warm-up is best-effort: a failed prefetch rejects (and clears
				// the memo so the real interaction retries), so swallow it here.
				() => api.expressCheckoutFetchNonces().catch( () => {} ),
				{ once: true, passive: true }
			)
		);
	}

	// We need to refresh ECE data when total is updated.
	$( document.body ).on( 'updated_cart_totals', () => {
		wcStripeECE.init();
	} );

	// We need to refresh ECE data when total is updated.
	$( document.body ).on( 'updated_checkout', () => {
		wcStripeECE.init();
	} );
} );
