import { __, sprintf } from '@wordpress/i18n';
import {
	appendPaymentMethodIdToForm,
	getPaymentMethodTypes,
	initializeUPEAppearance,
	isLinkEnabled,
	getDefaultValues,
	getStripeServerData,
	getUpeSettings,
	showErrorCheckout,
	appendSetupIntentToForm,
	unblockBlockCheckout,
	resetBlockCheckoutPaymentState,
	getAdditionalSetupIntentData,
} from '../../stripe-utils';
import { getFontRulesFromPage } from '../../styles/upe';
import {
	PAYMENT_METHOD_BOLETO,
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_CASHAPP,
	PAYMENT_METHOD_MULTIBANCO,
	PAYMENT_METHOD_WECHAT_PAY,
} from 'wcstripe/stripe-utils/constants';

const gatewayUPEComponents = {};

const paymentMethodsConfig = getStripeServerData()?.paymentMethodsConfig;

for ( const paymentMethodType in paymentMethodsConfig ) {
	gatewayUPEComponents[ paymentMethodType ] = {
		elements: null,
		upeElement: null,
	};
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
 * Creates a Stripe payment element with the specified payment method type and options. The function
 * retrieves the necessary data from the UPE configuration and initializes the appearance. It then creates the
 * Stripe elements and the Stripe payment element, which is attached to the gatewayUPEComponents object afterward.
 *
 * @todo Make paymentMethodType required when Split is implemented.
 *
 * @param {Object} api The API object used to create the Stripe payment element.
 * @param {string} paymentMethodType The type of Stripe payment method to create.
 * @return {Object} A promise that resolves with the created Stripe payment element.
 */
function createStripePaymentElement( api, paymentMethodType = null ) {
	const amount = Number( getStripeServerData()?.cartTotal );
	const paymentMethodTypes = getPaymentMethodTypes( paymentMethodType );
	const options = {
		mode: amount < 1 ? 'setup' : 'payment',
		currency: getStripeServerData()?.currency.toLowerCase(),
		amount,
		paymentMethodCreation: 'manual',
		paymentMethodTypes,
		appearance: initializeUPEAppearance( api ),
		fonts: getFontRulesFromPage(),
	};

	const elements = api.getStripe().elements( options );

	const attachDefaultValuesUpdateEvent = ( element ) => {
		if ( document.getElementById( element ) ) {
			document.getElementById( element ).onblur = function () {
				updatePaymentElementDefaultValues();
			};
		}
	};

	const createdStripePaymentElement = elements.create( 'payment', {
		...getUpeSettings(),
		...getDefaultValues(),
		wallets: {
			applePay: 'never',
			googlePay: 'never',
		},
	} );

	gatewayUPEComponents[ paymentMethodType ].elements = elements;
	gatewayUPEComponents[
		paymentMethodType
	].upeElement = createdStripePaymentElement;

	// When email or phone is updated and Link is enabled, we need to
	// update the payment element to update its default values.
	if (
		getStripeServerData()?.isCheckout &&
		isLinkEnabled() &&
		paymentMethodType === PAYMENT_METHOD_CARD
	) {
		attachDefaultValuesUpdateEvent( 'billing_email' );
		attachDefaultValuesUpdateEvent( 'billing_phone' );
	}

	return createdStripePaymentElement;
}

/**
 * Updates the payment element's default values.
 */
function updatePaymentElementDefaultValues() {
	if ( ! gatewayUPEComponents?.card?.upeElement ) {
		return;
	}

	const paymentElement = gatewayUPEComponents.card.upeElement;
	paymentElement.update( getDefaultValues() );
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
 * @param {Object} api The API object used to call the Stripe API's createPaymentMethod method.
 * @param {Object} elements The Stripe elements object used to create a Stripe payment method.
 * @param {Object} jQueryForm The jQuery object for the form being submitted.
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
				phone: document.querySelector( '#billing_phone' )?.value,
				address: {
					city: document.querySelector( '#billing_city' )?.value,
					country: document.querySelector( '#billing_country' )
						?.value,
					line1: document.querySelector( '#billing_address_1' )
						?.value,
					line2: document.querySelector( '#billing_address_2' )
						?.value,
					postal_code: document.querySelector( '#billing_postcode' )
						?.value,
					state: document.querySelector( '#billing_state' )?.value,
				},
			},
		};
	}

	return api
		.getStripe( paymentMethodType )
		.createPaymentMethod( { elements, params } )
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
 * @param {Object} api The API object.
 * @param {string} domElement The selector of the DOM element of particular payment method to mount the UPE element to.
 * @return {Object} An object containing the Stripe Elements object and the Stripe Payment Element.
 **/
export async function mountStripePaymentElement( api, domElement ) {
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

	const upeElement =
		gatewayUPEComponents[ paymentMethodType ].upeElement ||
		( await createStripePaymentElement( api, paymentMethodType ) );
	upeElement.mount( domElement );

	return gatewayUPEComponents[ paymentMethodType ];
}

/**
 * Handles the checkout process for the provided jQuery form and Stripe payment method type. The function blocks the
 * form UI to prevent duplicate submission and validates the Stripe elements. It then creates a Stripe payment method
 * object and appends the necessary data to the form for checkout completion. Finally, it submits the form and prevents
 * the default form submission from WC Core.
 *
 * @param {Object} api The API object used to create the Stripe payment method.
 * @param {Object} jQueryForm The jQuery object for the form being submitted.
 * @param {string} paymentMethodType The type of Stripe payment method being used.
 * @return {boolean} return false to prevent the default form submission from WC Core.
 */
let hasCheckoutCompleted;
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

	const elements = gatewayUPEComponents[ paymentMethodType ].elements;

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
			await validateElements( elements );

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
 * @param {string} paymentMethod The payment method ID (i.e. pm_1234567890).
 * @param {Object} jQueryForm The jQuery object for the form being submitted.
 * @param {Object} api The API object used to create the Stripe payment method.
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
 * @param {Object} api           The API object used to create the Stripe payment method.
 * @param {Object} jQueryForm    The jQuery object for the form being submitted.
 */
export const confirmVoucherPayment = async ( api, jQueryForm ) => {
	const isOrderPay = getStripeServerData()?.isOrderPay;

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
		( isOrderPay && orderId !== getStripeServerData()?.orderId )
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

		// Once the customer closes the voucher and there are no errors, redirect them to the order received page.
		window.location.href = decodeURIComponent( partials[ 4 ] );
	} catch ( error ) {
		jQueryForm.removeClass( 'processing' ).unblock();
		showErrorCheckout( error.message );
	}
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
 * @param {Object} api           The API object used to create the Stripe payment method.
 * @param {Object} jQueryForm    The jQuery object for the form being submitted.
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
		if ( intentObject.status !== 'requires_action' ) {
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
