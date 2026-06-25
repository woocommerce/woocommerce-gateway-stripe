/* global wc_stripe_amazon_pay_settings_params */
import React from 'react';
import ExpressCheckoutPreview from 'wcstripe/settings/express-checkout-preview';
import ExpressCheckoutSimulator from 'wcstripe/settings/express-checkout-simulator';
import {
	STATUS,
	buildBaseChecks,
	buildCurrencyCheck,
	buildLocations,
} from 'wcstripe/settings/express-checkout-simulator/build-checks';
import getReasonText from 'wcstripe/settings/express-checkout-simulator/get-reason-text';
import { isAmazonPayAccountCountrySupported } from 'utils/use-payment-method-currencies';
import {
	ExpressCheckoutAppearanceOverrideNotice,
	ExpressCheckoutButtonSizeControl,
	ExpressCheckoutLocationsControl,
	getExpressCheckoutLocationKeys,
} from 'wcstripe/settings/express-checkout-customize';
import { Card } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	useAmazonPayEnabledSettings,
	useAmazonPayLocations,
	useAmazonPayButtonSize,
} from 'wcstripe/data';
import {
	PAYMENT_METHOD_AMAZON_PAY,
	PAYMENT_METHOD_UNAVAILABLE_REASONS,
} from 'wcstripe/stripe-utils/constants';
import CardBody from 'wcstripe/settings/card-body';
import LoadableAccountSection from 'wcstripe/settings/loadable-account-section';

const AmazonPaySettingsSection = () => {
	const [ size, setSize ] = useAmazonPayButtonSize();
	const isButtonStyleOverridden =
		!! wc_stripe_amazon_pay_settings_params?.is_button_style_overridden; // eslint-disable-line camelcase
	// eslint-disable-next-line camelcase
	const previewParams = wc_stripe_amazon_pay_settings_params;

	const [ isAmazonPayEnabled ] = useAmazonPayEnabledSettings();

	const [ amazonPayLocations, updateAmazonPayLocations ] =
		useAmazonPayLocations();

	const methodLabel = __( 'Amazon Pay', 'woocommerce-gateway-stripe' );

	const isAccountCountrySupported = isAmazonPayAccountCountrySupported();
	// eslint-disable-next-line camelcase
	const isTaxBasedOnBilling = Boolean(
		previewParams?.taxes_based_on_billing
	);

	const simulatorChecks = [
		...buildBaseChecks( {
			params: previewParams,
			methodEnabled: isAmazonPayEnabled,
			methodLabel,
		} ),
		{
			key: 'account-country',
			label: __(
				'Account country supported',
				'woocommerce-gateway-stripe'
			),
			status: isAccountCountrySupported ? STATUS.PASS : STATUS.FAIL,
			detail: '',
			blockingText: __(
				"Amazon Pay isn't supported for your Stripe account's country.",
				'woocommerce-gateway-stripe'
			),
		},
		buildCurrencyCheck( {
			methodId: PAYMENT_METHOD_AMAZON_PAY,
			methodLabel,
		} ),
		{
			key: 'tax-setup',
			label: __( 'Compatible tax setup', 'woocommerce-gateway-stripe' ),
			status: isTaxBasedOnBilling ? STATUS.FAIL : STATUS.PASS,
			detail: '',
			blockingText: getReasonText(
				PAYMENT_METHOD_UNAVAILABLE_REASONS.TAX_BASED_ON_BILLING_ADDRESS,
				methodLabel
			),
		},
	].filter( Boolean );

	const simulatorLocations = buildLocations(
		getExpressCheckoutLocationKeys(),
		amazonPayLocations
	);

	return (
		<Card className="express-checkout-settings">
			<CardBody>
				<ExpressCheckoutAppearanceOverrideNotice
					isOverridden={ isButtonStyleOverridden }
				/>
				<ExpressCheckoutLocationsControl
					methodEnabled={ isAmazonPayEnabled }
					locations={ amazonPayLocations }
					onChange={ updateAmazonPayLocations }
				/>
				<h4>{ __( 'Appearance', 'woocommerce-gateway-stripe' ) }</h4>
				<ExpressCheckoutButtonSizeControl
					size={ size }
					onChange={ setSize }
				/>
				<p>{ __( 'Preview', 'woocommerce-gateway-stripe' ) }</p>
				<LoadableAccountSection numLines={ 7 }>
					<ExpressCheckoutPreview
						params={ previewParams }
						paymentMethodTypes={ [ PAYMENT_METHOD_AMAZON_PAY ] }
						paymentMethods={ {
							amazonPay: 'auto',
							link: 'never',
							googlePay: 'never',
							applePay: 'never',
							klarna: 'never',
						} }
						size={ size }
						errorMessage={ __(
							'Failed to preview the Amazon Pay button. ' +
								'Ensure your store uses HTTPS on a publicly available domain ' +
								"and you're viewing this page in a Safari or Chrome browser. " +
								'Your device must be configured to use Amazon Pay.',
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

export default AmazonPaySettingsSection;
