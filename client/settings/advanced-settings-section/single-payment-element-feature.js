import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import { CheckboxControl, ExternalLink } from '@wordpress/components';
import React, { useEffect } from 'react';
import { useIsSpeEnabled, useIsUpeEnabled } from '../../data';

const SinglePaymentElementFeature = () => {
	const [ isSpeEnabled, setIsSpeEnabled ] = useIsSpeEnabled();
	const [ isUpeEnabled ] = useIsUpeEnabled();

	useEffect( () => {
		if ( ! isUpeEnabled ) {
			setIsSpeEnabled( false );
		}
	}, [ isUpeEnabled, setIsSpeEnabled ] );

	return (
		<>
			<h4>
				{ __(
					'Enable Smart Checkout (Recommended)',
					'woocommerce-gateway-stripe'
				) }
			</h4>
			<CheckboxControl
				data-testid="single-payment-element-checkbox"
				label={ __(
					'Enable Smart Checkout to display payment methods',
					'woocommerce-gateway-stripe'
				) }
				help={ createInterpolateElement(
					__(
						"Automatically display the most relevant payment methods for each customer with Stripe's AI-driven Dynamic Payment Methods to optimize your checkout for conversions. <learnMoreLink>Learn more</learnMoreLink>.",
						'woocommerce-gateway-stripe'
					),
					{
						learnMoreLink: (
							<ExternalLink href="https://woocommerce.com/document/stripe/setup-and-configuration/settings-guide/#advanced-settings" />
						),
					}
				) }
				checked={ isSpeEnabled }
				onChange={ setIsSpeEnabled }
				disabled={ ! isUpeEnabled }
			/>
		</>
	);
};

export default SinglePaymentElementFeature;
