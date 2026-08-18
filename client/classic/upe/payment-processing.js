import jQuery from 'jquery';
import {
	appendPaymentMethodIdToForm,
	appendPaymentIntentIdToForm,
	appendCheckoutSessionIdToForm,
	getPaymentMethodTypes,
	isLinkEnabled,
	getDefaultValues,
	getStripeServerData,
	getUpeSettings,
	showErrorCheckout,
	showErrorPaymentMethod,
	appendSetupIntentToForm,
	unblockBlockCheckout,
	resetBlockCheckoutPaymentState,
	getAdditionalSetupIntentData,
	validateBlikCode,
	getExcludedPaymentMethodTypesForBillingCountry,
	getCurrentBillingCountry,
	getUserDataForCheckoutSession,
	getAdaptivePricingSavedTokenPaymentMethod,
	getBillingDetailsForDeferredFlow,
	normalizeReturnUrl,
	getStaleCheckoutTotalMessage,
	clearStaleCheckoutTotalNotice,
} from '../../stripe-utils';
import {
	initializeUPEAppearance,
	invalidateAppearanceCache,
} from '../../stripe-utils/upe-appearance';
import { getFontRulesFromPage, sampleFontFamily } from '../../styles/upe';
import { getPaymentMethodRadioStyles } from '../../styles/upe/utils';
import { __, sprintf } from '@wordpress/i18n';
import {
	OPTIMIZED_CHECKOUT_DEFAULT_LAYOUT,
	PAYMENT_INTENT_STATUS_REQUIRES_ACTION,
	PAYMENT_METHOD_BLIK,
	PAYMENT_METHOD_BOLETO,
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_CASHAPP,
	PAYMENT_METHOD_MULTIBANCO,
	PAYMENT_METHOD_WECHAT_PAY,
} from 'wcstripe/stripe-utils/constants';
import { handleDisplayOfPaymentInstructions } from 'wcstripe/optimized-checkout/handle-display-of-payment-instructions';
import { handleDisplayOfSavingCheckbox } from 'wcstripe/optimized-checkout/handle-display-of-saving-checkbox';

/**
 * @typedef {Object} UPEComponent
 * @property {string|null}          intentId                The ID of the intent.
 * @property {string|null}          checkoutSessionId       Stripe Checkout Session id (cs_…) embedded by WooCommerce.
 * @property {string}               checkoutSessionRevision Revision token embedded by the native WooCommerce checkout response.
 * @property {Object|null}          elements                The Stripe elements object.
 * @property {Object|null}          upeElement              The Stripe payment element.
 * @property {boolean}              hasLoadError            Whether the payment element has a load error.
 * @property {Promise<Object|null>} upeElementPromise       Promise that resolves to the Stripe payment element.
 * @property {Promise<Object>|null} mountPromise            Promise that resolves once the element is fully (re)mounted, or null when no mount is in flight.
 * @property {Object|null}          mountTarget             The DOM node the in-flight mount targets, used to dedupe concurrent mounts of the same element.
 * @property {number}               mountToken              Monotonic id of the latest mount; an older mount whose token no longer matches skips its side-effects.
 * @property {boolean}              listenersAttached       Whether the one-time element listeners (loaderror/change) have been wired, so remounts don't stack duplicates on the cached element.
 */

/**
 * @type {Object<string, UPEComponent>}
 */
const gatewayUPEComponents = {};
let hasCheckoutCompleted = false;

/**
 * OC exclusions last applied to each Elements instance (as a sorted key), so
 * redundant `elements.update()` calls can be skipped. Keyed by instance: a
 * re-mounted element starts fresh and stale state can't leak across mounts.
 *
 * @type {WeakMap<Object, string>}
 */
const appliedOptimizedCheckoutExclusions = new WeakMap();

const getExclusionsKey = ( excludedPaymentMethodTypes ) =>
	[ ...excludedPaymentMethodTypes ].sort().join( ',' );

/**
 * Tracks an in-flight Payment Element (re)mount.
 *
 * WooCommerce re-renders the payment box on every `updated_checkout`, remounting
 * asynchronously. A submission in that window posts an empty
 * `wc-stripe-payment-method` field and fails, so submissions wait on this.
 *
 * @type {Promise<*>|null}
 */
let mountInProgress = null;

/**
 * Set when the last Adaptive Pricing Checkout Session line-item resync failed.
 *
 * A failed resync leaves the session holding stale line items, so the Payment Element
 * would charge an out-of-date total. We block submission until a later resync succeeds
 * rather than let the buyer be charged the wrong amount.
 *
 * @type {boolean}
 */
let adaptivePricingSyncFailed = false;

/**
 * Bumped on each resync. Back-to-back `updated_checkout` events can overlap two
 * resyncs; only the newest one may publish `adaptivePricingSyncFailed`, so a
 * slower older resync can't clobber the current result.
 *
 * @type {number}
 */
let adaptivePricingSyncGeneration = 0;

/**
 * Registers a (re)mount promise to wait on. Composes with any existing one so
 * overlapping `updated_checkout` cycles all settle before submission proceeds.
 *
 * @param {Promise<*>} promise The mount promise to track.
 */
export function trackMountInProgress( promise ) {
	// Swallow rejections so awaiting this in processPayment never throws.
	const trackedPromise = Promise.resolve( promise ).catch( () => {} );
	mountInProgress = mountInProgress
		? Promise.allSettled( [ mountInProgress, trackedPromise ] ).then(
				() => {}
		  )
		: trackedPromise;
}

/**
 * Initialize the UPE components for each payment method type.
 */
export function initializeUPEComponents() {
	const paymentMethodsConfig =
		getStripeServerData()?.paymentMethodsConfig ?? {};
	for ( const paymentMethodType in paymentMethodsConfig ) {
		gatewayUPEComponents[ paymentMethodType ] = {
			intentId: null,
			checkoutSessionId: null,
			checkoutSessionRevision: '',
			elements: null,
			upeElement: null,
			hasLoadError: false,
			upeElementPromise: null,
			mountPromise: null,
			mountTarget: null,
			mountToken: 0,
			listenersAttached: false,
		};
	}
	// Reset so processPayment runs fully when called again (e.g. after re-init or in tests).
	hasCheckoutCompleted = false;
	mountInProgress = null;
	// A fresh session is created on the next mount, so any prior stale-session block no longer applies.
	adaptivePricingSyncFailed = false;
}

/**
 * After WooCommerce refreshes classic checkout, notify Stripe.js that the native response finished synchronizing
 * the Checkout Session. The PHP lifecycle hook has already applied WooCommerce's current cart to Stripe.
 *
 * Wraps the server request in Stripe Custom Checkout {@link https://docs.stripe.com/js/custom_checkout/run_server_update runServerUpdate}
 * when available so the embedded session state stays consistent after the update.
 *
 * @return {Promise<void>}
 */
