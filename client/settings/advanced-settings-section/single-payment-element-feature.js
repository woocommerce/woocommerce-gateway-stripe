import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import {
	CheckboxControl,
	ExternalLink,
	TextControl,
} from '@wordpress/components';
import React, { useEffect } from 'react';
import { useIsSPEEnabled, useIsUpeEnabled, useSPETitle } from '../../data';

const SinglePaymentElementFeature = () => {
	const [ isSPEEnabled, setIsSPEEnabled ] = useIsSPEEnabled();
	const [ SPETitle, setSPETitle ] = useSPETitle();
	const [ isUpeEnabled ] = useIsUpeEnabled();

	useEffect( () => {
		if ( ! isUpeEnabled ) {
			setIsSPEEnabled( false );
		}
	}, [ isUpeEnabled, setIsSPEEnabled ] );

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
				checked={ isSPEEnabled }
				onChange={ setIsSPEEnabled }
				disabled={ ! isUpeEnabled }
			/>
			{ isSPEEnabled && (
				<TextControl
					help={ __(
						'This will appear as the title of the Smart Checkout payment element on checkout.',
						'woocommerce-gateway-stripe'
					) }
					label={ __( 'Title', 'woocommerce-gateway-stripe' ) }
					value={ SPETitle }
					onChange={ setSPETitle }
				/>
			) }
		</>
	);
};

export default SinglePaymentElementFeature;
