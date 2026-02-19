import { Elements } from '@stripe/react-stripe-js';
import { getStripeProviderOptions } from 'wcstripe/stripe-utils';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import ElementsForm from 'wcstripe/blocks/payment-intents/elements-form';

const stripeServerData = getBlocksConfiguration();
export const ElementsContainer = ( props ) => {
	const [ clientSecret, setClientSecret ] = useState( null );
	const [ paymentIntentId, setPaymentIntentId ] = useState( null );
	const [ hasRequestedIntent, setHasRequestedIntent ] = useState( false );
	const [ , setErrorMessage ] = useState( null );
	const [ setPaymentProcessorLoadErrorMessage ] = useState( null );

	const { api, LoadingMask, paymentMethodId, supportsDeferredIntent } = props;
	const paymentMethodsConfig = stripeServerData?.paymentMethodsConfig;
	const stripe = api.getStripe();

	useEffect( () => {
		if ( supportsDeferredIntent || hasRequestedIntent ) {
			return;
		}

		async function createIntent() {
			try {
				const paymentNeeded = stripeServerData?.isPaymentNeeded;
				const response = paymentNeeded
					? await api.createIntent(
							stripeServerData?.orderId,
							paymentMethodId
					  )
					: await api.initSetupIntent( paymentMethodId );

				setClientSecret( response.client_secret );
				setPaymentIntentId( response.id );
			} catch ( error ) {
				const paymentMethodTitle =
					stripeServerData?.paymentMethodsConfig?.[ paymentMethodId ]
						?.title ?? '';
				setErrorMessage(
					error?.message ??
						sprintf(
							// translators: %s is the payment method title.
							__(
								'Failed to load %s payment method. Please refresh the page and try again.',
								'woocommerce-gateway-stripe'
							),
							paymentMethodTitle
						)
				);
			}
		}

		setHasRequestedIntent( true );
		createIntent();
	}, [
		api,
		hasRequestedIntent,
		paymentIntentId,
		paymentMethodId,
		supportsDeferredIntent,
	] );

	// If a client secret is required, wait until it is available.
	if ( ! supportsDeferredIntent && ! clientSecret ) {
		return (
			<LoadingMask
				isLoading={ true }
				showSpinner={ true }
				screenReaderLabel={ __(
					'Loading payment method…',
					'woocommerce-gateway-stripe'
				) }
			/>
		);
	}

	return (
		<Elements
			stripe={ stripe }
			options={ getStripeProviderOptions(
				api,
				clientSecret,
				paymentMethodsConfig[ paymentMethodId ].isReusable,
				paymentMethodId,
				props.showSaveOption,
				supportsDeferredIntent
			) }
		>
			<ElementsForm
				api={ api }
				paymentIntentId={ paymentIntentId }
				paymentMethodId={ paymentMethodId }
				onLoadError={ setPaymentProcessorLoadErrorMessage }
				{ ...props }
			/>
		</Elements>
	);
};