export async function maybeUpdateAdaptivePricingCheckoutSession() {
	if ( ! getStripeServerData()?.isAdaptivePricingEnabled ) {
		return;
	}

	const generation = ++adaptivePricingSyncGeneration;
	const nativeSession = getNativeCheckoutSessionData();
	// Only an element that actually holds a session can go stale. A store that fell back to
	// standard elements has nothing to resync, so it must not be warned about a stale total.
	let resyncFailed = false;

	const seen = new Set();
	for ( const paymentMethodType of Object.keys( gatewayUPEComponents ) ) {
		const component = gatewayUPEComponents[ paymentMethodType ];
		const sessionId = component?.checkoutSessionId;
		if ( ! sessionId || seen.has( sessionId ) ) {
			continue;
		}
		seen.add( sessionId );

		// The mounted element cannot update the checkout session ID once it has been set.
		// So we show an error when we failed to get a session OR we got a new session.
		if (
			nativeSession?.status !== 'success' ||
			nativeSession?.session_id !== sessionId
		) {
			resyncFailed = true;
			continue;
		}

		const checkout = component?.elements;

		if ( checkout && typeof checkout.loadActions === 'function' ) {
			try {
				const loadResult = await checkout.loadActions();
				if (
					loadResult.type === 'success' &&
					typeof loadResult.actions?.runServerUpdate === 'function'
				) {
					try {
						blockUI( jQuery( 'form.checkout #payment' ) );
						const updateResult =
							await loadResult.actions.runServerUpdate(
								async () => {}
							);
						if ( updateResult.type === 'error' ) {
							resyncFailed = true;
							// eslint-disable-next-line no-console
							console.error( updateResult.error );
						} else {
							component.checkoutSessionRevision =
								nativeSession.revision;
						}
					} catch ( error ) {
						resyncFailed = true;
						// eslint-disable-next-line no-console
						console.error( error );
					}
					continue;
				}
			} catch ( error ) {
				resyncFailed = true;
				// eslint-disable-next-line no-console
				console.error( error );
			} finally {
				// A superseded resync must not lift the block a newer, still
				// in-flight resync is holding on the payment area.
				if ( generation === adaptivePricingSyncGeneration ) {
					unblockUI( jQuery( 'form.checkout #payment' ) );
				}
			}
		}

		// initCheckoutElementsSdk exposes runServerUpdate; without it Stripe.js cannot be told
		// to fetch the session revision that WooCommerce just synchronized.
		resyncFailed = true;
	}

	// A newer resync started while we were awaiting; let it own the result.
	if ( generation !== adaptivePricingSyncGeneration ) {
		return;
	}

	// Clear any overlay a superseded resync left up; as the current generation
	// with none newer in flight, this can't lift a block still in use.
	unblockUI( jQuery( 'form.checkout #payment' ) );

	adaptivePricingSyncFailed = resyncFailed;

	if ( resyncFailed ) {
		showErrorCheckout( getStaleCheckoutTotalMessage() );
	} else {
		// Retract the notice a prior failed resync left behind now that totals sync.
		clearStaleCheckoutTotalNotice();
	}
}

/**
 * Read Checkout Session data embedded in the latest native checkout fragment.
 *
 * @return {Object|null} Parsed session data.
 */
function getNativeCheckoutSessionData() {
	const dataElement = document.getElementById(
		'wc-stripe-checkout-session-data'
	);
	const serializedData = dataElement?.dataset?.checkoutSession;
	if ( ! serializedData ) {
		return null;
	}

	try {
		return JSON.parse( serializedData );
	} catch ( error ) {
		// eslint-disable-next-line no-console
		console.error( error );
		return null;
	}
}

/**
 * Block UI to indicate processing and avoid duplicate submission.
 *
 * @param {Object} jQueryForm The jQuery object for the form.
 */
function blockUI( jQueryForm ) {
	jQueryForm.addClass( 'processing' ).block( {
		message: null,
		overlayCSS: {
			background: '#fff',
			opacity: 0.6,
		},
	} );
}

/**
 * Unblock UI to remove the processing state from the element of the form.
 *
 * @param {Object} jQueryForm The jQuery object for the form.
 */
function unblockUI( jQueryForm ) {
	jQueryForm.removeClass( 'processing' ).unblock();
}

/**
 * Validates the Stripe elements by submitting them and handling any errors that occur during submission.
 * If an error occurs, the function removes loading effect from the provided jQuery form and thus unblocks it,
 * and shows an error message in the checkout.
 *
 * @param {Object} elements The Stripe elements object to be validated.
 * @return {Promise} Promise for the checkout submission.
 */
export function validateElements( elements ) {
	return elements.submit().then( ( result ) => {
		if ( result.error ) {
			throw new Error( result.error.message );
		}
	} );
}

/**
 * Updates the payment element's default values.
 *
 * @param {boolean} forCheckoutSession Whether the default values are for a Checkout Session.
 */
function updatePaymentElementDefaultValues( forCheckoutSession = false ) {
	if ( ! gatewayUPEComponents?.card?.upeElement ) {
		return;
	}

	const paymentElement = gatewayUPEComponents.card.upeElement;
	paymentElement.update( getDefaultValues( forCheckoutSession ) );
}

/**
 * Creates a Stripe payment element with the specified payment method type and options.
 *
 * If the payment method doesn't support deferred intent, the intent must be created first.
 *
 * When Adaptive Pricing is enabled, a Checkout Session is created first and
 * the element is loaded via initCheckoutElementsSdk.
 * Otherwise, the payment element is created with the intent's client secret.
 *
 * Finally, the payment element is mounted and attached to the gatewayUPEComponents object.
 *
 * @param {Object} api               The API object used to create the Stripe payment element.
 * @param {string} paymentMethodType The type of Stripe payment method to create.
 * @return {Object} A promise that resolves with the created Stripe payment element.
 */
