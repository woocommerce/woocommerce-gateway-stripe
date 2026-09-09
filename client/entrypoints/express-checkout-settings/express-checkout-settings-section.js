/* global wc_stripe_express_checkout_settings_params */
import React from 'react';
import ExpressCheckoutPreview from 'wcstripe/settings/express-checkout-preview';
import ExpressCheckoutSimulator from 'wcstripe/settings/express-checkout-simulator';
import {
	buildBaseChecks,
	buildCardMethodCheck,
	buildLocations,
} from 'wcstripe/settings/express-checkout-simulator/build-checks';
import {
	ExpressCheckoutAppearanceOverrideNotice,
	ExpressCheckoutButtonSizeControl,
	ExpressCheckoutLocationsControl,
	getExpressCheckoutLocationKeys,
} from 'wcstripe/settings/express-checkout-customize';
import { Card, RadioControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	useExpressCheckoutEnabledSettings,
	useExpressCheckoutLocations,
	useExpressCheckoutButtonType,
	useExpressCheckoutButtonSize,
	useExpressCheckoutButtonTheme,
	useEnabledPaymentMethodIds,
} from 'wcstripe/data';
import { PAYMENT_METHOD_CARD } from 'wcstripe/stripe-utils/constants';
import CardBody from 'wcstripe/settings/card-body';
import LoadableAccountSection from 'wcstripe/settings/loadable-account-section';

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
	const [ buttonType, setButtonType ] = useExpressCheckoutButtonType();
	const [ size, setSize ] = useExpressCheckoutButtonSize();
	const [ theme, setTheme ] = useExpressCheckoutButtonTheme();
	const isButtonStyleOverridden =
		!! wc_stripe_express_checkout_settings_params?.is_button_style_overridden; // eslint-disable-line camelcase
	// eslint-disable-next-line camelcase
	const previewParams = wc_stripe_express_checkout_settings_params;
	// eslint-disable-next-line camelcase
	const isSubscriptionsActive = !! previewParams?.is_subscriptions_active;

	const [ isExpressCheckoutEnabled ] = useExpressCheckoutEnabledSettings();

	const [ expressCheckoutLocations, updateExpressCheckoutLocations ] =
		useExpressCheckoutLocations();

	const [ enabledMethodIds ] = useEnabledPaymentMethodIds();

	const methodLabel = __(
		'Apple Pay / Google Pay',
		'woocommerce-gateway-stripe'
	);
	const simulatorChecks = [
		...buildBaseChecks( {
			params: previewParams,
			methodEnabled: isExpressCheckoutEnabled,
			methodLabel,
		} ),
		buildCardMethodCheck( {
			isCardEnabled: enabledMethodIds.includes( PAYMENT_METHOD_CARD ),
			methodLabel,
		} ),
	];
	const simulatorLocations = buildLocations(
		getExpressCheckoutLocationKeys( {
			includeChangePaymentMethod: isSubscriptionsActive,
		} ),
		expressCheckoutLocations
	);

	return (
		<Card className="express-checkout-settings">
			<CardBody>
				<ExpressCheckoutAppearanceOverrideNotice
					isOverridden={ isButtonStyleOverridden }
				/>
				<ExpressCheckoutLocationsControl
					methodEnabled={ isExpressCheckoutEnabled }
					locations={ expressCheckoutLocations }
					onChange={ updateExpressCheckoutLocations }
					showChangePaymentMethod={ isSubscriptionsActive }
				/>
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
				<ExpressCheckoutButtonSizeControl
					size={ size }
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
					<ExpressCheckoutPreview
						params={ previewParams }
						paymentMethodTypes={ [ PAYMENT_METHOD_CARD ] }
						paymentMethods={ {
							link: 'never',
							googlePay: 'always',
							applePay: 'always',
							amazonPay: 'never',
							klarna: 'never',
						} }
						buttonType={ buttonType }
						theme={ theme }
						size={ size }
						requireExpressCheckoutEnabled
						isExpressCheckoutEnabled={ isExpressCheckoutEnabled }
						errorMessage={ __(
							'Failed to preview the Apple Pay or Google Pay button. ' +
								'Ensure your store uses HTTPS on a publicly available domain ' +
								"and you're viewing this page in a Safari or Chrome browser. " +
								'Your device must be configured to use Apple Pay or Google Pay.',
							'woocommerce-gateway-stripe'
						) }
					/>
				</LoadableAccountSection>
				<ExpressCheckoutSimulator
					checks={ simulatorChecks }
					locations={ simulatorLocations }
				/>
			</CardBody>
		</Card>
	);
};

export default ExpressCheckoutSettingsSection;
