import { StoreNotice } from '@woocommerce/blocks-checkout';
import React, { useEffect, useState } from 'react';

const defaultButtontext = 'Create a Bacs Direct Debit payment method';

const CheckoutSession = ( {
	eventRegistration: { onPaymentSetup },
	paymentMethodName,
	upeMethods,
	api,
	activePaymentMethod,
} ) => {
	const [ checkoutSessionId, setCheckoutSessionId ] = useState( '' );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ errorMessage, setErrorMessage ] = useState( null );
	const [ buttonText, setButtonText ] = useState( defaultButtontext );

	useEffect( () => {
		const urlParams = new URLSearchParams( window.location.search );
		const checkoutSessionIdFromURL = urlParams.get( 'checkout_session_id' );
		if ( checkoutSessionIdFromURL ) {
			setCheckoutSessionId( checkoutSessionIdFromURL );
		}
	}, [] );

	useEffect( () => {
		const unsubscribe = onPaymentSetup( () => {
			async function handlePaymentProcessing() {
				if ( upeMethods[ paymentMethodName ] !== activePaymentMethod ) {
					return;
				}

				try {
					if ( ! checkoutSessionId ) {
						throw new Error(
							'You must first create the Bacs Direct Debit payment method before proceeding with checkout.'
						);
					}

					// Attach payment method to customer.
					const response = await api.attachPaymentMethodToCustomer(
						checkoutSessionId
					);

					if ( ! response.success ) {
						throw new Error( response.data.message );
					}

					const setup = {
						type: 'success',
						meta: {
							paymentMethodData: {
								'wc-stripe-new-payment-method': false,
								'wc-stripe-payment-token': `${ response.data.bacs_token_id }`,
								isSavedToken: true,
								payment_method: upeMethods[ paymentMethodName ],
								token: `${ response.data.bacs_token_id }`,
								'wc-stripe_bacs_debit-payment-token': `${ response.data.bacs_token_id }`,
							},
						},
					};

					return setup;
				} catch ( err ) {
					return {
						type: 'error',
						message: err.message,
					};
				}
			}

			return handlePaymentProcessing();
		} );
		return unsubscribe;
	}, [
		activePaymentMethod,
		api,
		checkoutSessionId,
		onPaymentSetup,
		paymentMethodName,
		upeMethods,
	] );

	const createCheckoutSession = async ( e ) => {
		e.preventDefault();
		try {
			setIsLoading( true );
			const response = await api.createCheckoutSession();

			if ( ! response.success ) {
				throw new Error( response.data.message );
			}

			setButtonText( 'Redirecting...' );
			window.location.href = response.data.checkout_session_url;
		} catch ( err ) {
			setErrorMessage( err.message );
			setIsLoading( false );
			setButtonText( defaultButtontext );
		}
	};

	return (
		<>
			{ errorMessage && (
				<div className="wc-block-components-notices">
					<StoreNotice status="error" isDismissible={ false }>
						{ errorMessage }
					</StoreNotice>
				</div>
			) }

			<button onClick={ createCheckoutSession } disabled={ isLoading }>
				{ buttonText }
			</button>
		</>
	);
};

export const getCheckoutSessionElementForBacs = (
	paymentMethod,
	upeMethods,
	api
) => {
	// Select Bacs if the `checkout_session_id` query string is defined.
	const targetNode = document.querySelector( '.wc-block-checkout' );
	if ( targetNode ) {
		const observer = new MutationObserver( () => {
			const bacsRadioButtonSelector =
				'#radio-control-wc-payment-method-options-stripe_bacs_debit';
			const radioButton = document.querySelector(
				bacsRadioButtonSelector
			);
			if ( radioButton ) {
				radioButton.click();
				observer.disconnect();
			}
		} );
		observer.observe( targetNode, { childList: true, subtree: true } );
	}

	return (
		<CheckoutSession
			paymentMethodName={ paymentMethod }
			api={ api }
			upeMethods={ upeMethods }
		/>
	);
};