async function createStripePaymentElement( api, paymentMethodType ) {
	const stripeServerData = getStripeServerData();
	const paymentMethodsConfig = stripeServerData?.paymentMethodsConfig ?? {};
	const { supportsDeferredIntent } =
		paymentMethodsConfig[ paymentMethodType ] || {};
	let intent, options;

	const shouldExpandOptimizedCheckout =
		stripeServerData?.shouldShowOptimizedCheckout &&
		stripeServerData?.shouldExpandOptimizedCheckout &&
		document.querySelector(
			'.woocommerce-checkout-payment .payment_methods'
		);

	options = {
		appearance: initializeUPEAppearance(
			'false',
			shouldExpandOptimizedCheckout
		),
		paymentMethodCreation: 'manual',
		fonts: getFontRulesFromPage(),
	};

	// If the payment method doesn't support deferred intent, the intent must be created here.
	if ( ! supportsDeferredIntent ) {
		try {
			const isSetupIntent =
				document.getElementById( 'add_payment_method' ) ||
				! stripeServerData?.isPaymentNeeded ||
				stripeServerData?.isChangingPayment;

			if ( isSetupIntent ) {
				intent = await api.initSetupIntent( paymentMethodType );
			} else {
				intent = await api.createIntent( null, paymentMethodType );
			}
		} catch ( error ) {
			showErrorPaymentMethod(
				error?.message ??
					sprintf(
						// translators: %s is the payment method title.
						__(
							'Failed to load %s payment method. Please refresh the page and try again.',
							'woocommerce-gateway-stripe'
						),
						paymentMethodsConfig?.[ paymentMethodType ]?.title ?? ''
					),
				'.payment_box.payment_method_stripe_' + paymentMethodType
			);
			// Setting the flag to true to prevent the form from being submitted.
			gatewayUPEComponents[ paymentMethodType ].hasLoadError = true;
			return;
		}

		gatewayUPEComponents[ paymentMethodType ].intentId = intent.id;

		options = {
			...options,
			clientSecret: intent.client_secret,
		};
	} else {
		const amount = Number( stripeServerData?.cartTotal );
		const paymentMethodTypes = getPaymentMethodTypes( paymentMethodType );

		options = {
			...options,
			mode: amount < 1 ? 'setup' : 'payment',
			currency: stripeServerData?.currency.toLowerCase(),
			amount,
		};

		if ( stripeServerData?.shouldShowOptimizedCheckout ) {
			options = {
				...options,
				paymentMethodConfiguration:
					stripeServerData?.paymentMethodConfigurationId,
				// Server exclusions plus country-restricted methods; refreshed on country change.
				excludedPaymentMethodTypes:
					getExcludedPaymentMethodTypesForBillingCountry(
						getCurrentBillingCountry()
					),
			};

			const setupFutureUsage =
				document.getElementById( 'wc-stripe-new-payment-method' )
					?.checked || stripeServerData?.cartContainsSubscription;
			if ( setupFutureUsage ) {
				options = {
					...options,
					setupFutureUsage: 'off_session',
				};
			}
		} else {
			options = {
				...options,
				paymentMethodTypes,
			};
		}
	}

	const stripe = api.getStripe();
	let elements;
	let shouldLoadStripeElements = true;
	// If Adaptive Pricing is enabled, use the Checkout Session API to load the elements.
	if (
		stripeServerData?.isAdaptivePricingEnabled &&
		supportsDeferredIntent &&
		typeof stripe?.initCheckoutElementsSdk === 'function'
	) {
		try {
			const nativeSession = getNativeCheckoutSessionData();
			const clientSecret = nativeSession?.client_secret;
			const sessionId = nativeSession?.session_id;

			if (
				! clientSecret ||
				! sessionId ||
				nativeSession?.status !== 'success'
			) {
				throw new Error(
					__(
						'Failed to load payment method due to missing client secret or session id.',
						'woocommerce-gateway-stripe'
					)
				);
			}

			gatewayUPEComponents[ paymentMethodType ].checkoutSessionId =
				sessionId;
			gatewayUPEComponents[ paymentMethodType ].checkoutSessionRevision =
				nativeSession.revision;

			elements = await stripe.initCheckoutElementsSdk( {
				clientSecret,
				elementsOptions: {
					appearance: options.appearance,
					fonts: options.fonts,
					savedPaymentMethod: {
						// Stripe must not list saved customer payment methods inside the Payment Element; the gateway surfaces the saved payment methods instead.
						enableRedisplay: 'never',
						// Stripe must not show the save payment method checkbox in the Payment Element; the gateway has its own save payment method checkbox.
						enableSave: 'never',
					},
				},
				adaptivePricing: {
					allowed: true,
				},
			} );

			shouldLoadStripeElements = false;
		} catch ( error ) {
			gatewayUPEComponents[ paymentMethodType ].checkoutSessionId = null;
			gatewayUPEComponents[ paymentMethodType ].checkoutSessionRevision =
				'';
			// eslint-disable-next-line no-console
			console.error( error );
			shouldLoadStripeElements = true;
		}
	}

	// If Adaptive Pricing is not enabled, or if there was an error loading the AP elements,
	// load the Stripe elements as fallback.
	if ( shouldLoadStripeElements ) {
		gatewayUPEComponents[ paymentMethodType ].checkoutSessionId = null;
		gatewayUPEComponents[ paymentMethodType ].checkoutSessionRevision = '';
		elements = stripe.elements( options );

		// Creation already applied these exclusions; seed the memo so the first
		// `updated_checkout` doesn't re-send an identical update.
		if ( options.excludedPaymentMethodTypes ) {
			appliedOptimizedCheckoutExclusions.set(
				elements,
				getExclusionsKey( options.excludedPaymentMethodTypes )
			);
		}
	}

	// After web fonts finish loading, re-compute appearance with correct
	// font families and update the live Stripe Elements instance.
	document.fonts?.ready?.then( () => {
		// Compare the live font with the cached appearance — only
		// invalidate and recompute if they actually differ.
		const cachedFont = initializeUPEAppearance(
			'false',
			shouldExpandOptimizedCheckout
		)?.variables?.fontFamily;
		const liveFont = sampleFontFamily( false );
		if ( ! liveFont || liveFont === cachedFont ) {
			return;
		}
		invalidateAppearanceCache();
		const appearance = initializeUPEAppearance(
			'false',
			shouldExpandOptimizedCheckout
		);
		if ( typeof elements?.update === 'function' ) {
			elements.update( { appearance } );
		}
	} );

	const attachDefaultValuesUpdateEvent = (
		element,
		forCheckoutSession = false
	) => {
		if ( document.getElementById( element ) ) {
			document.getElementById( element ).onblur = function () {
				updatePaymentElementDefaultValues( forCheckoutSession );
			};
		}
	};

	let paymentElementOptions = {
		wallets: {
			applePay: 'never',
			googlePay: 'never',
		},
	};

	// Set the layout to accordion if OC is enabled.
	if ( stripeServerData?.shouldShowOptimizedCheckout ) {
		const ocsLayout = shouldExpandOptimizedCheckout
			? 'accordion'
			: stripeServerData?.OCLayout || OPTIMIZED_CHECKOUT_DEFAULT_LAYOUT;
		const layout = {
			type: ocsLayout,
		};
		if ( ocsLayout === OPTIMIZED_CHECKOUT_DEFAULT_LAYOUT ) {
			layout.spacedAccordionItems = false;

			if ( shouldExpandOptimizedCheckout ) {
				layout.paymentMethodLogoPosition = 'end';
				// Ensure all available payment methods are shown.
				layout.visibleAccordionItemsCount = 0;
				layout.radios =
					getPaymentMethodRadioStyles() !== null ? 'always' : 'never';
			} else {
				layout.radios = 'never';
			}
		}
		paymentElementOptions = {
			...paymentElementOptions,
			layout,
		};
	} else {
		// When Optimized Checkout is disabled, default to 'tabs' layout, as that has
		// the best default UX for individual payment methods.
		paymentElementOptions.layout = {
			type: 'tabs',
		};
	}

	let createdStripePaymentElement = null;

	if ( shouldLoadStripeElements ) {
		paymentElementOptions = {
			...paymentElementOptions,
			...getDefaultValues(),
			...getUpeSettings(),
		};
		createdStripePaymentElement = elements.create(
			'payment',
			paymentElementOptions
		);
	} else {
		const upeSettings = getUpeSettings();
		// createPaymentElement() (Checkout Sessions API) does not accept terms.link.
		if ( upeSettings.terms ) {
			delete upeSettings.terms.link;
		}
		paymentElementOptions = {
			...paymentElementOptions,
			...upeSettings,
		};
		createdStripePaymentElement = elements.createPaymentElement(
			paymentElementOptions
		);
		mountCurrencySelectorElement( elements );
	}

	gatewayUPEComponents[ paymentMethodType ].elements = elements;
	gatewayUPEComponents[ paymentMethodType ].upeElement =
		createdStripePaymentElement;

	// When email or phone is updated and Link is enabled, we need to
	// update the payment element to update its default values.
	if (
		stripeServerData?.isCheckout &&
		isLinkEnabled() &&
		paymentMethodType === PAYMENT_METHOD_CARD
	) {
		attachDefaultValuesUpdateEvent(
			'billing_email',
			! shouldLoadStripeElements
		);
		attachDefaultValuesUpdateEvent(
			'billing_phone',
			! shouldLoadStripeElements
		);
	}

	return createdStripePaymentElement;
}

