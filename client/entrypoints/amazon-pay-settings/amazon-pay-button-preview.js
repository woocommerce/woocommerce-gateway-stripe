import { __ } from '@wordpress/i18n';
import { React, useState, useEffect } from 'react';
import {
	PaymentRequestButtonElement,
	useStripe,
} from '@stripe/react-stripe-js';
import InlineNotice from 'wcstripe/components/inline-notice';
import {
	useAmazonPayButtonType,
	useAmazonPayButtonSize,
	useAmazonPayButtonTheme,
} from 'wcstripe/data';

/**
 * stripePromise is used to pass into <Elements>'s stripe props.
 * The stripe prop in <Elements> can't be change once passed in.
 * Keeping this outside of <AmazonPayButtonPreview> so that
 * re-rendering does not change it.
 */

const buttonSizeToPxMap = {
	small: 40,
	default: 48,
	large: 56,
};

const AmazonPayButtonPreview = () => {
	const stripe = useStripe();
	const [ amazonPay, setAmazonPay ] = useState();
	const [ isLoading, setIsLoading ] = useState( true );
	const [ buttonType ] = useAmazonPayButtonType();
	const [ size ] = useAmazonPayButtonSize();
	const [ theme ] = useAmazonPayButtonTheme();

	useEffect( () => {
		// when `stripe` is falsy, it means that it didn't load because of some error (like: the website wasn't loaded with HTTPS).
		if ( ! stripe ) {
			setIsLoading( false );
			return;
		}

		setIsLoading( true );
		// Create a preview for payment button. The label and its total are placeholders.
		const stripeAmazonPay = stripe.paymentRequest( {
			country: 'US',
			currency: 'usd',
			total: {
				label: __( 'Total', 'woocommerce-gateway-stripe' ),
				amount: 99,
			},
			requestPayerName: true,
			requestPayerEmail: true,
		} );

		// Check the availability of the Payment Request API.
		stripeAmazonPay.canMakePayment().then( ( result ) => {
			if ( result ) {
				setAmazonPay( stripeAmazonPay );
			}
			setIsLoading( false );
		} );
	}, [ stripe, setAmazonPay, setIsLoading ] );

	/**
	 * If stripe is loading, then display nothing.
	 * If stripe finished loading but payment request button failed to load (null), display info section.
	 * If stripe finished loading and payment request button loads, display the button.
	 */
	if ( isLoading ) {
		return null;
	}

	if ( ! amazonPay ) {
		return (
			<InlineNotice status="info" isDismissible={ false }>
				{ __(
					'To preview the button, ' +
						'ensure your device is configured to accept Amazon Pay, ' +
						'and view this page using the Safari or Chrome browsers.',
					'woocommerce-gateway-stripe'
				) }
			</InlineNotice>
		);
	}

	return (
		<>
			<div className="payment-method-settings__preview">
				<PaymentRequestButtonElement
					key={ `${ buttonType }-${ theme }-${ size }` }
					onClick={ ( e ) => {
						e.preventDefault();
					} }
					options={ {
						amazonPay,
						style: {
							paymentRequestButton: {
								type: buttonType,
								theme,
								height: `${
									buttonSizeToPxMap[ size ] ||
									buttonSizeToPxMap.default
								}px`,
							},
						},
					} }
				/>
			</div>
			<p className="payment-method-settings__preview-help-text">
				{ __(
					'To preview the Amazon Pay button, view this page in Chrome or Safari browsers.',
					'woocommerce-gateway-stripe'
				) }
			</p>
		</>
	);
};

export default AmazonPayButtonPreview;
