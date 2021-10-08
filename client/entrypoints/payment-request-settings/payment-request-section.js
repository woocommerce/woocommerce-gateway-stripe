import { __ } from '@wordpress/i18n';
import React, { useMemo } from 'react';
import { Card, RadioControl } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import { Elements } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import PaymentRequestButtonPreview from './payment-request-button-preview';
import {
	usePaymentRequestButtonType,
	usePaymentRequestButtonSize,
	usePaymentRequestButtonTheme,
} from 'wcstripe/data';
import CardBody from 'wcstripe/settings/card-body';
import LoadableAccountSection from 'wcstripe/settings/loadable-account-section';
import { useAccount } from 'wcstripe/data/account/hooks';
import {
	useAccountKeysPublishableKey,
	useAccountKeysTestPublishableKey,
} from 'wcstripe/data/account-keys/hooks';

const makeButtonSizeText = ( string ) =>
	interpolateComponents( {
		mixedString: string,
		components: {
			helpText: (
				<span className="payment-method-settings__option-muted-text" />
			),
		},
	} );
const buttonSizeOptions = [
	{
		label: makeButtonSizeText(
			__(
				'Default {{helpText}}(40 px){{/helpText}}',
				'woocommerce-gateway-stripe'
			)
		),
		value: 'default',
	},
	{
		label: makeButtonSizeText(
			__(
				'Medium {{helpText}}(48 px){{/helpText}}',
				'woocommerce-gateway-stripe'
			)
		),
		value: 'medium',
	},
	{
		label: makeButtonSizeText(
			__(
				'Large {{helpText}}(56 px){{/helpText}}',
				'woocommerce-gateway-stripe'
			)
		),
		value: 'large',
	},
];
const buttonActionOptions = [
	{
		label: __( 'Only icon', 'woocommerce-gateway-stripe' ),
		value: 'default',
	},
	{
		label: __( 'Buy', 'woocommerce-gateway-stripe' ),
		value: 'buy',
	},
	{
		label: __( 'Donate', 'woocommerce-gateway-stripe' ),
		value: 'donate',
	},
	{
		label: __( 'Book', 'woocommerce-gateway-stripe' ),
		value: 'book',
	},
];

const makeButtonThemeText = ( string ) =>
	interpolateComponents( {
		mixedString: string,
		components: {
			br: <br />,
			helpText: (
				<span className="payment-method-settings__option-help-text" />
			),
		},
	} );
const buttonThemeOptions = [
	{
		label: makeButtonThemeText(
			__(
				'Dark {{br/}}{{helpText}}Recommended for white or light-colored backgrounds with high contrast.{{/helpText}}',
				'woocommerce-gateway-stripe'
			)
		),
		value: 'dark',
	},
	{
		label: makeButtonThemeText(
			__(
				'Light {{br/}}{{helpText}}Recommended for dark or colored backgrounds with high contrast.{{/helpText}}',
				'woocommerce-gateway-stripe'
			)
		),
		value: 'light',
	},
	{
		label: makeButtonThemeText(
			__(
				'Outline {{br/}}{{helpText}}Recommended for white or light-colored backgrounds with insufficient contrast.{{/helpText}}',
				'woocommerce-gateway-stripe'
			)
		),
		value: 'light-outline',
	},
];

const PaymentRequestsSection = () => {
	const [ buttonType, setButtonType ] = usePaymentRequestButtonType();
	const [ size, setSize ] = usePaymentRequestButtonSize();
	const [ theme, setTheme ] = usePaymentRequestButtonTheme();
	const accountId = useAccount().data?.account?.id;
	const [ publishableKey ] = useAccountKeysPublishableKey();
	const [ testPublishableKey ] = useAccountKeysTestPublishableKey();

	const stripePromise = useMemo( () => {
		return loadStripe(
			publishableKey || testPublishableKey || 'pk_test_123',
			{
				stripeAccount: accountId || '0001',
				locale: 'en',
			}
		);
	}, [ testPublishableKey, publishableKey, accountId ] );

	return (
		<Card>
			<CardBody>
				<h4>
					{ __( 'Call to action', 'woocommerce-gateway-stripe' ) }
				</h4>
				<RadioControl
					className="payment-method-settings__cta-selection"
					label={ __(
						'Call to action',
						'woocommerce-gateway-stripe'
					) }
					// ideLabelFromVision
					help={ __(
						'Select a button label that fits best with the flow of purchase or payment experience on your store.',
						'woocommerce-gateway-stripe'
					) }
					selected={ buttonType }
					options={ buttonActionOptions }
					onChange={ setButtonType }
				/>
				<h4>{ __( 'Appearance', 'woocommerce-gateway-stripe' ) }</h4>
				<RadioControl
					help={ __(
						'Note that larger buttons are more suitable for mobile use.',
						'woocommerce-gateway-stripe'
					) }
					label={ __( 'Size', 'woocommerce-gateway-stripe' ) }
					selected={ size }
					options={ buttonSizeOptions }
					onChange={ setSize }
				/>
				<RadioControl
					label={ __( 'Theme', 'woocommerce-gateway-stripe' ) }
					selected={ theme }
					options={ buttonThemeOptions }
					onChange={ setTheme }
				/>
				<p>{ __( 'Preview', 'woocommerce-gateway-stripe' ) }</p>
				<LoadableAccountSection numLines={ 7 }>
					<Elements stripe={ stripePromise }>
						<PaymentRequestButtonPreview />
					</Elements>
				</LoadableAccountSection>
			</CardBody>
		</Card>
	);
};

export default PaymentRequestsSection;