/**
 * Mounts the currency selector element to the DOM element.
 *
 * @param {Object} elements The Stripe elements object.
 */
function mountCurrencySelectorElement( elements ) {
	const currencySelectorContainer = document.getElementById(
		'wc-stripe-currency-selector'
	);
	if ( ! currencySelectorContainer ) {
		return;
	}
	const currencySelector = elements.createCurrencySelectorElement();
	currencySelector.mount( currencySelectorContainer );
}

/**
 * Submits the provided jQuery form and removes the 'processing' class from it.
 *
 * @param {Object} jQueryForm The jQuery object for the form being submitted.
 */
function submitForm( jQueryForm ) {
	jQueryForm.removeClass( 'processing' ).trigger( 'submit' );
}

/**
 * Creates a Stripe payment method by calling the Stripe API's createPaymentMethod with the provided elements
 * and billing details. The billing details are obtained from various form elements on the page.
 *
 * @param {Object} api               The API object used to call the Stripe API's createPaymentMethod method.
 * @param {Object} elements          The Stripe elements object used to create a Stripe payment method.
 * @param {Object} jQueryForm        The jQuery object for the form being submitted.
 * @param {string} paymentMethodType The type of Stripe payment method to create.
 * @return {Promise<Object>} A promise that resolves with the created Stripe payment method.
 */
function createStripePaymentMethod(
	api,
	elements,
	jQueryForm,
	paymentMethodType
) {
	let params = {};
	if ( jQueryForm.attr( 'name' ) === 'checkout' ) {
		params = {
			billing_details: {
				name: document.querySelector( '#billing_first_name' )
					? (
							document.querySelector( '#billing_first_name' )
								?.value +
							' ' +
							document.querySelector( '#billing_last_name' )
								?.value
					  ).trim()
					: undefined,
				email: document.querySelector( '#billing_email' )?.value,
				phone:
					// Phone is optional, but an empty string is not allowed by Stripe.
					document.querySelector( '#billing_phone' )?.value || null,
				address: {
					city: document.querySelector( '#billing_city' )?.value,
					country:
						document.querySelector( '#billing_country' )?.value,
					line1: document.querySelector( '#billing_address_1' )
						?.value,
					line2: document.querySelector( '#billing_address_2' )
						?.value,
					postal_code:
						document.querySelector( '#billing_postcode' )?.value,
					state: document.querySelector( '#billing_state' )?.value,
				},
			},
		};
	} else {
		// On the deferred-payment flows the form is not a checkout form, so billing
		// fields are not present in the DOM. Forward the order/customer billing
		// data from the server.
		const billingDetails = getBillingDetailsForDeferredFlow();
		if ( billingDetails ) {
			params = {
				billing_details: billingDetails,
			};
		}
	}

	// BLIK uses a controlled form instead of Stripe Elements.
	const paymentMethodData =
		paymentMethodType === PAYMENT_METHOD_BLIK
			? {
					billing_details: params?.billing_details,
					blik: {},
					type: paymentMethodType,
			  }
			: { elements, params };

	return api
		.getStripe( paymentMethodType )
		.createPaymentMethod( paymentMethodData )
		.then( ( paymentMethod ) => {
			if ( paymentMethod.error ) {
				throw paymentMethod.error;
			}
			return paymentMethod;
		} );
}

/**
 * Mounts the existing Stripe Payment Element to the DOM element.
 * Creates the Stripe Payment Element instance if it doesn't exist and mounts it to the DOM element.
 *
 * @param {Object} api        The API object.
 * @param {string} domElement The selector of the DOM element of particular payment method to mount the UPE element to.
 * @return {Object} An object containing the Stripe Elements object and the Stripe Payment Element.
 */
export async function mountStripePaymentElement( api, domElement ) {
	const mountPromise = mountStripePaymentElementImpl( api, domElement );
	// Track this (re)mount so a concurrent checkout submission waits for it.
	trackMountInProgress( mountPromise );
	return mountPromise;
}

/**
 * Mounts the Stripe payment element. See {@link mountStripePaymentElement}.
 *
 * @param {Object} api        The API object used to create the Stripe payment element.
 * @param {Object} domElement The DOM element to mount the Stripe payment element on.
 * @return {Promise<Object|undefined>} The UPE component, or undefined when nothing was mounted.
 */
