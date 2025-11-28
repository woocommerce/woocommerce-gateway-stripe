import { ADMIN_URL, getSetting } from '@woocommerce/settings';
import React, { useMemo } from 'react';
import interpolateComponents from '@automattic/interpolate-components';
import { loadStripe } from '@stripe/stripe-js';
import styled from '@emotion/styled';
import ExpressCheckoutPreviewComponent from './express-checkout-preview-component';
import {
	Card,
	RadioControl,
	CheckboxControl,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	usePaymentRequestEnabledSettings,
	usePaymentRequestLocations,
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
				'Small {{helpText}}(40 px){{/helpText}}',
				'woocommerce-gateway-stripe'
			)
		),
		value: 'small',
	},
	{
		label: makeButtonSizeText(
			__(
				'Default {{helpText}}(48 px){{/helpText}}',
				'woocommerce-gateway-stripe'
			)
		),
		value: 'default',
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

const buttonThemeOptions = [
	{
		label: __( 'Dark', 'woocommerce-gateway-stripe' ),
		description: __(
			'Recommended for white or light-colored backgrounds with high contrast.',
			'woocommerce-gateway-stripe'
		),
		value: 'dark',
	},
	{
		label: __( 'Light', 'woocommerce-gateway-stripe' ),
		description: __(
			'Recommended for dark or colored backgrounds with high contrast.',
			'woocommerce-gateway-stripe'
		),
		value: 'light',
	},
	{
		label: __( 'Outline', 'woocommerce-gateway-stripe' ),
		description: __(
			'Recommended for white or light-colored backgrounds with insufficient contrast.',
			'woocommerce-gateway-stripe'
		),
		value: 'light-outline',
	},
];

const ExpressCheckoutSettingsSection = () => {
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

	const [ isPaymentRequestEnabled ] = usePaymentRequestEnabledSettings();

	const [ paymentRequestLocations, updatePaymentRequestLocations ] =
		usePaymentRequestLocations();

	const makeLocationChangeHandler = ( location ) => ( isChecked ) => {
		if ( isChecked ) {
			updatePaymentRequestLocations( [
				...paymentRequestLocations,
				location,
			] );
		} else {
			updatePaymentRequestLocations(
				paymentRequestLocations.filter( ( name ) => name !== location )
			);
		}
	};

	const StyledLink = styled.a`
		&:focus,
		&:visited {
			box-shadow: none;
		}
	`;

	return (
		<Card className="express-checkout-settings">
			<CardBody>
				<Notice status="warning" isDismissible={ false }>
					{ interpolateComponents( {
						mixedString: __(
							'Some appearance settings may be overridden by the express payment section of the ' +
								'{{checkoutPageLink}}Cart & Checkout blocks{{/checkoutPageLink}}.',
							'woocommerce-gateway-stripe'
						),
						components: {
							checkoutPageLink: (
								<StyledLink
									href={ `${ ADMIN_URL }post.php?post=${
										getSetting( 'storePages' )?.checkout?.id
									}&action=edit` }
									target="_blank"
									rel="noreferrer"
									onClick={ ( ev ) => {
										// Stop propagation is necessary so it doesn't trigger the tooltip click event.
										ev.stopPropagation();
									} }
								/>
							),
						},
					} ) }
				</Notice>
				<h4>
					{ __(
						'Show express checkouts on',
						'woocommerce-gateway-stripe'
					) }
				</h4>
				<ul className="payment-request-settings__location">
					<li>
						<CheckboxControl
							disabled={ ! isPaymentRequestEnabled }
							checked={
								isPaymentRequestEnabled &&
								paymentRequestLocations.includes( 'checkout' )
							}
							onChange={ makeLocationChangeHandler( 'checkout' ) }
							label={ __(
								'Checkout',
								'woocommerce-gateway-stripe'
							) }
						/>
					</li>
					<li>
						<CheckboxControl
							disabled={ ! isPaymentRequestEnabled }
							checked={
								isPaymentRequestEnabled &&
								paymentRequestLocations.includes( 'product' )
							}
							onChange={ makeLocationChangeHandler( 'product' ) }
							label={ __(
								'Product page',
								'woocommerce-gateway-stripe'
							) }
						/>
					</li>
					<li>
						<CheckboxControl
							disabled={ ! isPaymentRequestEnabled }
							checked={
								isPaymentRequestEnabled &&
								paymentRequestLocations.includes( 'cart' )
							}
							onChange={ makeLocationChangeHandler( 'cart' ) }
							label={ __( 'Cart', 'woocommerce-gateway-stripe' ) }
						/>
					</li>
				</ul>
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
					<ExpressCheckoutPreviewComponent
						stripe={ stripePromise }
						buttonType={ buttonType }
						theme={ theme }
						size={ size }
					/>
				</LoadableAccountSection>
			</CardBody>
		</Card>
	);
};

export default ExpressCheckoutSettingsSection;
