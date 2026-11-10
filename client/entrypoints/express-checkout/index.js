/* global wcStripeExpressCheckoutPayForOrderParams */
/* global wc_stripe_express_checkout_params */

import { debounce } from 'lodash';
import jQuery from 'jquery';
import WCStripeAPI from '../../api';
import { rebuildWalletContainer } from 'wcstripe/express-checkout/utils/rebuild-wallet-container';
import { getStripeServerData } from 'wcstripe/stripe-utils/get-stripe-server-data';
import { __ } from '@wordpress/i18n';
import {
	displayExpressCheckoutNotice,
	displayLoginConfirmation,
	getDefaultShippingOptions,
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
	observeVariationSelection,
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

const initializeExpressCheckout = ( $ ) => {
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
	let cartRevision = 0;
	let cartRefreshPending = false;
	let checkoutUpdating = false;
	let checkoutRefreshFailed = false;
	let containerObserver;
	const isClassicCheckout =
		getExpressCheckoutData( 'is_checkout_page' ) &&
		! getExpressCheckoutData( 'is_product_page' ) &&
		! getExpressCheckoutData( 'is_pay_for_order' ) &&
		! getExpressCheckoutData( 'is_change_payment_method' );

	// Classic template only — its `wc-add-to-cart-variation.js` script is what
	// fires the jQuery variation events.
	const hasClassicVariationForm = $( '.variations_form' ).length > 0;
	// Template-agnostic: true for a variable product on either template.
	const hasVariationUi = hasVariationSelectionUi();
	const hasBookingForm = $( '.wc-bookings-booking-form' ).length > 0;

	// Variable and booking products keep the legacy display-item format: their
	// product-page preview comes from `get_selected_product_data`, which has no
	// Store API equivalent. Add-to-cart routing is handled separately in `addToCart()`.
	const useLegacyDisplayItems = hasVariationUi || hasBookingForm;

	const resolveClickEvent = ( event, options ) => {
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
						: getDefaultShippingOptions(),
			} ),
			...( options.requestShipping &&
				Array.isArray( allowedShippingCountries ) && {
					allowedShippingCountries,
				} ),
		};

		// The wallet sheet opens on resolve, so defer refreshes until it closes.
		wcStripeECE.paymentInFlight = true;
		return event.resolve( clickOptions );
	};

	// Share shipping rates so initial render and refreshes use the same values.
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

		clearCheckoutRefreshError: () => {
			checkoutRefreshFailed = false;
			$( '#wc-stripe-express-checkout-refresh-error' ).remove();
		},

		failCheckoutRefresh: () => {
			checkoutRefreshFailed = true;
			cartRefreshPending = true;
			if ( wcStripeECE.paymentInFlight ) {
				wcStripeECE.pendingRefresh = true;
				return;
			}
			wcStripeECE.hide();
			$( '#wc-stripe-express-checkout-refresh-error' ).remove();
			const notice = $( '<div>', {
				id: 'wc-stripe-express-checkout-refresh-error',
				class: 'woocommerce-info',
				role: 'alert',
			} ).text(
				__(
					'Express checkout is unavailable because your order could not be updated. ',
					'woocommerce-gateway-stripe'
				)
			);
			$( '<button>', { type: 'button', class: 'button' } )
				.text( __( 'Retry', 'woocommerce-gateway-stripe' ) )
				.on( 'click', () =>
					$( document.body ).trigger( 'update_checkout' )
				)
				.appendTo( notice );
			notice.insertBefore( wcStripeECE.getElements() );
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

		// Mounted Elements groups and the inputs that decide reuse.
		mountedGroups: [],

		// True while the wallet sheet is open; refreshes are deferred meanwhile.
		paymentInFlight: false,
		pendingRefresh: false,

		// Reuse must apply visibility, mode and styling changes alongside fresh totals.
		getStructuralSignature: ( options ) => {
			const stripe = getExpressCheckoutData( 'stripe' );
			return JSON.stringify( {
				currency: options.currency,
				requestShipping: !! options.requestShipping,
				locale: stripe?.locale ?? 'en',
				hasFreeTrial: !! getExpressCheckoutData( 'has_free_trial' ),
				taxesBasedOnBilling: !! getExpressCheckoutData(
					'taxes_based_on_billing'
				),
				appearance: getExpressCheckoutButtonAppearance(),
				wallets: Object.entries( {
					[ EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY ]:
						stripe?.is_apple_pay_enabled,
					[ EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY ]:
						stripe?.is_google_pay_enabled,
					[ EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY ]:
						stripe?.is_amazon_pay_enabled,
					[ EXPRESS_PAYMENT_METHOD_SETTING_LINK ]:
						stripe?.is_link_enabled,
				} )
					.filter( ( [ , enabled ] ) => enabled )
					.map( ( [ type ] ) => ( {
						type,
						style: getExpressCheckoutButtonStyleSettings( type ),
						paymentMethodTypes:
							getPaymentMethodTypesForExpressMethod( type ),
					} ) ),
			} );
		},

		teardownExpressCheckout: () => {
			wcStripeECE.mountedGroups.forEach( ( group ) => {
				group.disposed = true;
				try {
					group.button?.destroy();
				} catch ( error ) {
					// A stale reference must not block the rebuild.
				}
				group.preparation?.remove();
				$(
					`#wc-stripe-express-checkout-element-${ group.type }`
				).remove();
			} );
			wcStripeECE.mountedGroups = [];
		},

		/**
		 * Refreshes the mounted buttons in place, rebuilding only when a
		 * structural input changed or nothing is mounted. Reuse skips the
		 * /v1/elements/sessions call and wallet re-probe of a rebuild.
		 *
		 * @param {Object} options ECE options (same shape as startExpressCheckout).
		 */
		refreshOrStartExpressCheckout: ( options ) => {
			const signature = wcStripeECE.getStructuralSignature( options );
			const groups = wcStripeECE.mountedGroups;
			const canReuse =
				groups.length > 0 &&
				groups.every(
					( group ) =>
						group.button &&
						! group.failed &&
						group.signature === signature
				);

			if ( ! canReuse ) {
				wcStripeECE.startExpressCheckout( options );
				return;
			}

			const shippingRates = getShippingRatesForOptions( options );
			groups.forEach( ( group ) => {
				// Click and confirm handlers share this object, so update it in place.
				Object.assign( group.options, options, { shippingRates } );
				const update = group.elements.update( {
					amount: options.total,
				} );
				if ( update && typeof update.then === 'function' ) {
					const token = {};
					group.pendingUpdate = token;
					update
						.then( () => {
							if ( group.pendingUpdate === token ) {
								group.pendingUpdate = null;
							}
						} )
						.catch( () => {
							if (
								! group.disposed &&
								group.pendingUpdate === token
							) {
								group.failed = true;
								wcStripeECE.failCheckoutRefresh();
							}
						} );
				}

				// Cart totals replacement discards the markup, so remount the existing buttons.
				const container = document.getElementById(
					`wc-stripe-express-checkout-element-${ group.type }`
				);
				if (
					! group.preparing &&
					( isClassicCheckout ||
						! container ||
						! container.childElementCount ) &&
					group.available !== false
				) {
					wcStripeECE.remountGroupButton( group );
				}
			} );

			// Only reveal the separator after a wallet is available, including after zero totals.
			if (
				! checkoutRefreshFailed &&
				groups.some( ( group ) => group.available )
			) {
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
			let nextSlot;
			let reorderFailed = false;
			if ( isClassicCheckout ) {
				// Readiness order must not change the merchant's wallet order.
				nextSlot = wcStripeECE.mountedGroups
					.slice( wcStripeECE.mountedGroups.indexOf( group ) + 1 )
					.map( ( item ) =>
						document.getElementById(
							`wc-stripe-express-checkout-element-${ item.type }`
						)
					)
					.find( Boolean );
				const slot = document.getElementById( containerName );
				if ( nextSlot && slot.nextElementSibling !== nextSlot ) {
					// Reinserting a populated wrapper restarts its iframe too.
					if ( typeof slot.parentElement.moveBefore === 'function' ) {
						try {
							slot.parentElement.moveBefore( slot, nextSlot );
						} catch ( error ) {
							// A rejected reorder needs the same SDK remount fallback as a rejected host move.
							reorderFailed = true;
						}
					} else if ( ! slot.querySelector( 'iframe' ) ) {
						nextSlot.before( slot );
					}
				}
				// Reserve each wallet's height so reused iframes cannot reveal initial growth.
				$( `#${ containerName }` ).css(
					'min-height',
					group.buttonHeight
				);
			}
			const container = document.getElementById( containerName );
			const host = group.mountHost ?? group.preparation;
			if (
				isClassicCheckout &&
				! reorderFailed &&
				rebuildWalletContainer( container, host )
			) {
				host.style.display = '';
				group.mountHost = host;
				group.preparation = null;
				return;
			}
			// Unsupported browsers and detached fragments require a real SDK remount.
			group.button.unmount();
			group.preparation?.remove();
			group.preparation = null;
			if ( isClassicCheckout ) {
				const replacement = container.cloneNode( false );
				container.replaceWith( replacement );
				if ( nextSlot ) {
					nextSlot.before( replacement );
				}
				group.mountHost = document.createElement( 'div' );
				group.mountHost.id = `wc-stripe-express-prepare-${ group.type }`;
				replacement.appendChild( group.mountHost );
				group.button.mount( `#${ group.mountHost.id }` );
			} else {
				group.button.mount( `#${ containerName }` );
			}
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

			// Destroy discarded buttons to avoid leaked iframes and duplicate wallets.
			wcStripeECE.teardownExpressCheckout();

			// Reset the registry so variation/qty updates only touch the buttons
			// mounted for this render.
			wcStripeECE.expressCheckoutElements = [];
			if ( ! expressPaymentTypes.length ) {
				wcStripeECE.hide();
			}

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

					// eslint-disable-next-line no-alert
					window.alert( message || defaultMessage );
					return;
				}

				if ( wcStripeECEError ) {
					// eslint-disable-next-line no-alert
					window.alert( wcStripeECEError );
					return;
				}

				if (
					wcStripeECE.previewSelectionKey !== null &&
					wcStripeECE.getSelectionKey() !==
						wcStripeECE.previewSelectionKey
				) {
					// The preview can't be refreshed within Stripe's 1s click
					// deadline, so catch it up for the next tap instead.
					wcStripeECE.onVariationChanged();
					// eslint-disable-next-line no-alert
					window.alert(
						__(
							'The price is being updated for your selection. Please try again in a moment.',
							'woocommerce-gateway-stripe'
						)
					);
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

			// Track the group before button creation so a failure can still clean it up.
			const group = {
				type: expressPaymentType,
				elements,
				options,
				signature: wcStripeECE.getStructuralSignature( options ),
				button: null,
				available: null,
				preparing: isClassicCheckout,
				preparation: null,
				disposed: false,
				failed: false,
			};
			wcStripeECE.mountedGroups.push( group );

			const buttonStyleSettings =
				getExpressCheckoutButtonStyleSettings( expressPaymentType );
			group.buttonHeight = buttonStyleSettings.buttonHeight;

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

			if ( ! isClassicCheckout ) {
				wcStripeECE.renderButton( eceButton, expressPaymentType );
			}

			eceButton.on( 'click', async function ( event ) {
				// Block wallet sheets until WooCommerce finishes recalculating stale totals.
				if (
					isClassicCheckout &&
					( cartRefreshPending ||
						group.disposed ||
						group.failed ||
						group.pendingUpdate )
				) {
					return;
				}

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
					isClassicCheckout &&
					( cartRefreshPending ||
						group.disposed ||
						group.failed ||
						group.pendingUpdate )
				) {
					return wcStripeECE.abortPayment(
						event,
						__(
							'Your cart has been updated. Please try again.',
							'woocommerce-gateway-stripe'
						)
					);
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
				wcStripeECE.paymentAborted = true;
				wcStripeECE.paymentInFlight = false;
				onCancelHandler();
				if ( checkoutRefreshFailed ) {
					wcStripeECE.failCheckoutRefresh();
					return;
				}
				// Apply an update deferred while the sheet was open.
				if ( wcStripeECE.pendingRefresh ) {
					wcStripeECE.pendingRefresh = false;
					wcStripeECE.init();
				}
			} );

			eceButton.on( 'ready', async ( onReadyParams ) => {
				if ( group.disposed || group.failed ) {
					return;
				}
				group.available =
					!! onReadyParams.availablePaymentMethods &&
					Object.values(
						onReadyParams.availablePaymentMethods
					).filter( Boolean ).length > 0;
				if ( isClassicCheckout ) {
					if ( ! group.available ) {
						group.preparing = false;
						group.button.unmount();
						group.preparation?.remove();
						group.preparation = null;
						$(
							`#wc-stripe-express-checkout-element-${ group.type }`
						).remove();
						if (
							! wcStripeECE.mountedGroups.some(
								( item ) => item.available
							)
						) {
							wcStripeECE.hide();
						}
						return;
					}
					if ( group.preparing ) {
						group.preparing = false;
						// Finish the SDK's initial ready dispatch before changing its mount.
						await Promise.resolve();
						if ( group.disposed || group.failed ) {
							return;
						}
						wcStripeECE.remountGroupButton( group );
					}
				}
				if (
					! checkoutRefreshFailed &&
					group.available &&
					( options.total > 0 ||
						hasFreeTrial ||
						isChangePaymentMethod ||
						getExpressCheckoutData( 'is_product_page' ) ||
						getExpressCheckoutData( 'is_pay_for_order' ) )
				) {
					wcStripeECE.show();
					wcStripeECE.getButtonSeparator().show();
				}
			} );

			if ( isClassicCheckout ) {
				const failPreparation = () => {
					if ( group.disposed ) {
						return;
					}
					group.failed = true;
					group.available = false;
					group.preparing = false;
					eceButton.unmount();
					group.preparation?.remove();
					group.preparation = null;
					$(
						`#wc-stripe-express-checkout-element-${ group.type }`
					).remove();
					if (
						! wcStripeECE.mountedGroups.some(
							( item ) => item.available
						)
					) {
						wcStripeECE.hide();
					}
				};
				eceButton.on( 'loaderror', failPreparation );
				// Keep each prepared wallet and its handlers outside the visible root until remount.
				group.preparation = document.createElement( 'div' );
				group.preparation.id = `wc-stripe-express-prepare-${ expressPaymentType }`;
				group.preparation.style.display = 'none';
				document.body.appendChild( group.preparation );
				try {
					eceButton.mount( `#${ group.preparation.id }` );
				} catch {
					failPreparation();
				}
			}

			if ( getExpressCheckoutData( 'is_product_page' ) ) {
				wcStripeECE.attachProductPageEventListeners();
			}
		},

		/**
		 * Initialize event handlers and UI state
		 */
		init: () => {
			if ( isClassicCheckout ) {
				if ( ! wcStripeECE.getElements().length ) {
					if ( ! containerObserver ) {
						// Late checkout insertion must not consume the initial cart snapshot.
						containerObserver = new MutationObserver( () => {
							if ( wcStripeECE.getElements().length ) {
								wcStripeECE.init();
							}
						} );
						containerObserver.observe( document.body, {
							childList: true,
							subtree: true,
						} );
					}
					return;
				}
				containerObserver?.disconnect();
				containerObserver = undefined;
			}
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
					requestPhone:
						getExpressCheckoutData( 'checkout' )
							?.needs_payer_phone ?? false,
					appearance: getExpressCheckoutButtonAppearance(),
					locale: getExpressCheckoutData( 'stripe' )?.locale ?? 'en',
					displayItems: transformLabeledDisplayItems(
						displayItems ?? [],
						total
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
				if ( checkoutUpdating || wcStripeECE.paymentInFlight ) {
					wcStripeECE.pendingRefresh = true;
					return;
				}
				wcStripeECE.pendingRefresh = false;
				const revision = ++cartRevision;
				const cartBootstrap = getExpressCheckoutData( 'cart' );

				// First paint renders from the snapshot, skipping GET /wc/store/v1/cart;
				// re-inits fall through to the AJAX path below for live cart updates.
				if (
					! cartBootstrapConsumed &&
					( ! isClassicCheckout || ! cartRefreshPending ) &&
					Number.isInteger( cartBootstrap?.total ) &&
					cartBootstrap.total >= 0 &&
					typeof cartBootstrap.currency === 'string' &&
					cartBootstrap.currency.length === 3
				) {
					cartBootstrapConsumed = true;
					cartRefreshPending = false;
					if (
						cartBootstrap.total === 0 &&
						! getExpressCheckoutData( 'has_free_trial' )
					) {
						wcStripeECE.hide();
						return;
					}

					wcStripeECE.startExpressCheckout( {
						total: cartBootstrap.total,
						currency: cartBootstrap.currency,
						requestShipping: cartBootstrap.requestShipping,
						requestPhone: cartBootstrap.requestPhone,
						displayItems: transformLabeledDisplayItems(
							cartBootstrap.displayItems ?? [],
							cartBootstrap.total
						),
					} );

					// After initializing a new express checkout button, we need to reset the paymentAborted flag.
					wcStripeECE.paymentAborted = false;
					return;
				}

				cartBootstrapConsumed = true;
				cartRefreshPending = true;
				api.expressCheckoutGetCartDetails()
					.then( ( cart ) => {
						// Discard stale responses without disrupting a wallet sheet opened during the fetch.
						if ( revision !== cartRevision ) {
							return;
						}
						if ( wcStripeECE.paymentInFlight ) {
							wcStripeECE.pendingRefresh = true;
							return;
						}

						const total = transformPrice(
							parseInt( cart.totals.total_price, 10 ) -
								parseInt( cart.totals.total_refund || 0, 10 ),
							cart.totals
						);
						wcStripeECE.clearCheckoutRefreshError();

						if (
							total === 0 &&
							! getExpressCheckoutData( 'has_free_trial' )
						) {
							wcStripeECE.mountedGroups.forEach( ( group ) => {
								group.options.total = 0;
							} );
							wcStripeECE.hide();
							cartRefreshPending = false;
							return;
						}

						wcStripeECE.refreshOrStartExpressCheckout( {
							total,
							currency:
								cart.totals.currency_code?.toLowerCase() ??
								getExpressCheckoutData( 'checkout' )
									?.currency_code,
							requestShipping: cart.needs_shipping === true,
							requestPhone:
								getExpressCheckoutData( 'checkout' )
									?.needs_payer_phone,
							displayItems:
								transformCartDataForDisplayItems( cart ),
						} );
						cartRefreshPending = false;
					} )
					.catch( () => {
						// Keep stale totals unpayable; the next checkout update retries.
						if ( revision === cartRevision ) {
							if ( isClassicCheckout ) {
								wcStripeECE.failCheckoutRefresh();
							} else if ( wcStripeECE.paymentInFlight ) {
								wcStripeECE.pendingRefresh = true;
							} else {
								wcStripeECE.hide();
							}
						}
					} );
			}

			// After initializing a new express checkout button, we need to reset the paymentAborted flag.
			wcStripeECE.paymentAborted = false;
		},

		getAttributes: () => getSelectedVariationAttributes(),

		// The selection the cached preview was priced for. Compared against the
		// form at click time: the blockified template's change signals are
		// inferred from shopper gestures, so a missed one must cost a retry,
		// never a sheet at a stale amount.
		previewSelectionKey: null,

		getSelectionKey: () =>
			JSON.stringify( wcStripeECE.getSelectedProductRequestData() ),

		getSelectedProductRequestData: () => {
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

			return {
				product_id: productId,
				qty: $( quantityInputSelector ).val(),
				attributes: hasVariationUi
					? wcStripeECE.getAttributes().data
					: [],
				addon_value: addonValue,
				...depositObject,
			};
		},

		getSelectedProductData: ( data ) =>
			api.expressCheckoutGetSelectedProductData(
				data ?? wcStripeECE.getSelectedProductRequestData()
			),

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
			wcStripeECE.paymentInFlight = false;
			onCompletePaymentHandler( url );
			window.location = url;
		},

		/**
		 * Abort the payment and display error messages.
		 *
		 * @param {PaymentResponse} payment Payment response instance.
		 * @param {string}          message Error message to display.
		 * @param {Object}          options Set `linkToCheckout` to link to the checkout page.
		 */
		abortPayment: ( payment, message, options = {} ) => {
			wcStripeECE.paymentInFlight = false;
			onAbortPaymentHandler( payment, message );
			displayExpressCheckoutNotice(
				message,
				'error',
				undefined,
				options
			);

			// The wallet sheet only closes once the confirm event gets a terminal
			// result, so order errors must fail it too. A late call rejects an
			// internal Stripe promise asynchronously, after the message is shown.
			payment.paymentFailed( { reason: 'fail' } );
			if ( checkoutRefreshFailed ) {
				wcStripeECE.failCheckoutRefresh();
			} else if ( cartRefreshPending ) {
				wcStripeECE.init();
			}
		},

		// Refresh the express checkout amount/items for the selected variation.
		// Wired to both templates' change signals: the classic
		// `woocommerce_variation_has_changed` jQuery event and the blockified
		// selector's gesture listeners.
		onVariationChanged: () => {
			if ( isVariationSelectionNeeded() ) {
				// Keep the buttons visible: an incomplete selection is
				// handled at click time, where the shopper is prompted to
				// choose their options. A prior errored refresh may have
				// hidden the buttons, so restore them.
				wcStripeECE.show();
				return;
			}

			wcStripeECE.blockExpressCheckoutButton();
			wcStripeECEError = '';

			const requestData = wcStripeECE.getSelectedProductRequestData();
			const selectionKey = JSON.stringify( requestData );

			$.when( wcStripeECE.getSelectedProductData( requestData ) )
				.then( ( response ) => {
					// A null or primitive response can't be applied: record
					// the error (surfaced on the next click) and keep the
					// current preview.
					if ( ! response || typeof response !== 'object' ) {
						wcStripeECEError = defaultErrorMessage;
						return;
					}

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
								.requestShipping === response.requestShipping;

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

						wcStripeECE.previewSelectionKey = selectionKey;
						wcStripeECE.show();
					}
				} )
				.catch( () => {
					wcStripeECE.hide();
				} )
				.always( () => {
					wcStripeECE.unblockExpressCheckoutButton();
				} );
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
				.on(
					'woocommerce_variation_has_changed',
					wcStripeECE.onVariationChanged
				);

			// The jQuery event handled above only exists on the classic
			// template (its script fires it). On the blockified template,
			// listen for the shopper's gestures on the selector instead.
			if ( ! hasClassicVariationForm ) {
				// `attachProductPageEventListeners` runs again on re-init;
				// drop the previous listeners so changes aren't handled twice.
				wcStripeECE.variationSelectionListener?.disconnect();
				// Debounced: the block re-renders the compat `variation_id`
				// input after the gesture, and one selection fires several
				// gesture events.
				wcStripeECE.variationSelectionListener =
					observeVariationSelection(
						debounce( wcStripeECE.onVariationChanged, 250 )
					);
			}

			$( '.quantity' )
				.off( 'input', '.qty' )
				.on(
					'input',
					'.qty',
					debounce( wcStripeECE.onQuantityChanged, 250 )
				);

			// The blockified steppers fire no input event; the block
			// dispatches a synthetic `change` on the input for extensions
			// instead (also after clamping the quantity to a variation's
			// limits), so listen for that too. Simple products render the
			// same block, so this needs no variable-product check.
			$( '.wc-block-add-to-cart-with-options .quantity' )
				.off( 'change', '.qty' )
				.on(
					'change',
					'.qty',
					debounce( wcStripeECE.onQuantityChanged, 250 )
				);
		},

		// Refresh the express checkout amount/items for the current quantity.
		// Wired to the quantity input's `input` event and, on the blockified
		// template, its `change` event.
		onQuantityChanged: () => {
			wcStripeECE.blockExpressCheckoutButton();
			wcStripeECEError = '';

			const requestData = wcStripeECE.getSelectedProductRequestData();
			const selectionKey = JSON.stringify( requestData );

			$.when( wcStripeECE.getSelectedProductData( requestData ) )
				.then(
					( response ) => {
						// A null or primitive response can't be applied:
						// record the error (surfaced on the next click) and
						// keep the current preview.
						if ( ! response || typeof response !== 'object' ) {
							wcStripeECEError = defaultErrorMessage;
							return;
						}

						if (
							! wcStripeECE.paymentAborted &&
							getExpressCheckoutData( 'product' )
								.requestShipping === response.requestShipping
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

						wcStripeECE.previewSelectionKey = selectionKey;
					},
					( response ) => {
						if ( response.responseJSON ) {
							wcStripeECEError = response.responseJSON.error;
						} else {
							wcStripeECEError = defaultErrorMessage;
						}
					}
				)
				.always( function () {
					wcStripeECE.unblockExpressCheckoutButton();
				} );
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

	if ( getExpressCheckoutData( 'is_product_page' ) ) {
		// The localized preview was priced for the form as served.
		wcStripeECE.previewSelectionKey = wcStripeECE.getSelectionKey();
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

	// Coalesce cart updates while keeping the first refresh immediate.
	const refreshExpressCheckout = debounce(
		() => {
			// Defer refreshes until cancel or abort to preserve the open wallet session.
			if ( wcStripeECE.paymentInFlight ) {
				wcStripeECE.pendingRefresh = true;
				return;
			}
			wcStripeECE.init();
		},
		300,
		{ leading: true, trailing: true }
	);

	if ( isClassicCheckout ) {
		let latestCheckoutRequest;
		// Observe the request because failed updates never emit updated_checkout.
		$( document ).on( 'ajaxSend', ( event, request, settings ) => {
			const url = new URL( settings.url, window.location.href );
			if (
				url.origin !== window.location.origin ||
				url.searchParams.get( 'wc-ajax' ) !== 'update_order_review'
			) {
				return;
			}
			latestCheckoutRequest = request;
			const revision = cartRevision;
			request.fail( ( xhr, status ) => {
				if (
					status === 'abort' ||
					status === 'canceled' ||
					request !== latestCheckoutRequest ||
					revision !== cartRevision
				) {
					return;
				}
				wcStripeECE.failCheckoutRefresh();
			} );
		} );
		$( document.body ).on( 'checkout_error', () => {
			if ( cartRefreshPending ) {
				wcStripeECE.failCheckoutRefresh();
			}
		} );
		$( document.body ).on( 'update_checkout', () => {
			checkoutUpdating = true;
			cartRefreshPending = true;
			++cartRevision;
			$( '#wc-stripe-express-checkout-refresh-error button' ).prop(
				'disabled',
				true
			);
		} );
	}

	$( document.body ).on( 'updated_cart_totals updated_checkout', () => {
		checkoutUpdating = false;
		cartRefreshPending = true;
		++cartRevision;
		refreshExpressCheckout();
	} );

	// Keep recovery listeners active if eager SDK initialization throws.
	if (
		getExpressCheckoutData( 'is_product_page' ) ||
		isClassicCheckout ||
		getExpressCheckoutData( 'is_pay_for_order' ) ||
		getExpressCheckoutData( 'is_cart_page' ) ||
		getExpressCheckoutData( 'is_change_payment_method' )
	) {
		wcStripeECE.init();
	}
};

// Prepare Classic wallets from footer markup before deferred scripts reach ready.
const stripeServerData = getStripeServerData();
if (
	getExpressCheckoutData( 'is_checkout_page' ) &&
	! getExpressCheckoutData( 'is_product_page' ) &&
	! getExpressCheckoutData( 'has_block' ) &&
	! getExpressCheckoutData( 'is_pay_for_order' ) &&
	! getExpressCheckoutData( 'is_change_payment_method' ) &&
	stripeServerData &&
	! stripeServerData.isOCEnabled &&
	typeof window.Stripe === 'function' &&
	document.getElementById( 'wc-stripe-express-checkout-element' )
) {
	initializeExpressCheckout( jQuery );
} else {
	jQuery( initializeExpressCheckout );
}