async function mountStripePaymentElementImpl( api, domElement ) {
	/*
	 * Trigger this event to ensure the tokenization-form.js init
	 * is executed.
	 *
	 * This script handles the radio input interaction when toggling
	 * between the user's saved card / entering new card details.
	 *
	 * Ref: https://github.com/woocommerce/woocommerce/blob/2429498/assets/js/frontend/tokenization-form.js#L109
	 */
	const event = new Event( 'wc-credit-card-form-init' );
	document.body.dispatchEvent( event );

	let paymentMethodType = domElement.dataset.paymentMethodType;

	if ( typeof paymentMethodType === 'undefined' ) {
		paymentMethodType = PAYMENT_METHOD_CARD;
	}

	if ( ! gatewayUPEComponents[ paymentMethodType ] ) {
		return;
	}

	const component = gatewayUPEComponents[ paymentMethodType ];

	// Two callers can race to mount the same element; reuse the in-flight mount
	// so upeElement.mount() isn't called twice on the same node.
	if ( component.mountPromise && component.mountTarget === domElement ) {
		return component.mountPromise;
	}

	// A newer mount supersedes any older in-flight one. The async body captures
	// this token and skips its side-effects once it no longer matches, so a slow
	// stale remount can't flip shared state after a newer mount has settled.
	const mountToken = component.mountToken + 1;
	component.mountToken = mountToken;
	const isCurrentMount = () => component.mountToken === mountToken;

	// Clear any prior load error so a successful (re)mount can recover instead of
	// leaving checkout blocked until a page refresh.
	component.hasLoadError = false;

	let upeElementPromise =
		gatewayUPEComponents[ paymentMethodType ]?.upeElementPromise ?? null;
	if ( ! upeElementPromise ) {
		if ( gatewayUPEComponents[ paymentMethodType ].upeElement ) {
			upeElementPromise = Promise.resolve(
				gatewayUPEComponents[ paymentMethodType ].upeElement
			);
		} else {
			upeElementPromise = createStripePaymentElement(
				api,
				paymentMethodType
			).catch( ( error ) => {
				gatewayUPEComponents[ paymentMethodType ].upeElementPromise =
					null;
				throw error;
			} );
		}
		gatewayUPEComponents[ paymentMethodType ].upeElementPromise =
			upeElementPromise;
	}

	// Run the (re)mount as one promise so a concurrent mount of the same node
	// reuses it (dedupe above) and a newer mount supersedes it via the token
	// fence. Submission waiting goes through the module-level tracker.
	const mountPromise = ( async () => {
		const upeElement = await upeElementPromise;

		// A newer mount started while we awaited element creation; let it win.
		if ( ! isCurrentMount() ) {
			return component;
		}

		if ( ! upeElement ) {
			// Clear cached promise so later attempts can retry creation.
			component.upeElementPromise = null;
			return component;
		}

		upeElement.mount( domElement );

		// The element is cached and reused across remounts, so wire its listeners
		// once — re-attaching on every remount stacks duplicate handlers. The
		// loaderror handler reads the live mount target so it surfaces on the
		// element currently on the page, not the first node it was bound to.
		if ( ! component.listenersAttached ) {
			upeElement.on( 'loaderror', ( e ) => {
				showErrorPaymentMethod(
					e.error.message,
					component.mountTarget ?? domElement
				);
				// Setting the flag to true to prevent the form from being submitted.
				component.hasLoadError = true;
			} );

			const stripeServerData = getStripeServerData();
			if ( stripeServerData?.shouldShowOptimizedCheckout ) {
				const paymentMethodsConfig =
					stripeServerData?.paymentMethodsConfig;
				// Single stable listener that reads the latest selected type
				// from a shared variable. Using one reference keeps
				// removeEventListener effective so the checkbox handler never
				// stacks across change events.
				let selectedPaymentType;
				const updateCheckboxListener = () => {
					handleDisplayOfSavingCheckbox(
						selectedPaymentType,
						paymentMethodsConfig
					);
				};
				upeElement.on( 'change', ( { value } ) => {
					selectedPaymentType = value.type;

					// Mirror the actual selected payment method type into the hidden
					// input so it's submitted with the form. This lets the server set
					// the order's payment method title to the actual method (e.g.
					// iDEAL) instead of the OC pseudo-method's default ("Stripe") when
					// paying via Adaptive Pricing / Checkout Sessions, where the
					// outer form's `payment_method` is just `stripe`.
					const selectedTypeInput = document.getElementById(
						'wc_stripe_selected_upe_payment_type'
					);
					if ( selectedTypeInput ) {
						selectedTypeInput.value = value?.type ?? '';
					}

					// If the OC is enabled, we need to handle the display of the saving checkbox.
					handleDisplayOfPaymentInstructions( value.type, 'classic' );

					// Bind the create account checkbox to the save card info container display function.
					const createAccountCheckbox =
						document.getElementById( 'createaccount' );
					if ( createAccountCheckbox ) {
						createAccountCheckbox.removeEventListener(
							'change',
							updateCheckboxListener
						);
						createAccountCheckbox.addEventListener(
							'change',
							updateCheckboxListener
						);
					}
					handleDisplayOfSavingCheckbox(
						value.type,
						paymentMethodsConfig
					);
				} );
			}

			component.listenersAttached = true;
		}

		const elements = component.elements;
		const isAdaptivePricingEnabled =
			getStripeServerData()?.isAdaptivePricingEnabled;

		if (
			! isAdaptivePricingEnabled ||
			! elements ||
			typeof elements.loadActions !== 'function'
		) {
			return component;
		}

		// Call loadActions() after mounting the elements with the Checkout Session API to check if there are any errors.
		let hasLoadActionsError = false;
		let loadActionsErrorMessage;
		try {
			const actions = await elements.loadActions();

			// Superseded by a newer mount while awaiting; don't write shared
			// state or surface an error for this stale mount.
			if ( ! isCurrentMount() ) {
				return component;
			}

			if ( actions.type === 'error' ) {
				hasLoadActionsError = true;
				loadActionsErrorMessage = actions?.error?.message;
			}
		} catch ( error ) {
			if ( ! isCurrentMount() ) {
				return component;
			}
			hasLoadActionsError = true;
			loadActionsErrorMessage = error?.message;
		}

		if ( hasLoadActionsError ) {
			// Setting the flag to true to prevent the form from being submitted.
			component.hasLoadError = true;
			showErrorPaymentMethod(
				loadActionsErrorMessage ??
					__(
						'Failed to load payment method. Please refresh the page and try again.',
						'woocommerce-gateway-stripe'
					),
				domElement
			);
		}

		return component;
	} )();

	component.mountPromise = mountPromise;
	component.mountTarget = domElement;
	try {
		return await mountPromise;
	} finally {
		// Clear only if we're still the in-flight mount, so a newer remount's
		// promise isn't wiped out by an older one settling. mountTarget is left
		// pointing at the last mounted node so the once-attached loaderror
		// handler can surface errors on whatever element is currently on the page.
		if ( component.mountPromise === mountPromise ) {
			component.mountPromise = null;
		}
	}
}

/**
 * Queries the on-page UPE container for a payment method type.
 *
 * @param {string} paymentMethodType The payment method type.
 * @return {HTMLElement|null} The container element, or null if not on the page.
 */
function getUPEDomElement( paymentMethodType ) {
	return document.querySelector(
		`.wc-stripe-upe-element[data-payment-method-type="${ paymentMethodType }"]`
	);
}

/**
 * Whether a UPE container currently holds a mounted element. Stripe appends the
 * Payment Element's iframe as a child on mount, so an empty container means the
 * element was torn down (e.g. by an `updated_checkout` re-render).
 *
 * @param {HTMLElement|null} domElement The UPE container element.
 * @return {boolean} True when the element is mounted.
 */
function isUPEDomElementMounted( domElement ) {
	return !! domElement && domElement.children.length > 0;
}

/**
 * Ensures the Payment Element for the given method is fully mounted before the
 * checkout is submitted: awaits any in-flight (re)mount and, if the element was
 * torn down but not yet re-mounted, mounts it.
 *
 * @param {Object} api               The API object.
 * @param {string} paymentMethodType The payment method type.
 * @return {Promise<void>}
 */
export async function ensureUPEElementMounted( api, paymentMethodType ) {
	const component = gatewayUPEComponents[ paymentMethodType ];
	if ( ! component ) {
		return;
	}

	// Drain in-flight updated_checkout chains before touching the DOM. Back-to-back
	// re-renders each start a new chain, so re-read the tracker until none is left.
	while ( mountInProgress ) {
		const inFlight = mountInProgress;
		// eslint-disable-next-line no-await-in-loop
		await inFlight;
		if ( mountInProgress === inFlight ) {
			break;
		}
	}

	// Re-query only after the awaits above: a node captured earlier could have
	// been detached by an `updated_checkout` re-render while we waited, and
	// mounting into a detached node would bind the iframe outside the document.
	const domElement = getUPEDomElement( paymentMethodType );

	// No element on the page (e.g. a 100% discount coupon removed the payment box).
	if ( ! domElement ) {
		return;
	}

	// Torn down but no re-mount kicked off yet — mount it now.
	if ( ! isUPEDomElementMounted( domElement ) ) {
		await mountStripePaymentElement( api, domElement );
	}
}

