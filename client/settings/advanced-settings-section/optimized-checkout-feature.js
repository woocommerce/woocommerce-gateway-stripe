/* global wc_stripe_settings_params */
import React, { useEffect, useRef, useMemo } from 'react';
import { getQuery } from '@woocommerce/navigation';
import styled from '@emotion/styled';
import { useIsAdaptivePricingEnabled, useOCLayout } from '../../data';
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
		default:
			return __(
				'Adaptive Pricing is currently unavailable.',
				'woocommerce-gateway-stripe'
			);
	}
};

/**
 * Callback to update the IsEnabled setting for Optimized Checkout Suite.
 *
 * @callback SetIsOCEnabled
 * @param {boolean} value The new value for the IsEnabled setting.
 * @return {void}
 */

/**
 * Props for the OptimizedCheckoutFeature component.
 *
 * @typedef {Object} OptimizedCheckoutFeatureProps
 * @property {boolean}        isOCAvailable  Whether Optimized Checkout Suite is available.
 * @property {boolean}        isOCEnabled    Whether Optimized Checkout Suite is enabled.
 * @property {SetIsOCEnabled} setIsOCEnabled Callback to set the value of the Optimized Checkout Suite setting.
 */

/**
 * The OptimizedCheckoutFeature component.
 *
 * @param {OptimizedCheckoutFeatureProps} props The props for the OptimizedCheckoutFeature component.
 * @return {React.ReactNode} The rendered OptimizedCheckoutFeature component.
 */
const OptimizedCheckoutFeature = ( {
	isOCAvailable,
	isOCEnabled,
	setIsOCEnabled,
} ) => {
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

			return createInterpolateElement(
				'<emphasize>' +
					adaptivePricingUnavailableText +
					'</emphasize>' +
					__(
						"With Adaptive Pricing, Stripe detects the customer's currency via IP and automatically applies localized pricing and conversion. <learnMoreLink>Learn more</learnMoreLink>.",
						'woocommerce-gateway-stripe'
					),
				{
					emphasize: (
						<span className="wc-stripe-adaptive-pricing-unavailable-reason" />
					),
					learnMoreLink: (
						<ExternalLink href="https://docs.stripe.com/payments/currencies/localize-prices/adaptive-pricing" />
					),
				}
			);
		}

		return createInterpolateElement(
			__(
				"With Adaptive Pricing, Stripe detects the customer's currency via IP and automatically applies localized pricing and conversion. <learnMoreLink>Learn more</learnMoreLink>.",
				'woocommerce-gateway-stripe'
			),
			{
				learnMoreLink: (
					<ExternalLink href="https://docs.stripe.com/payments/currencies/localize-prices/adaptive-pricing" />
				),
			}
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
					adaptivePricingUnavailableReason
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
