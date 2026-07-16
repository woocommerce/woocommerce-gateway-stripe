/* global wc_stripe_settings_params */
import React, { useEffect, useRef, useMemo } from 'react';
import { getQuery } from '@woocommerce/navigation';
import styled from '@emotion/styled';
import {
	useIsAdaptivePricingEnabled,
	useIsOCEnabled,
	useOCLayout,
} from '../../data';
import OptimizedCheckoutFirstMethodNotice from './optimized-checkout-first-method-notice';
import {
	CheckboxControl,
	ExternalLink,
	Notice,
	RadioControl,
} from '@wordpress/components';
import { createInterpolateElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import './style.scss';

const StyledRadioControl = styled( RadioControl )`
	legend {
		margin-bottom: 12px;
	}
	.components-radio-control__option {
		padding-top: 6px;
		margin-bottom: 0;
	}
`;

/**
 * Helper function to get the text (if any) to communicate why Adaptive Pricing is unavailable.
 *
 * @param {string|null} adaptivePricingUnavailableReason The reason why Adaptive Pricing is unavailable, or null if it is available.
 * @param {boolean}     isOCAvailable                    Whether Optimized Checkout Suite is available.
 * @param {boolean}     isOCEnabled                      Whether Optimized Checkout Suite is enabled.
 * @return {string|null} The text to display for the Adaptive Pricing help text, or null if it is available.
 */
const getAdaptivePricingUnavailableText = (
	adaptivePricingUnavailableReason,
	isOCAvailable,
	isOCEnabled
) => {
	if ( ! isOCAvailable || ! isOCEnabled ) {
		return __(
			'Adaptive Pricing is only available when Optimized Checkout Suite is enabled.',
			'woocommerce-gateway-stripe'
		);
	}

	if ( ! adaptivePricingUnavailableReason ) {
		return null;
	}

	switch ( adaptivePricingUnavailableReason ) {
		case 'account-country':
			return __(
				'Adaptive Pricing is not available in your country.',
				'woocommerce-gateway-stripe'
			);
		case 'no-settlement-currencies':
			return __(
				'We cannot identify which settlement currencies are available for your account.',
				'woocommerce-gateway-stripe'
			);
		case 'store-currency-not-settlement-currency':
			return __(
				'Adaptive Pricing is unavailable as your account does not support settlement in your store currency.',
				'woocommerce-gateway-stripe'
			);
		case 'webhooks-disabled':
			return __(
				'Adaptive Pricing requires working webhooks. Your Stripe webhooks are currently disabled, so it can’t be enabled.',
				'woocommerce-gateway-stripe'
			);
		case 'manual-capture':
			return __(
				'Adaptive Pricing requires automatic capture. It’s unavailable while "Issue an authorization on checkout, and capture later" is enabled.',
				'woocommerce-gateway-stripe'
			);
		case 'amount-mismatch-detected':
			return __(
				'Adaptive Pricing was disabled due to a plugin compatibility issue. Please contact WooCommerce support to report the issue so we can investigate the cause.',
				'woocommerce-gateway-stripe'
			);
		default:
			return __(
				'Adaptive Pricing is currently unavailable.',
				'woocommerce-gateway-stripe'
			);
	}
};

/**
 * Props for the OptimizedCheckoutFeature component.
 *
 * @typedef {Object} OptimizedCheckoutFeatureProps
 * @property {boolean} isOCAvailable Whether Optimized Checkout Suite is available.
 */

/**
 * The OptimizedCheckoutFeature component.
 *
 * @param {OptimizedCheckoutFeatureProps} props The props for the OptimizedCheckoutFeature component.
 * @return {React.ReactNode} The rendered OptimizedCheckoutFeature component.
 */
const OptimizedCheckoutFeature = ( { isOCAvailable } ) => {
	const [ isOCEnabled, setIsOCEnabled ] = useIsOCEnabled();
	const [ isAdaptivePricingEnabled, setIsAdaptivePricingEnabled ] =
		useIsAdaptivePricingEnabled();
	const [ OCLayout, setOCLayout ] = useOCLayout();

	const headingRef = useRef( null );
	const adaptivePricingUnavailableReason =
		wc_stripe_settings_params.adaptive_pricing_unavailable_reason; // eslint-disable-line camelcase

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

	const adaptivePricingHelp = useMemo( () => {
		const baseAdaptivePricingText = __(
			"With Adaptive Pricing, Stripe detects the customer's currency and allows them to pay using their local currency and enabled local payment methods. <learnMoreLink>Learn more</learnMoreLink>.",
			'woocommerce-gateway-stripe'
		);
		const interpolateElements = {
			learnMoreLink: (
				<ExternalLink href="https://docs.stripe.com/payments/currencies/localize-prices/adaptive-pricing" />
			),
		};
		let fullAdaptivePricingText = baseAdaptivePricingText;

		if (
			! isOCAvailable ||
			! isOCEnabled ||
			adaptivePricingUnavailableReason
		) {
			const adaptivePricingUnavailableText =
				getAdaptivePricingUnavailableText(
					adaptivePricingUnavailableReason,
					isOCAvailable,
					isOCEnabled
				);

			fullAdaptivePricingText =
				'<emphasize>' +
				adaptivePricingUnavailableText +
				'</emphasize>' +
				baseAdaptivePricingText;
			interpolateElements.emphasize = (
				<span className="wc-stripe-adaptive-pricing-unavailable-reason" />
			);
		}

		return createInterpolateElement(
			fullAdaptivePricingText,
			interpolateElements
		);
	}, [ adaptivePricingUnavailableReason, isOCAvailable, isOCEnabled ] );

	return (
		<>
			<h4 ref={ headingRef }>
				{ __(
					'Enable Optimized Checkout Suite (recommended)',
					'woocommerce-gateway-stripe'
				) }
			</h4>
			{ ! isOCAvailable && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						"Optimized Checkout Suite is not currently available. Please try to reconnect your account to Stripe, but if that doesn't work, please contact our support team.",
						'woocommerce-gateway-stripe'
					) }
				</Notice>
			) }
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
				disabled={ ! isOCAvailable }
			/>
			<OptimizedCheckoutFirstMethodNotice
				isOCEnabled={ isOCAvailable && isOCEnabled }
			/>
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
			<h4>{ __( 'Adaptive Pricing', 'woocommerce-gateway-stripe' ) }</h4>
			<CheckboxControl
				disabled={
					! isOCAvailable ||
					! isOCEnabled ||
					adaptivePricingUnavailableReason !== null
				}
				label={ __(
					'Let customers pay in their local currency with Adaptive Pricing',
					'woocommerce-gateway-stripe'
				) }
				help={ adaptivePricingHelp }
				checked={ isAdaptivePricingEnabled }
				onChange={ setIsAdaptivePricingEnabled }
			/>
		</>
	);
};

export default OptimizedCheckoutFeature;
