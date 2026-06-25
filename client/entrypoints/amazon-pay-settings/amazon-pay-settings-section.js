/* global wc_stripe_amazon_pay_settings_params */
import { ADMIN_URL, getSetting } from '@woocommerce/settings';
import React from 'react';
import interpolateComponents from '@automattic/interpolate-components';
import styled from '@emotion/styled';
import ExpressCheckoutPreview from 'wcstripe/settings/express-checkout-preview';
import ExpressCheckoutSimulator from 'wcstripe/settings/express-checkout-simulator';
import {
	STATUS,
	buildBaseChecks,
	buildCurrencyCheck,
	buildLocations,
} from 'wcstripe/settings/express-checkout-simulator/build-checks';
import getReasonText from 'wcstripe/settings/express-checkout-simulator/get-reason-text';
import {
	Card,
	RadioControl,
	CheckboxControl,
	Notice,
} from '@wordpress/components';
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

const makeButtonSizeText = ( string ) =>
	interpolateComponents( {
		mixedString: string,
		components: {
			helpText: (
				<span className="amazon-pay-settings__option-muted-text" />
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

	// eslint-disable-next-line camelcase
	const isAccountCountrySupported = Boolean(
		previewParams?.is_account_country_supported
	);
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
		[ 'product', 'cart', 'checkout' ],
		amazonPayLocations
	);

	const makeLocationChangeHandler = ( location ) => ( isChecked ) => {
		if ( isChecked ) {
			updateAmazonPayLocations( [ ...amazonPayLocations, location ] );
		} else {
			updateAmazonPayLocations(
				amazonPayLocations.filter( ( name ) => name !== location )
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
				{ isButtonStyleOverridden && (
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
											getSetting( 'storePages' )?.checkout
												?.id
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
				) }
				<h4>
					{ __(
						'Show express checkouts on',
						'woocommerce-gateway-stripe'
					) }
				</h4>
				<ul className="payment-request-settings__location">
					<li>
						<CheckboxControl
							disabled={ ! isAmazonPayEnabled }
							checked={
								isAmazonPayEnabled &&
								amazonPayLocations.includes( 'checkout' )
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
							disabled={ ! isAmazonPayEnabled }
							checked={
								isAmazonPayEnabled &&
								amazonPayLocations.includes( 'product' )
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
							disabled={ ! isAmazonPayEnabled }
							checked={
								isAmazonPayEnabled &&
								amazonPayLocations.includes( 'cart' )
							}
							onChange={ makeLocationChangeHandler( 'cart' ) }
							label={ __( 'Cart', 'woocommerce-gateway-stripe' ) }
						/>
					</li>
				</ul>
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