/**
 * Whether a live Adaptive Pricing Checkout Session backs the payment method's
 * mounted component. False when the mount fell back to classic Stripe Elements.
 *
 * @param {string} paymentMethodType The payment method type.
 * @return {boolean} True when a Checkout Session id was captured at mount time.
 */
export function hasActiveCheckoutSession( paymentMethodType ) {
	return Boolean(
		gatewayUPEComponents[ paymentMethodType ]?.checkoutSessionId
	);
}

/**
 * Gets the mounted UPE element for a payment method type.
 *
 * @param {string} paymentMethodType The payment method type.
 * @return {Object|null} The UPE element component object or null if not found.
 */
export function getMountedUPEComponent( paymentMethodType ) {
	if ( ! gatewayUPEComponents[ paymentMethodType ] ) {
		return null;
	}

	const component = gatewayUPEComponents[ paymentMethodType ];

	if ( ! component.elements ) {
		return null;
	}

	// Only return if the Elements object exists and is mounted.
	if ( isUPEDomElementMounted( getUPEDomElement( paymentMethodType ) ) ) {
		return component;
	}

	return null;
}

/**
 * Pushes recomputed country exclusions to the live OC Elements instance.
 * No-op outside OC and on Adaptive Pricing (`initCheckout` has no `update()`).
 */
export function maybeUpdateOptimizedCheckoutExclusions() {
	if ( ! getStripeServerData()?.shouldShowOptimizedCheckout ) {
		return;
	}

	const elements = gatewayUPEComponents[ PAYMENT_METHOD_CARD ]?.elements;
	if ( ! elements || typeof elements.update !== 'function' ) {
		return;
	}

	const excludedPaymentMethodTypes =
		getExcludedPaymentMethodTypesForBillingCountry(
			getCurrentBillingCountry()
		);

	// `updated_checkout` fires for many unrelated changes (coupon, shipping,
	// quantity); skip the Stripe round-trip when the exclusions are unchanged.
	const exclusionsKey = getExclusionsKey( excludedPaymentMethodTypes );
	if (
		appliedOptimizedCheckoutExclusions.get( elements ) === exclusionsKey
	) {
		return;
	}

	elements.update( { excludedPaymentMethodTypes } );
	// Recorded only after a successful update, so a throw doesn't poison the memo.
	appliedOptimizedCheckoutExclusions.set( elements, exclusionsKey );
}

/**
 * Gets the Stripe payment element for a payment method type.
 *
 * @param {string} paymentMethodType The payment method type.
 * @return {Promise<Object|null>} The Stripe payment element or null if not found.
 */
export async function getStripePaymentElement( paymentMethodType ) {
	const upeElementPromise =
		gatewayUPEComponents?.[ paymentMethodType ]?.upeElementPromise ?? null;
	if ( ! upeElementPromise ) {
		return Promise.resolve( null );
	}

	return await upeElementPromise;
}

/**
 * Handles the checkout process for the provided jQuery form and Stripe payment method type. The function blocks the
 * form UI to prevent duplicate submission and validates the Stripe elements. It then creates a Stripe payment method
 * object and appends the necessary data to the form for checkout completion. Finally, it submits the form and prevents
 * the default form submission from WC Core.
 *
 * @param {Object}   api                        The API object used to create the Stripe payment method.
 * @param {Object}   jQueryForm                 The jQuery object for the form being submitted.
 * @param {string}   paymentMethodType          The type of Stripe payment method being used.
 * @param {Function} [additionalActionsHandler] Optional handler run after payment method creation.
 * @return {void|boolean} Returns false to prevent the default form submission from WC Core, or nothing when exiting early.
 * @throws {Error} If there is an error creating the Stripe payment method.
 */
