import { ADMIN_URL, getSetting } from '@woocommerce/settings';
import { __ } from '@wordpress/i18n';
import React, { useMemo } from 'react';
import {
	Card,
	RadioControl,
	CheckboxControl,
	Notice,
} from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import { loadStripe } from '@stripe/stripe-js';
import styled from '@emotion/styled';
import ExpressCheckoutPreviewComponent from './express-checkout-button-preview';
import {
	useAmazonPayEnabledSettings,
	useAmazonPayLocations,
	useAmazonPayButtonSize,
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

	const [ isAmazonPayEnabled ] = useAmazonPayEnabledSettings();

	const [
		amazonPayLocations,
		updateAmazonPayLocations,
	] = useAmazonPayLocations();

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
				<Notice status="warning" isDismissible={ false }>
					{ interpolateComponents( {
						mixedString: __(
							'Some appearance settings may be overridden by the express payment section of the ' +
								'{{checkoutPageLink}}Cart & Checkout blocks{{/checkoutPageLink}}. ' +
								'Follow the instructions there and check back soon.',
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
					<ExpressCheckoutPreviewComponent
						stripe={ stripePromise }
						size={ size }
					/>
				</LoadableAccountSection>
			</CardBody>
		</Card>
	);
};

export default AmazonPaySettingsSection;
