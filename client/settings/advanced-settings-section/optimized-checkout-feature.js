/* global wc_stripe_settings_params */
import React, { useEffect, useRef } from 'react';
import { getQuery } from '@woocommerce/navigation';
import styled from '@emotion/styled';
import {
	useIsAdaptivePricingEnabled,
	useIsOCEnabled,
	useOCLayout,
} from '../../data';
import {
	CheckboxControl,
	ExternalLink,
	RadioControl,
} from '@wordpress/components';
import { createInterpolateElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const AdaptivePricingCheckbox = styled( CheckboxControl )`
	margin-left: 24px;
`;

const StyledRadioControl = styled( RadioControl )`
	legend {
		margin-bottom: 12px;
	}
	.components-radio-control__option {
		padding-top: 6px;
		margin-bottom: 0;
	}
`;

const OptimizedCheckoutFeature = () => {
	const [ isOCEnabled, setIsOCEnabled ] = useIsOCEnabled();
	const [ isAdaptivePricingEnabled, setIsAdaptivePricingEnabled ] =
		useIsAdaptivePricingEnabled();
	const [ OCLayout, setOCLayout ] = useOCLayout();
	const headingRef = useRef( null );
	const isCheckoutSessionsAvailable =
		wc_stripe_settings_params.is_cs_available; // eslint-disable-line camelcase

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

	const handleLayoutChange = ( value ) => {
		setOCLayout( value );
	};

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
							<ExternalLink href="https://woocommerce.com/document/stripe/admin-experience/optimized-checkout-suite/" />
						),
					}
				) }
				checked={ isOCEnabled }
				onChange={ setIsOCEnabled }
			/>
			{ isOCEnabled && isCheckoutSessionsAvailable && (
				<AdaptivePricingCheckbox
					label={ __(
						'Let customers pay in their local currency with Adaptive Pricing.',
						'woocommerce-gateway-stripe'
					) }
					help={ createInterpolateElement(
						__(
							"With Adaptive Pricing, Stripe detects the customer's currency via IP and automatically applies localized pricing and conversion. <learnMoreLink>Learn more</learnMoreLink>.",
							'woocommerce-gateway-stripe'
						),
						{
							learnMoreLink: (
								<ExternalLink href="https://docs.stripe.com/payments/currencies/localize-prices/adaptive-pricing" />
							),
						}
					) }
					checked={ isAdaptivePricingEnabled }
					onChange={ setIsAdaptivePricingEnabled }
				/>
			) }
			{ isOCEnabled && (
				<StyledRadioControl
					label={ __( 'Layout', 'woocommerce-gateway-stripe' ) }
					help={ __(
						'Choose between a vertical accordion layout and a horizontal tabs layout to display payment methods.',
						'woocommerce-gateway-stripe'
					) }
					selected={ OCLayout }
					options={ [
						{
							label: __(
								'Accordion',
								'woocommerce-gateway-stripe'
							),
							value: 'accordion',
						},
						{
							label: __( 'Tabs', 'woocommerce-gateway-stripe' ),
							value: 'tabs',
						},
					] }
					onChange={ handleLayoutChange }
				/>
			) }
		</>
	);
};

export default OptimizedCheckoutFeature;
