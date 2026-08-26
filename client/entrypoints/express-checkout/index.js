/* global wcStripeExpressCheckoutPayForOrderParams */
/* global wc_stripe_express_checkout_params */

import { debounce } from 'lodash';
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
	isManualPaymentMethodCreation,
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

	let wcStripeECEError = '';
	const defaultErrorMessage = __(
		'There was an error getting the product information.',
		'woocommerce-gateway-stripe'
	);

	// Snapshot is first-paint only; re-inits reconcile via AJAX (see init() below).
	let cartBootstrapConsumed = false;

	const hasVariationForm = $( '.variations_form' ).length > 0;
	const hasBookingForm = $( '.wc-bookings-booking-form' ).length > 0;

	// Variable and booking products keep the legacy display-item format: their
	// product-page preview comes from `get_selected_product_data`, which has no
	// Store API equivalent. Add-to-cart routing is handled separately in `addToCart()`.
	const useLegacyDisplayItems = hasVariationForm || hasBookingForm;

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

		// The fast-path on variation/qty change updates only the element amount,
		// not this click closure, so read the latest items from the store to keep
		// the wallet breakdown in sync. Legacy (variable/booking) format only.
		const displayItems =
			useLegacyDisplayItems && getExpressCheckoutData( 'is_product_page' )
				? getExpressCheckoutData( 'product' )?.displayItems ??
				  options.displayItems
				: options.displayItems;

		const clickOptions = {
			lineItems: useLegacyDisplayItems
				? normalizeLineItems( displayItems )
				: displayItems,
			emailRequired: true,
			shippingAddressRequired: options.requestShipping,
			phoneNumberRequired: options.requestPhone,
			...( options.requestShipping && {
				shippingRates:
					options.shippingRates?.length > 0
						? options.shippingRates
						: getDefaultShippingRates(),
			} ),
			...( options.requestShipping &&
				Array.isArray( allowedShippingCountries ) && {
					allowedShippingCountries,
				} ),
		};

		// The wallet sheet opens once this resolves; cart/checkout refreshes
		// must not tear the buttons down underneath it (see the update-event
		// guard at the bottom of this file).
		wcStripeECE.paymentInFlight = true;
		return event.resolve( clickOptions );
	};

	// Shared between the initial render and in-place refreshes so a refreshed
	// wallet sheet derives its rates from the same items as the first paint.
	const getShippingRatesForOptions = ( options ) => {
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
				displayName: useLegacyDisplayItems ? i.label ?? i.name : i.name,
			} ) );
	};

	// Check if the product is waiting for a variation to be selected.
	const isVariationSelectionNeeded = () => {
		// This check only makes sense on the product page.
		const isProductPage = getExpressCheckoutData( 'is_product_page' );
		if ( ! isProductPage ) {
			return false;
		}

		const isVariationProduct = document.querySelector(
			'.single_variation_wrap'
		);
		const variationId = document.querySelector(
			'input[name="variation_id"]'
		)?.value;
		const variationSelected = variationId && variationId !== '0';
		return isVariationProduct && ! variationSelected;
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

		productHasDepositOption() {
			return !! $( 'form' ).has(
				'input[name=wc_deposit_option],input[name=wc_deposit_payment_plan]'
			).length;
		},

		// Mounted Elements groups, with the inputs that decide whether they can
		// be refreshed in place on a cart/checkout update.
		mountedGroups: [],

		// True while the wallet sheet is open (set when a click resolves,
		// cleared on cancel/abort/complete); refreshes are deferred meanwhile.
		paymentInFlight: false,
		pendingRefresh: false,

		// Only currency and the shipping requirement can't be changed through
		// elements.update(); anything else refreshes in place.
		getStructuralSignature: ( options ) =>
			JSON.stringify( {
				currency: options.currency,
				requestShipping: !! options.requestShipping,
			} ),

		teardownExpressCheckout: () => {
			wcStripeECE.mountedGroups.forEach( ( group ) => {
				try {
					group.button?.destroy();
				} catch ( error ) {
					// The button may already be gone (e.g. WooCommerce replaced
					// the surrounding markup); a stale reference must not block
					// the rebuild.
				}
				$(
					`#wc-stripe-express-checkout-element-${ group.type }`
				).remove();
			} );
			wcStripeECE.mountedGroups = [];
		},

		/**
		 * Refreshes the mounted buttons in place when only amounts/items
		 * changed, falling back to a full rebuild when a structural input
		 * changed or nothing is mounted. Reuse skips the /v1/elements/sessions
		 * call and the wallet availability re-probe a rebuild would repeat on
		 * every cart/checkout update.
		 *
		 * @param {Object} options ECE options (same shape as startExpressCheckout).
		 */
		refreshOrStartExpressCheckout: ( options ) => {
			const signature = wcStripeECE.getStructuralSignature( options );
			const groups = wcStripeECE.mountedGroups;
			const canReuse =
				groups.length > 0 &&
				groups.every(
					( group ) => group.button && group.signature === signature
				);

			if ( ! canReuse ) {
				wcStripeECE.startExpressCheckout( options );
				return;
			}

			const shippingRates = getShippingRatesForOptions( options );
			groups.forEach( ( group ) => {
				// The click/confirm closures read this same options object, so
				// mutate it in place to keep the wallet sheet contents in sync
				// with the refreshed cart.
				Object.assign( group.options, options, { shippingRates } );
				group.elements.update( { amount: options.total } );

				// WooCommerce re-renders the cart totals wholesale on
				// updated_cart_totals, discarding the button markup; remount
				// the existing button instead of paying for a new group.
				const container = document.getElementById(
					`wc-stripe-express-checkout-element-${ group.type }`
				);
				if (
					( ! container || ! container.childElementCount ) &&
					group.available !== false
				) {
					wcStripeECE.remountGroupButton( group );
				}
			} );

			// A zero-total update may have hidden the buttons; only groups that
			// reported availability justify re-showing the section.
			if ( groups.some( ( group ) => group.available ) ) {
				wcStripeECE.show();
				wcStripeECE.getButtonSeparator().show();
			}
		},

		remountGroupButton: ( group ) => {
			if ( ! $( '#wc-stripe-express-checkout-element' ).length ) {
				return;
			}
			const containerName = `wc-stripe-express-checkout-element-${ group.type }`;
			if ( ! $( `#${ containerName }` ).length ) {
				$( '#wc-stripe-express-checkout-element' ).append(
					`<div id="${ containerName }"></div>`
				);
			}
			// unmount() first: the element may still reference the discarded
			// markup. Event listeners were bound at creation and survive a
			// remount, so renderButton() (which would rebind them) is not used.
			group.button.unmount();
			group.button.mount( `#${ containerName }` );
		},

		/**
		 * Starts the Express Checkout Element
		 *
		 * @param {Object} options ECE options.
		 */
		startExpressCheckout: ( options ) => {
			const shippingRates = getShippingRatesForOptions( options );

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

			// Tear down what a previous render mounted: dropping the old
			// buttons unreferenced leaks their wallet iframes and stacks
			// duplicate buttons in the containers.
			wcStripeECE.teardownExpressCheckout();

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
				const addToCartButton = document.querySelector(
					'.single_add_to_cart_button'
				);

				// First check if product can be added to cart.
				if ( addToCartButton.classList.contains( 'disabled' ) ) {
					const defaultMessage = __(
						'Please select your product options before proceeding.',
						'woocommerce-gateway-stripe'
					);
					let message;
					if (
						addToCartButton.classList.contains(
							'wc-variation-is-unavailable'
						)
					) {
						message =
							getAddToCartVariationParams(
								'i18n_unavailable_text'
							) ||
							__(
								'Sorry, this product is unavailable. Please choose a different combination.',
								'woocommerce-gateway-stripe'
							);
					}

					// eslint-disable-next-line no-alert
					window.alert( message || defaultMessage );
					return;
				}

				if ( wcStripeECEError ) {
					// eslint-disable-next-line no-alert
					window.alert( wcStripeECEError );
					return;
				}

				// Stripe requires event.resolve() to be called within 1s of the click event.
				// Here, we enforce a timeout for the addToCart operation. If the operation
				// takes longer, we will call event.resolve() immediately,
				// and wait for the addToCart operation to finish after.
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
					// Immediately resolve the click event to avoid the 1s timeout.
					resolveClickEvent( event, clickOptions );

					// Wait for the addToCart operation to finish, checking
					// that the product was successfully added to the cart.
					wcStripeECE.isAddToCartSuccessful = false;
					const response = await addToCartPromise;
					const isAddToCartSuccessful = response?.items_count > 0;
					const isLegacyAddToCartSuccessful =
						response?.result === 'success';
					if (
						isAddToCartSuccessful ||
						isLegacyAddToCartSuccessful
					) {
						wcStripeECE.isAddToCartSuccessful = true;
					}

					return;
				}

				wcStripeECE.isAddToCartSuccessful = true;
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

			// Registered before button creation so a failure below can't leave
			// an untracked group behind: teardown iterates this registry.
			const group = {
				type: expressPaymentType,
				elements,
				options,
				signature: wcStripeECE.getStructuralSignature( options ),
				button: null,
				available: null,
			};
			wcStripeECE.mountedGroups.push( group );

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

			group.button = eceButton;

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
				wcStripeECE.paymentAborted = true;
				wcStripeECE.paymentInFlight = false;
				onCancelHandler();
				// A cart/checkout update arrived while the sheet was open;
				// apply it now that touching the buttons is safe again.
				if ( wcStripeECE.pendingRefresh ) {
					wcStripeECE.pendingRefresh = false;
					wcStripeECE.init();
				}
			} );

			eceButton.on( 'ready', ( onReadyParams ) => {
				group.available =
					!! onReadyParams.availablePaymentMethods &&
					Object.values(
						onReadyParams.availablePaymentMethods
					).filter( Boolean ).length > 0;
				if ( ! isVariationSelectionNeeded() && group.available ) {
					wcStripeECE.show();
					wcStripeECE.getButtonSeparator().show();
				}
			} );

			if ( getExpressCheckoutData( 'is_product_page' ) ) {
				wcStripeECE.attachProductPageEventListeners();
			}
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

					// After initializing a new express checkout button, we need to reset the paymentAborted flag.
					wcStripeECE.paymentAborted = false;
					return;
				}

				api.expressCheckoutGetCartDetails().then( ( cart ) => {
					const total = transformPrice(
						parseInt( cart.totals.total_price, 10 ) -
							parseInt( cart.totals.total_refund || 0, 10 ),
						cart.totals
					);

					if (
						total === 0 &&
						! getExpressCheckoutData( 'has_free_trial' )
					) {
						wcStripeECE.hide();
						return;
					}

					wcStripeECE.refreshOrStartExpressCheckout( {
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
			data.variation = hasVariationForm
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
			wcStripeECE.paymentInFlight = false;
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
			wcStripeECE.paymentInFlight = false;
			if ( ! isOrderError ) {
				payment.paymentFailed( { reason: 'fail' } );
			}
			onAbortPaymentHandler( payment, message );

			displayExpressCheckoutNotice( message, 'error' );
		},

		attachProductPageEventListeners: () => {
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
					if ( isVariationSelectionNeeded() ) {
						wcStripeECE.hide();
						return;
					}

					wcStripeECE.blockExpressCheckoutButton();

					$.when( wcStripeECE.getSelectedProductData() )
						.then( ( response ) => {
							if ( response.error ) {
								wcStripeECE.hide();
							} else {
								const isDeposits =
									wcStripeECE.productHasDepositOption();
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
									// Refresh stored items so the click breakdown matches this variation.
									wcStripeECE.refreshTotals( response );
									wcStripeECE.updateExpressCheckoutAmount(
										response.total.amount
									);
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

			$( document.body )
				.off( 'woocommerce_update_variation_values' )
				.on( 'woocommerce_update_variation_values', () => {
					if ( isVariationSelectionNeeded() ) {
						wcStripeECE.hide();
					}
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
										// Refresh stored items so the click breakdown matches the new qty.
										wcStripeECE.refreshTotals( response );
										wcStripeECE.updateExpressCheckoutAmount(
											response.total.amount
										);
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
			wcStripeECE.refreshTotals( response );
			wcStripeECE.init();
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

	// Refresh ECE data when totals update. A single recalculation can emit a
	// burst of these events, so coalesce them into one cart fetch; the leading
	// call keeps the common single-event case immediate.
	const refreshExpressCheckout = debounce(
		() => {
			// Never rebuild while the wallet sheet is open: destroying the
			// button mid-payment kills the shopper's session. The refresh is
			// applied when the sheet closes (see the cancel handler).
			if ( wcStripeECE.paymentInFlight ) {
				wcStripeECE.pendingRefresh = true;
				return;
			}
			wcStripeECE.init();
		},
		300,
		{ leading: true, trailing: true }
	);

	$( document.body ).on( 'updated_cart_totals', refreshExpressCheckout );
	$( document.body ).on( 'updated_checkout', refreshExpressCheckout );
} );