export const processPayment = (
	api,
	jQueryForm,
	paymentMethodType,
	additionalActionsHandler = () => {}
) => {
	if ( hasCheckoutCompleted ) {
		hasCheckoutCompleted = false;
		return;
	}

	if ( ! gatewayUPEComponents[ paymentMethodType ] ) {
		return;
	}

	blockUI( jQueryForm );

	const getErrorMessage = ( err ) => {
		const genericErrorMessage = __(
			'Payment failed. Please try again.',
			'woocommerce-gateway-stripe'
		);
		if ( ! err ) {
			return genericErrorMessage;
		}

		const stripeErrorCodes = [
			'parameter_invalid_empty',
			'parameter_missing',
			'parameter_string_empty',
			'parameter_string_blank',
		];

		const errorMessage = err?.message || genericErrorMessage;
		if ( ! stripeErrorCodes.includes( err.code ) ) {
			return errorMessage;
		}

		// err.param is expected to be in the format of <billing|shipping>_details[<field>],
		// e.g. billing_details[name]
		const section = err?.param?.match( /(billing|shipping)_/ );
		const field = err?.param?.match( /\[([A-Za-z0-9]+)\]/ );
		if ( ! section || ! field || ! section[ 1 ] || ! field[ 1 ] ) {
			return errorMessage;
		}

		const toProperCase = ( str ) => {
			return str ? str.charAt( 0 ).toUpperCase() + str.slice( 1 ) : str;
		};
		return sprintf(
			/* translators: %s is an input field name */
			__( '%s is a required field.', 'woocommerce-gateway-stripe' ),
			( section && section[ 1 ]
				? toProperCase( section[ 1 ] ) + ' '
				: '' ) + toProperCase( field[ 1 ] )
		);
	};

	( async () => {
		try {
			// Wait out any in-flight re-mount before reading the Elements
			// instance. The form is already blocked, so the spinner covers it.
			await ensureUPEElementMounted( api, paymentMethodType );

			const { elements, hasLoadError } =
				gatewayUPEComponents[ paymentMethodType ];

			if ( hasLoadError ) {
				throw new Error(
					__(
						'Invalid or missing payment details. Please ensure the provided payment method is correctly entered.',
						'woocommerce-gateway-stripe'
					)
				);
			}

			if (
				getStripeServerData()?.isAdaptivePricingEnabled &&
				elements &&
				typeof elements.loadActions === 'function'
			) {
				// The session still holds stale line items from a failed resync, so
				// its total no longer matches the cart. Block rather than charge it.
				if ( adaptivePricingSyncFailed ) {
					throw new Error( getStaleCheckoutTotalMessage() );
				}

				const loadActionsResult = await elements.loadActions();

				if ( loadActionsResult.type === 'error' ) {
					throw new Error(
						loadActionsResult.error?.message ??
							__(
								'Payment could not be completed. Please try again.',
								'woocommerce-gateway-stripe'
							)
					);
				}

				const { actions } = loadActionsResult;
				const session = await actions.getSession();

				// Get the session ID stored during mount.
				const sessionId =
					gatewayUPEComponents[ paymentMethodType ].checkoutSessionId;
				if ( ! sessionId ) {
					throw new Error(
						__(
							'Payment could not be completed. Please try again.',
							'woocommerce-gateway-stripe'
						)
					);
				}

				// Append session ID and submit form to create the WC order
				// BEFORE confirming payment, so the return URL points to the
				// order-received page (not the checkout page).
				appendCheckoutSessionIdToForm( jQueryForm, sessionId );

				const checkoutUrl = api.getAjaxUrl( 'checkout', '' );
				const checkoutResponse = await jQuery.ajax( {
					type: 'POST',
					url: checkoutUrl,
					data: jQueryForm.serialize(),
					dataType: 'json',
				} );

				if ( checkoutResponse.result !== 'success' ) {
					// WC core unblocks in its checkout AJAX complete handler; this path
					// uses a direct jQuery.ajax call, so we must unblock explicitly.
					jQueryForm.removeClass( 'processing' ).unblock();
					const messages = checkoutResponse.messages;
					if (
						typeof messages === 'string' &&
						messages.trim().length > 0
					) {
						showErrorCheckout( messages );
					} else {
						showErrorCheckout(
							__(
								'An error occurred while processing your checkout. Please try again.',
								'woocommerce-gateway-stripe'
							)
						);
					}
					return;
				}

				// Confirm payment with the order-received page as return URL
				// so redirect-based methods (iDEAL, Bancontact, etc.) return
				// the customer to the thank-you page instead of checkout.
				const confirmArgs = {
					...getUserDataForCheckoutSession( session ),
					returnUrl: normalizeReturnUrl( checkoutResponse.redirect ),
					redirect: 'if_required',
				};

				// A saved token pays the session directly: `paymentMethod` makes
				// confirm() ignore the Payment Element, and an already-saved
				// method must not request saving again.
				const savedTokenPaymentMethod =
					getAdaptivePricingSavedTokenPaymentMethod(
						paymentMethodType
					);
				if ( savedTokenPaymentMethod ) {
					confirmArgs.paymentMethod = savedTokenPaymentMethod;
				} else if ( getStripeServerData()?.isLoggedIn ) {
					confirmArgs.savePaymentMethod = jQueryForm
						.find( '#wc-stripe-new-payment-method' )
						.is( ':checked' );
				}

				const confirmResult = await actions.confirm( confirmArgs );

				if ( confirmResult.type === 'error' ) {
					throw new Error(
						confirmResult.error?.message ??
							__(
								'Payment could not be completed. Please try again.',
								'woocommerce-gateway-stripe'
							)
					);
				}

				// No redirect occurred (non-redirect payment method).
				// Navigate to the order-received page.
				window.location.href = checkoutResponse.redirect;
				return;
			}

			if ( paymentMethodType === PAYMENT_METHOD_BLIK ) {
				validateBlikCode( jQueryForm );
			} else {
				await validateElements( elements );
			}

			const paymentMethodObject = await createStripePaymentMethod(
				api,
				elements,
				jQueryForm,
				paymentMethodType
			);

			appendPaymentMethodIdToForm(
				jQueryForm,
				paymentMethodObject.paymentMethod.id
			);

			// Append the intent ID to the form if it was previously created through a non-deferred intent.
			if ( gatewayUPEComponents[ paymentMethodType ].intentId ) {
				appendPaymentIntentIdToForm(
					jQueryForm,
					gatewayUPEComponents[ paymentMethodType ].intentId
				);
			}

			let stopFormSubmission = false;
			await additionalActionsHandler(
				paymentMethodObject.paymentMethod,
				jQueryForm,
				api,
				() => {
					// Provide a callback to flag that a redirect has occurred.
					stopFormSubmission = true;
				}
			);

			if ( stopFormSubmission ) {
				return;
			}

			hasCheckoutCompleted = true;
			submitForm( jQueryForm );
		} catch ( err ) {
			hasCheckoutCompleted = false;
			jQueryForm.removeClass( 'processing' ).unblock();
			showErrorCheckout( getErrorMessage( err ) );
		}
	} )();

	// Prevent WC Core default form submission (see woocommerce/assets/js/frontend/checkout.js) from happening.
	return false;
};

/**
 * Handles creating and confirming a setup intent.
 *
 * With the confirmed setup intent, this function will add the new setup intent ID to the form before submitting.
 *
 * @param {string}   paymentMethod         The payment method ID (i.e. pm_1234567890).
 * @param {Object}   jQueryForm            The jQuery object for the form being submitted.
 * @param {Object}   api                   The API object used to create the Stripe payment method.
 * @param {Function} setStopFormSubmission The callback function to execute when a redirect occurred or the setup wasn't completed.
 *
 * @return {Promise<Object>} A promise that resolves with the confirmed setup intent.
 */
export const createAndConfirmSetupIntent = (
	paymentMethod,
	jQueryForm,
	api,
	setStopFormSubmission
) => {
	const additionalData = getAdditionalSetupIntentData( jQueryForm );
	return api
		.setupIntent( paymentMethod, additionalData )
		.then( function ( confirmedSetupIntent ) {
			switch ( confirmedSetupIntent ) {
				case 'incomplete':
					// When the set up wasn't completed, we need to unlock the form and stop the process.
					jQueryForm.removeClass( 'processing' ).unblock();
				// eslint-disable-next-line no-fallthrough -- intentional we need to stop the form submission on incomplete.
				case 'redirect_to_url':
					setStopFormSubmission();
					return;
				default:
					appendSetupIntentToForm( jQueryForm, confirmedSetupIntent );
					return confirmedSetupIntent;
			}
		} );
};

/**
 * Handles displaying the Boleto or Oxxo or Multibanco voucher to the customer and then redirecting
 * them to the order received page once they close the voucher window.
 *
 * When processing a payment for one of our voucher payment methods on the checkout or order pay page,
 * the process_payment_with_deferred_intent() function redirects the customer to a URL
 * formatted with: #wc-stripe-voucher-<order_id>:<payment_method_type>:<client_secret>:<redirect_url>.
 *
 * This function, which is hooked onto the hashchanged event, checks if the URL contains the data we need to process the voucher payment.
 *
 * @param {Object} api        The API object used to create the Stripe payment method.
 * @param {Object} jQueryForm The jQuery object for the form being submitted.
 */
