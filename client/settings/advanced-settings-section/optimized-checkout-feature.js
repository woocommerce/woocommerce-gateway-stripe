import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import { CheckboxControl, ExternalLink } from '@wordpress/components';
import React, { useEffect, useRef } from 'react';
import { getQuery } from '@woocommerce/navigation';
import { useIsOCEnabled, useIsUpeEnabled } from '../../data';

const OptimizedCheckoutFeature = () => {
	const [ isOCEnabled, setIsOCEnabled ] = useIsOCEnabled();
	const [ isUpeEnabled ] = useIsUpeEnabled();
	const headingRef = useRef( null );

	useEffect( () => {
		if ( ! isUpeEnabled ) {
			setIsOCEnabled( false );
		}
	}, [ isUpeEnabled, setIsOCEnabled ] );

	useEffect( () => {
		if ( ! headingRef.current ) {
			return;
		}

		const { highlight } = getQuery();
		if ( highlight === 'enable-optimized-checkout' ) {
			headingRef.current.scrollIntoView( {
				behavior: 'smooth',
				block: 'start',
			} );
		}
	}, [ headingRef ] );

	return (
		<>
			<h4 ref={ headingRef }>
				{ __(
					'Enable Optimized Checkout Suite (recommended)',
					'woocommerce-gateway-stripe'
				) }
			</h4>
			<CheckboxControl
				data-testid="optimized-checkout-element-checkbox"
				label={ __(
					"Dynamically display the most relevant payment methods you've enabled",
					'woocommerce-gateway-stripe'
				) }
				help={ createInterpolateElement(
					__(
						"Stripe's Optimized Checkout Suite uses AI models to order the most relevant payment methods you've enabled for each of your customers dynamically. <learnMoreLink>Learn more</learnMoreLink>.",
						'woocommerce-gateway-stripe'
					),
					{
						learnMoreLink: (
							<ExternalLink href="https://woocommerce.com/document/stripe/setup-and-configuration/settings-guide/#advanced-settings" />
						),
					}
				) }
				checked={ isOCEnabled }
				onChange={ setIsOCEnabled }
				disabled={ ! isUpeEnabled }
			/>
		</>
	);
};

export default OptimizedCheckoutFeature;
