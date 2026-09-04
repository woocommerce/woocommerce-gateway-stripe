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
	// bootstrap params and legacy add-to-cart responses carry labeled items
	// from `build_display_items()`. Add-to-cart routing is handled separately
	// in `addToCart()`.
	const useLegacyDisplayItems = hasVariationUi || hasBookingForm;

	const resolveClickEvent = ( event, options ) => {
		const getDefaultShippingRates = () => {
			// Return a default shipping option when shipping is required but no rates are provided
			const defaultShippingOption =
				getExpressCheckoutData( 'checkout' )?.default_shipping_option;
			// Stripe requires a rate once an address is required; real rates
			// replace this placeholder at shippingaddresschange.
			return defaultShippingOption
				? [ defaultShippingOption ]
				: [
						{
							id: 'pending',
							displayName: __(
								'Pending',
								'woocommerce-gateway-stripe'
							),
							amount: 0,
						},
				  ];
		};
		const allowedShippingCountries = getExpressCheckoutData(
			'allowed_shipping_countries'
		);

		// Product pages: the click handler writes cart-derived items into
		// the cached params before resolving, so read them from there for
		// every product type.
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

	// The selection a late-settled add left in the cart; a matching retry
	// resolves without re-adding. Never set on the fast path, which
	// re-prices every click to stay fresh.
	let cartSelectionKey = null;

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

		renderButton: ( eceButton, expressPaymentType, mountTarget ) => {
			// UX-experiment path: mount into an arbitrary container (the retry
			// modal) instead of the standard express-checkout element area.
			if ( mountTarget ) {
				eceButton.mount( mountTarget );
				return;
			}

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
			// Only Store API refusals (code + message) carry a shopper-facing
			// message; anything else gets the generic one.
			const addToCartFailureMessage = ( error ) =>
				error?.code && error?.message
					? error.message
					: __(
							'There was an error adding the product to the cart.',
							'woocommerce-gateway-stripe'
					  );

			// alert() pauses this page's event loop, which would also freeze
			// the wallet-UI dismissal that reject() queues for methods opening
			// on the raw gesture (e.g. Amazon Pay) — leaving the sheet on
			// screen behind the alert. Yield so the dismissal lands first.
			const promptAfterWalletDismissal = ( message ) =>
				setTimeout( () => {
					// eslint-disable-next-line no-alert
					window.alert( message );
				}, 100 );

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

					promptAfterWalletDismissal( message || defaultMessage );
					return;
				}

				const request = wcStripeECE.buildAddToCartRequest();
				const selectionKey = JSON.stringify( request );

				// The cart already holds this selection (a late-settled
				// add): resolve from the cart-derived params, no re-add.
				if ( cartSelectionKey === selectionKey ) {
					wcStripeECE.isAddToCartSuccessful = true;
					return resolveClickEvent( event, clickOptions );
				}
				cartSelectionKey = null;

				// Stripe requires resolve()/reject() within 1s of the click, so
				// the cart request gets a 700ms budget - the margin also has
				// to absorb main-thread scheduling between our resolve() and
				// Stripe's frame receiving it. The timer winning the race
				// doesn't mean the request failed — it's still in flight,
				// just too slow for this click's deadline.
				const addToCartPromise = wcStripeECE.addToCart( request );
				const timeout = new Promise( ( resolve ) =>
					setTimeout( () => {
						resolve( 'timeout' );
					}, 700 )
				);
				let result;
				try {
					result = await Promise.race( [
						addToCartPromise,
						timeout,
					] );
				} catch ( error ) {
					event.reject?.();
					wcStripeECE.isAddToCartSuccessful = false;
					promptAfterWalletDismissal(
						addToCartFailureMessage( error )
					);
					return;
				}
				if ( result === 'timeout' ) {
					// Opening the sheet now would show a preview amount the cart
					// may not match, so reject the click and hand off to the
					// retry modal: it holds the shopper with a loading state
					// while the pending add settles, then offers a fresh wallet
					// button primed with the settled cart data.
					event.reject?.();
					wcStripeECE.showRetryModal();
					wcStripeECE.blockExpressCheckoutButton();
					try {
						// Waiting for the pending mutation keeps a retry from
						// overlapping it, but a request the browser hangs onto
						// would keep the button blocked indefinitely - after a
						// generous bound, treat the attempt as failed. A stray
						// late add can't double-charge: every click empties
						// the cart first and prices from its own response.
						const response = await Promise.race( [
							addToCartPromise,
							new Promise( ( resolve ) =>
								setTimeout(
									() => resolve( 'abandoned' ),
									30000
								)
							),
						] );
						if ( response === 'abandoned' ) {
							wcStripeECE.isAddToCartSuccessful = false;
							wcStripeECE.setRetryModalError(
								__(
									'We could not prepare your payment. Please check your internet connection and try again.',
									'woocommerce-gateway-stripe'
								)
							);
						} else {
							wcStripeECE.isAddToCartSuccessful =
								response?.items_count > 0 ||
								response?.result === 'success';
							// Record only when the cached params hold
							// this response's cart data.
							if (
								wcStripeECE.refreshTotalsFromCart( response ) &&
								wcStripeECE.isAddToCartSuccessful
							) {
								cartSelectionKey = selectionKey;
								wcStripeECE.setRetryModalReady(
									event.expressPaymentType
								);
							} else {
								wcStripeECE.setRetryModalError(
									addToCartFailureMessage( response )
								);
							}
						}
					} catch ( error ) {
						wcStripeECE.isAddToCartSuccessful = false;
						// The click was already rejected at the deadline;
						// still explain why a retry won't work.
						wcStripeECE.setRetryModalError(
							addToCartFailureMessage( error )
						);
					} finally {
						wcStripeECE.unblockExpressCheckoutButton();
					}

					return;
				}

				wcStripeECE.isAddToCartSuccessful = true;
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

			wcStripeECE.renderButton(
				eceButton,
				expressPaymentType,
				options.mountTarget
			);

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
				// The wallet has handed off; "your order is ready" would now
				// mislead for however long the server takes to confirm.
				if ( options.mountTarget ) {
					wcStripeECE.setRetryModalProcessing();
				}
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
				// A sheet opened from the retry modal has served its purpose;
				// dismissing it should hand the page back, not strand the
				// shopper behind the modal backdrop. The main buttons stay
				// primed for an instant retry.
				if ( options.mountTarget ) {
					wcStripeECE.closeRetryModal();
				}
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
		 * Builds the add-to-cart request from the current page state. Also
		 * serves as the retry-priming key material, so the key cannot drift
		 * from what reaches the cart.
		 *
		 * @return {{usesLegacyEndpoint: boolean, emptyCartParams: Object, data: Object}}
		 *         The endpoint routing flag, the empty-cart parameters, and
		 *         the request body.
		 */
		buildAddToCartRequest: () => {
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

				if ( ! bookingConfiguration ) {
					data.product_id = productId;
					data.attributes = wcStripeECE.getAttributes().data;
					return { usesLegacyEndpoint: true, emptyCartParams, data };
				}

				data.id = productId;
				data.booking_configuration = bookingConfiguration;
				return { usesLegacyEndpoint: false, emptyCartParams, data };
			}

			data.id = productId;

			// Variable products: `productId` is the parent id, so pass the chosen
			// attributes for the Store API to resolve the variation (incl. "any" attributes).
			data.variation = hasVariationUi
				? transformVariationAttributesForStoreApi(
						wcStripeECE.getAttributes().data
				  )
				: [];

			return { usesLegacyEndpoint: false, emptyCartParams, data };
		},

		/**
		 * Adds the item to the cart and returns cart details.
		 *
		 * @param {Object}  request                    The built request; defaults
		 *                                             to building one from the
		 *                                             current page state.
		 * @param {boolean} request.usesLegacyEndpoint Route to the legacy
		 *                                             endpoint.
		 * @param {Object}  request.emptyCartParams    Parameters for the cart clear.
		 * @param {Object}  request.data               The request body.
		 * @return {Promise} Promise for the request to the server.
		 */
		addToCart: async (
			{
				usesLegacyEndpoint,
				emptyCartParams,
				data,
			} = wcStripeECE.buildAddToCartRequest()
		) => {
			// Clear the cart (with the booking id where applicable), so items
			// currently in it do not interfere with computed totals. Use the
			// non-StoreAPI method as it is faster; Stripe requires the click
			// event to be resolved within 1 second.
			await api.expressCheckoutEmptyCartLegacy( emptyCartParams );

			return usesLegacyEndpoint
				? api.expressCheckoutAddToCartLegacy( data )
				: api.expressCheckoutAddToCart( data );
		},

		/**
		 * Complete payment.
		 *
		 * @param {string} url Order thank you page URL.
		 */
		completePayment: ( url ) => {
			// The redirect takes a beat to land; drop the retry modal so the
			// shopper sees the page's blocked/loading state instead of a stale
			// "your order is ready" prompt. No-op when no modal is open.
			wcStripeECE.closeRetryModal();
			onCompletePaymentHandler( url );
			window.location = url;
		},

		/**
		 * Abort the payment and display error messages.
		 *
		 * @param {PaymentResponse} payment Payment response instance.
		 * @param {string}          message Error message to display.
		 */
		abortPayment: ( payment, message ) => {
			onAbortPaymentHandler( payment, message );
			// The error notice renders on the page, which the retry modal's
			// backdrop would otherwise cover — hand the page back so the
			// shopper can actually read it. No-op when no modal is open.
			wcStripeECE.closeRetryModal();
			displayExpressCheckoutNotice( message, 'error' );

			// The wallet sheet only closes once the confirm event gets a terminal
			// result, so order errors must fail it too. A late call rejects an
			// internal Stripe promise asynchronously, after the message is shown.
			payment.paymentFailed( { reason: 'fail' } );
		},

		/**
		 * Refreshes the cached product params and the element amount from an
		 * add-to-cart response. Both shapes are cart-computed: Store API, and
		 * legacy (`build_display_items()`, amounts already in minor units).
		 *
		 * @param {Object} cart The add-to-cart response.
		 * @return {boolean} Whether cart data was applied; false off product
		 *                   pages or for unrecognized shapes.
		 */
		refreshTotalsFromCart: ( cart ) => {
			if ( ! getExpressCheckoutData( 'product' ) ) {
				return false;
			}

			if ( cart?.totals ) {
				const amount = transformCartTotalAmount( cart.totals );

				// The selection decides whether an address is needed (a
				// virtual variation must not prompt), so the cart's verdict
				// replaces the parent-product flag the page loaded with.
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
				return true;
			}

			if ( typeof cart?.total?.amount === 'number' ) {
				// Legacy shape (bookings and their fallbacks). The labeled
				// display items normalize at resolve time; no shipping flag
				// is carried, so the creation-time one stands.
				wcStripeECE.refreshTotals( {
					total: { ...cart.total, pending: false },
					displayItems: cart.displayItems ?? [],
				} );
				wcStripeECE.updateExpressCheckoutAmount( cart.total.amount );
				return true;
			}

			return false;
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

		// ---- Retry modal for a timed-out express click. ----
		// Wallet sheets only open from a genuine gesture on a Stripe-rendered
		// button (ECE has no programmatic show()), so the modal's CTA must be
		// a real express-checkout button mounted inside the modal, restricted
		// to the wallet the shopper originally clicked. Its click re-enters
		// handleProductPageECEButtonClick and resolves instantly off the
		// primed cartSelectionKey.

		// The <dialog> node while the modal is open, else null.
		retryModal: null,

		// While Stripe is confirming, the modal must not close: removing it
		// would detach the active element's frame mid-payment.
		isRetryModalProcessing: false,

		retryModalPart: ( className ) =>
			wcStripeECE.retryModal?.querySelector( `.${ className }` ),

		showRetryModal: () => {
			wcStripeECE.closeRetryModal();

			const dialog = document.createElement( 'dialog' );
			dialog.id = 'wc-stripe-ece-retry-modal';
			dialog.className = 'wc-stripe-ece-retry-modal';
			dialog.setAttribute(
				'aria-labelledby',
				'wc-stripe-ece-retry-modal-title'
			);

			const close = document.createElement( 'button' );
			close.type = 'button';
			close.className = 'wc-stripe-ece-retry-modal__close';
			close.setAttribute(
				'aria-label',
				__( 'Close', 'woocommerce-gateway-stripe' )
			);
			close.textContent = '×';
			close.addEventListener( 'click', () =>
				wcStripeECE.closeRetryModal()
			);

			const title = document.createElement( 'h2' );
			title.id = 'wc-stripe-ece-retry-modal-title';
			title.className = 'wc-stripe-ece-retry-modal__title';
			title.textContent = __(
				'Preparing your payment…',
				'woocommerce-gateway-stripe'
			);

			const message = document.createElement( 'p' );
			message.className = 'wc-stripe-ece-retry-modal__message';
			message.textContent = __(
				'This is taking a little longer than usual. Hang tight while we get your order ready.',
				'woocommerce-gateway-stripe'
			);

			const spinner = document.createElement( 'div' );
			spinner.className = 'wc-stripe-ece-retry-modal__spinner';

			const buttonHost = document.createElement( 'div' );
			buttonHost.id = 'wc-stripe-ece-retry-modal-button';
			buttonHost.className = 'wc-stripe-ece-retry-modal__button';

			dialog.append( close, title, message, spinner, buttonHost );

			// Esc closes via the dialog's native cancel event; block it only
			// while a confirmation is in flight.
			dialog.addEventListener( 'cancel', ( cancelEvent ) => {
				if ( wcStripeECE.isRetryModalProcessing ) {
					cancelEvent.preventDefault();
					return;
				}
				wcStripeECE.retryModal = null;
				dialog.remove();
			} );

			document.body.appendChild( dialog );
			// showModal() puts the dialog in the top layer (above any theme
			// z-index) with focus trapping; jsdom either lacks it or stubs it
			// to throw, so fall back to the open attribute there.
			try {
				dialog.showModal();
			} catch ( e ) {
				dialog.setAttribute( 'open', '' );
			}

			wcStripeECE.retryModal = dialog;
			wcStripeECE.isRetryModalProcessing = false;
		},

		closeRetryModal: () => {
			const dialog =
				wcStripeECE.retryModal ??
				document.getElementById( 'wc-stripe-ece-retry-modal' );
			wcStripeECE.retryModal = null;
			wcStripeECE.isRetryModalProcessing = false;
			if ( ! dialog ) {
				return;
			}
			// close() restores focus to the element focused before showModal().
			if ( dialog.open && typeof dialog.close === 'function' ) {
				dialog.close();
			}
			dialog.remove();
		},

		setRetryModalReady: ( clickedExpressPaymentType ) => {
			// The shopper closed the modal while waiting; the primed
			// cartSelectionKey still makes the main button resolve instantly.
			if ( ! wcStripeECE.retryModal ) {
				return;
			}

			// The click event reports the wallet in snake_case; element
			// creation uses the camelCase settings identifiers.
			const settingType = {
				apple_pay: EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
				google_pay: EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY,
				amazon_pay: EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY,
				link: EXPRESS_PAYMENT_METHOD_SETTING_LINK,
			}[ clickedExpressPaymentType ];

			if ( ! settingType ) {
				wcStripeECE.setRetryModalError(
					__(
						'This payment method is unavailable. Please try again.',
						'woocommerce-gateway-stripe'
					)
				);
				return;
			}

			wcStripeECE.retryModalPart(
				'wc-stripe-ece-retry-modal__spinner'
			).style.display = 'none';
			wcStripeECE.retryModalPart(
				'wc-stripe-ece-retry-modal__title'
			).textContent = __(
				'Your order is ready',
				'woocommerce-gateway-stripe'
			);
			wcStripeECE.retryModalPart(
				'wc-stripe-ece-retry-modal__message'
			).textContent = __(
				'Tap the button below to complete your purchase.',
				'woocommerce-gateway-stripe'
			);

			const product = getExpressCheckoutData( 'product' );
			wcStripeECE.createExpressCheckoutElement( settingType, {
				total: product.total.amount,
				currency: product.currency,
				requestShipping: product.requestShipping ?? false,
				requestPhone:
					getExpressCheckoutData( 'checkout' )?.needs_payer_phone ??
					false,
				displayItems: product.displayItems,
				shippingRates: product.shippingOptions ?? [],
				mountTarget: '#wc-stripe-ece-retry-modal-button',
			} );
		},

		setRetryModalProcessing: () => {
			if ( ! wcStripeECE.retryModal ) {
				return;
			}
			wcStripeECE.isRetryModalProcessing = true;
			wcStripeECE.retryModalPart(
				'wc-stripe-ece-retry-modal__spinner'
			).style.display = '';
			wcStripeECE.retryModalPart(
				'wc-stripe-ece-retry-modal__title'
			).textContent = __(
				'Processing your payment…',
				'woocommerce-gateway-stripe'
			);
			wcStripeECE.retryModalPart(
				'wc-stripe-ece-retry-modal__message'
			).textContent = '';
			// Keep the element mounted — Stripe may still need its frame to
			// finish the confirmation — but take it out of sight, and remove
			// the close button so the shopper cannot detach it either.
			const buttonHost = wcStripeECE.retryModalPart(
				'wc-stripe-ece-retry-modal__button'
			);
			buttonHost.style.visibility = 'hidden';
			buttonHost.style.height = '0';
			buttonHost.style.minHeight = '0';
			wcStripeECE.retryModalPart(
				'wc-stripe-ece-retry-modal__close'
			).style.display = 'none';
		},

		setRetryModalError: ( message ) => {
			if ( ! wcStripeECE.retryModal ) {
				return;
			}
			wcStripeECE.isRetryModalProcessing = false;
			wcStripeECE.retryModalPart(
				'wc-stripe-ece-retry-modal__spinner'
			).style.display = 'none';
			wcStripeECE.retryModalPart(
				'wc-stripe-ece-retry-modal__close'
			).style.display = '';
			wcStripeECE.retryModalPart(
				'wc-stripe-ece-retry-modal__title'
			).textContent = __(
				'Something went wrong',
				'woocommerce-gateway-stripe'
			);
			wcStripeECE.retryModalPart(
				'wc-stripe-ece-retry-modal__message'
			).textContent = message;
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
