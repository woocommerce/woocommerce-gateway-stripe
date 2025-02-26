import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import { CheckboxControl, ExternalLink } from '@wordpress/components';
import { getQuery } from '@woocommerce/navigation';
import React, { useEffect, useRef } from 'react';
import { useIsSpeEnabled } from '../../data';

const SinglePaymentElementFeature = () => {
	const [ isSpeEnabled, setIsSpeEnabled ] = useIsSpeEnabled();
	const headingRef = useRef( null );

	useEffect( () => {
		if ( ! headingRef.current ) {
			return;
		}

		const { highlight } = getQuery();
		if ( highlight === 'enable-spe' ) {
			headingRef.current.focus();
		}
	}, [] );

	return (
		<>
			<h4 ref={ headingRef } tabIndex="-1">
				{ __( 'Single payment element', 'woocommerce-gateway-stripe' ) }
			</h4>
			<CheckboxControl
				data-testid="legacy-checkout-experience-checkbox"
				label={ __(
					'Enable the single payment element feature',
					'woocommerce-gateway-stripe'
				) }
				help={ createInterpolateElement(
					__(
						"By enabling this, your store checkout form will use Stripe's dynamic payment methods. <learnMoreLink>Learn more</learnMoreLink>.",
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
			/>
		</>
	);
};

export default SinglePaymentElementFeature;
