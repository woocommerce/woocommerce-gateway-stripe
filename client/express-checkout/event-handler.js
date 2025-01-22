import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { getErrorMessageFromNotice, getExpressCheckoutData } from './utils';
import {
	trackExpressCheckoutButtonClick,
	trackExpressCheckoutButtonLoad,
} from './tracking';
import ExpressCheckoutCartApi from 'wcstripe/express-checkout/cart-api';
import {
	transformStripePaymentMethodForStoreApi,
	transformStripeShippingAddressForStoreApi,
} from 'wcstripe/express-checkout/transformers/stripe-to-wc';
import {
	transformCartDataForDisplayItems,
	transformCartDataForShippingRates,
	transformPrice,
} from 'wcstripe/express-checkout/transformers/wc-to-stripe';

let cartApi = new ExpressCheckoutCartApi();
export const setCartApiHandler = ( handler ) => ( cartApi = handler );

export const getCartApiHandler = () => cartApi;

export const shippingAddressChangeHandler = async ( event, elements ) => {
	try {
		const cartData = await cartApi.updateCustomer( {
			shipping_address: transformStripeShippingAddressForStoreApi(
				event.name,
				event.address
			),
		} );

		const shippingRates = transformCartDataForShippingRates( cartData );

		// when no shipping options are returned, the API still returns a 200 status code.
		// We need to ensure that shipping options are present - otherwise the ECE dialog won't update correctly.
		if ( shippingRates.length === 0 ) {
			event.reject();
		}

		elements.update( {
			amount: transformPrice(
				parseInt( cartData.totals.total_price, 10 ) -
					parseInt( cartData.totals.total_refund || 0, 10 ),
				cartData.totals
			),
		} );

		event.resolve( {
			shippingRates: transformCartDataForShippingRates( cartData ),
			lineItems: transformCartDataForDisplayItems( cartData ),
		} );
	} catch ( e ) {
		event.reject();
	}
};

export const shippingRateChangeHandler = async ( event, elements ) => {
	try {
		const cartData = await cartApi.selectShippingRate( {
			package_id: 0,
			rate_id: event.shippingRate.id,
		} );

		elements.update( {
			amount: transformPrice(
				parseInt( cartData.totals.total_price, 10 ) -
					parseInt( cartData.totals.total_refund || 0, 10 ),
				cartData.totals
			),
		} );
		event.resolve( {
			lineItems: transformCartDataForDisplayItems( cartData ),
		} );
	} catch ( error ) {
		event.reject();
	}
};

export const onConfirmHandler = async (
	api,
	stripe,
	elements,
	completePayment,
	abortPayment,
	event,
	order = 0 // Order ID for the pay for order flow.
) => {
	const submitResponse = await elements.submit();
	if ( submitResponse?.error ) {
		return abortPayment( event, submitResponse?.error?.message );
	}

	const { paymentMethod, error } = await stripe.createPaymentMethod( {
		elements,
	} );

	if ( error ) {
		return abortPayment( event, error.message );
	}

	try {
		// Kick off checkout processing step.
		const orderResponse = await cartApi.placeOrder( order, {
			// adding extension data as a separate action,
			// so that we make it harder for external plugins to modify or intercept checkout data.
			...transformStripePaymentMethodForStoreApi(
				event,
				paymentMethod.id
			),
			extensions: applyFilters(
				'wcstripe.express-checkout.cart-place-order-extension-data',
				{}
			),
		} );

		if ( orderResponse.payment_result?.payment_status !== 'success' ) {
			return abortPayment(
				event,
				getErrorMessageFromNotice(
					orderResponse.payment_result?.payment_details.find(
						( detail ) => detail.key === 'errorMessage'
					)?.value
				),
				true
			);
		}

		const confirmationRequest = api.confirmIntent(
			orderResponse.payment_result.redirect_url
		);

		// `true` means there is no intent to confirm.
		if ( confirmationRequest === true ) {
			completePayment( orderResponse.payment_result.redirect_url );
		} else {
			const { request } = confirmationRequest;
			const redirectUrl = await request;

			completePayment( redirectUrl );
		}
	} catch ( e ) {
		let errorMessage;
		if ( e.message ) {
			errorMessage = e.message;
		} else {
			const paymentDetailsErrorMessage = e.payment_result?.payment_details.find(
				( detail ) => detail.key === 'errorMessage'
			)?.value;
			if ( paymentDetailsErrorMessage ) {
				errorMessage = paymentDetailsErrorMessage;
			}
		}
		if ( ! errorMessage ) {
			errorMessage = __(
				'There was a problem processing the order.',
				'woocommerce-gateway-stripe'
			);
		}
		return abortPayment(
			event,
			getErrorMessageFromNotice( errorMessage ),
			true
		);
	}
};

export const onReadyHandler = function ( { availablePaymentMethods } ) {
	if ( availablePaymentMethods ) {
		const enabledMethods = Object.entries( availablePaymentMethods )
			.filter( ( [ , isEnabled ] ) => isEnabled )
			.map( ( [ methodName ] ) => methodName );

		trackExpressCheckoutButtonLoad( {
			paymentMethods: enabledMethods,
			source: getExpressCheckoutData( 'button_context' ),
		} );
	}
};

const blockUI = () => {
	jQuery.blockUI( {
		message: null,
		overlayCSS: {
			background: '#fff',
			opacity: 0.6,
		},
	} );
};

const unblockUI = () => {
	jQuery.unblockUI();
};

export const onClickHandler = function ( { expressPaymentType } ) {
	blockUI();
	trackExpressCheckoutButtonClick(
		expressPaymentType,
		getExpressCheckoutData( 'button_context' )
	);
};

export const onAbortPaymentHandler = () => {
	unblockUI();
};

export const onCompletePaymentHandler = () => {
	blockUI();
};

export const onCancelHandler = () => {
	unblockUI();
};