export const confirmVoucherPayment = async ( api, jQueryForm ) => {
	const stripeServerData = getStripeServerData();
	const isOrderPay = stripeServerData?.isOrderPay;

	// The Order Pay page does a hard refresh when the hash changes, so we need to block the UI again.
	if ( isOrderPay ) {
		blockUI( jQueryForm );
	}

	const partials = window.location.href.match(
		/#wc-stripe-voucher-(.+):(.+):(.+):(.+)$/
	);

	if ( ! partials ) {
		jQueryForm.removeClass( 'processing' ).unblock();
		return;
	}

	// Remove the hash from the URL.
	history.replaceState(
		'',
		document.title,
		window.location.pathname + window.location.search
	);

	const orderId = partials[ 1 ];
	const clientSecret = partials[ 3 ];

	// Verify the request using the data added to the URL.
	if (
		! clientSecret ||
		( isOrderPay && orderId !== stripeServerData?.orderId )
	) {
		jQueryForm.removeClass( 'processing' ).unblock();
		return;
	}

	const paymentMethodType = partials[ 2 ];

	try {
		// Confirm the payment to tell Stripe to display the voucher to the customer.
		let confirmPayment;
		if ( paymentMethodType === PAYMENT_METHOD_BOLETO ) {
			confirmPayment = await api
				.getStripe()
				.confirmBoletoPayment( clientSecret, {} );
		} else if ( paymentMethodType === PAYMENT_METHOD_MULTIBANCO ) {
			confirmPayment = await api
				.getStripe()
				.confirmMultibancoPayment( clientSecret, {} );
		} else {
			confirmPayment = await api
				.getStripe()
				.confirmOxxoPayment( clientSecret, {} );
		}

		if ( confirmPayment.error ) {
			throw confirmPayment.error;
		}
	} catch ( error ) {
		jQueryForm.removeClass( 'processing' ).unblock();
		showErrorCheckout( error.message );
		return;
	}

	let postPaymentUrl = null;
	try {
		postPaymentUrl = decodeURIComponent( partials[ 4 ] || '' );
	} catch ( error ) {}

	const validatedRedirectUrl = normalizeReturnUrl( postPaymentUrl );
	if ( validatedRedirectUrl ) {
		window.location.href = validatedRedirectUrl;
		return;
	}

	if ( ! stripeServerData?.orderReceivedURL ) {
		showErrorCheckout(
			__(
				'There was a problem processing the payment. Please refresh the page to try again.',
				'woocommerce-gateway-stripe'
			)
		);
		return;
	}

	// We didn't get a valid redirect URL, so redirect to the order received page.
	// If we have a numeric order ID, navigate to the order received page for that order.
	if (
		orderId &&
		orderId !== 'NaN' &&
		orderId === String( parseInt( orderId, 10 ) )
	) {
		window.location.href =
			stripeServerData.orderReceivedURL +
			'/' +
			encodeURIComponent( orderId ) +
			'/';
		return;
	}

	// Otherwise go to the generic page.
	window.location.href = stripeServerData.orderReceivedURL;
};

/**
 * Handles displaying the CashApp or WeChat modal to the customer and then redirecting
 * them to the order received page once they authenticate the payment.
 *
 * When processing a payment for a wallet payment method on the checkout or order pay page,
 * the process_payment_with_deferred_intent() function redirects the customer to a URL
 * formatted with: #wc-stripe-wallet-<order_id>:<payment_method_type>:<payment_intent_type>:<client_secret>:<redirect_url>.
 *
 * This function, which is hooked onto the hashchanged event, checks if the URL contains the data we need to process the wallet payment.
 *
 * @param {Object} api        The API object used to create the Stripe payment method.
 * @param {Object} jQueryForm The jQuery object for the form being submitted.
 */
export const confirmWalletPayment = async ( api, jQueryForm ) => {
	const isOrderPay = getStripeServerData()?.isOrderPay;
	const isChangingPayment = getStripeServerData()?.isChangingPayment;

	// The Order Pay page does a hard refresh when the hash changes, so we need to block the UI again.
	if ( isOrderPay ) {
		blockUI( jQueryForm );
	}

	const partials = window.location.href.match(
		/#wc-stripe-wallet-(.+):(.+):(.+):(.+):(.+):(.+)$/
	);

	if ( ! partials ) {
		jQueryForm.removeClass( 'processing' ).unblock();
		return;
	}

	// Remove the hash from the URL.
	history.replaceState(
		'',
		document.title,
		window.location.pathname + window.location.search
	);

	const orderId = partials[ 1 ];
	const clientSecret = partials[ 4 ];

	// Verify the request using the data added to the URL.
	if (
		! clientSecret ||
		( isOrderPay && orderId !== getStripeServerData()?.orderId )
	) {
		jQueryForm.removeClass( 'processing' ).unblock();
		return;
	}

	const paymentMethodType = partials[ 2 ];
	const intentType = partials[ 3 ];
	const returnURL = decodeURIComponent( partials[ 5 ] );

	try {
		// Confirm the payment to tell Stripe to display the modal to the customer.
		let confirmPayment;
		switch ( paymentMethodType ) {
			case PAYMENT_METHOD_WECHAT_PAY:
				confirmPayment = await api
					.getStripe()
					.confirmWechatPayPayment( clientSecret, {
						payment_method_options: {
							wechat_pay: {
								client: 'web',
							},
						},
					} );
				break;
			case PAYMENT_METHOD_CASHAPP:
				if ( intentType === 'setup_intent' ) {
					confirmPayment = await api
						.getStripe()
						.confirmCashappSetup( clientSecret, {
							return_url: returnURL,
						} );
				} else {
					confirmPayment = await api
						.getStripe()
						.confirmCashappPayment( clientSecret, {
							return_url: returnURL,
						} );
				}
				break;
			default:
				// eslint-disable-next-line no-console
				console.error( 'Invalid wallet type:', paymentMethodType );
				throw new Error( getStripeServerData()?.invalid_wallet_type );
		}

		if ( confirmPayment.error ) {
			throw confirmPayment.error;
		}

		const intentObject =
			intentType === 'setup_intent'
				? confirmPayment.setupIntent
				: confirmPayment.paymentIntent;

		if ( intentObject.last_payment_error ) {
			throw new Error( intentObject.last_payment_error.message );
		}

		// Do not redirect to the order received page if the modal is closed without payment.
		// Otherwise redirect to the order received page.
		if ( intentObject.status !== PAYMENT_INTENT_STATUS_REQUIRES_ACTION ) {
			if ( ! isChangingPayment ) {
				window.location.href = returnURL;
			}

			// If we're changing a subscription's payment method, there's an extra step needed.
			// We need to confirm the change payment intent via the confirm_change_payment AJAX request and then redirect to the return URL.
			const response = await api.request(
				api.getAjaxUrl( 'confirm_change_payment' ),
				{
					order_id: orderId,
					intent_id: intentObject.id,
					payment_method_id: intentObject.payment_method || null,
					_ajax_nonce: partials[ 6 ],
				}
			);

			if ( response.success ) {
				window.location.href = response.data.return_url;
			} else {
				throw new Error( response.data.error.message );
			}
		}
	} catch ( error ) {
		showErrorCheckout( error.message );
	} finally {
		jQueryForm.removeClass( 'processing' ).unblock();
		unblockBlockCheckout();
		resetBlockCheckoutPaymentState();
	}
};

/**
 * Checks if any required checkout fields in the given list are empty.
 * Uses the same field selectors as WC core's checkout validation (checkout.js).
 * This is a read-only DOM check with no side effects — it does not trigger
 * validation events or add/remove CSS classes.
 *
 * Visibility filtering is handled by the caller (using jQuery :visible in
 * deferred-intent.js) since jsdom cannot compute element visibility.
 *
 * @param {NodeList|Array} requiredWrappers List of .validate-required DOM elements to check.
 * @return {boolean} True if any required field is empty.
 */
export const hasEmptyRequiredFields = ( requiredWrappers ) => {
	for ( const wrapper of requiredWrappers ) {
		const input = wrapper.querySelector(
			'input.input-text, select, input[type="checkbox"]'
		);
		if ( ! input ) {
			continue;
		}

		if ( input.type === 'checkbox' ) {
			if ( ! input.checked ) {
				return true;
			}
		} else if ( ! input.value || input.value.trim() === '' ) {
			return true;
		}
	}

	return false;
};
