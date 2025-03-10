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
				{ __( 'Single payment element', 'woocommerce-gateway-stripe' ) }
			</h4>
			<CheckboxControl
				data-testid="single-payment-element-checkbox"
				label={ __(
					'Enable the single payment element feature',
					'woocommerce-gateway-stripe'
				) }
				help={ createInterpolateElement(
					__(
						"By enabling this, your store checkout form will use Stripe's dynamic payment methods. Legacy checkout must be disabled. <learnMoreLink>Learn more</learnMoreLink>.",
						'woocommerce-gateway-stripe'
					),
					{
						learnMoreLink: (
							<ExternalLink href="https://docs.stripe.com/connect/dynamic-payment-methods" />
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
