/* global wc_stripe_link_settings_params */
import { ADMIN_URL, getSetting } from '@woocommerce/settings';
import React from 'react';
import interpolateComponents from '@automattic/interpolate-components';
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
	useLinkLocations,
	useLinkButtonSize,
	useEnabledPaymentMethodIds,
} from 'wcstripe/data';
import { PAYMENT_METHOD_LINK } from 'wcstripe/stripe-utils/constants';
import CardBody from 'wcstripe/settings/card-body';
import LoadableAccountSection from 'wcstripe/settings/loadable-account-section';

const makeButtonSizeText = ( string ) =>
	interpolateComponents( {
		mixedString: string,
		components: {
			helpText: <span className="link-settings__option-muted-text" />,
		},
	} );
const StyledLink = styled.a`
	&:visited {
		box-shadow: none;
	}
	&:focus-visible {
		outline: 2px solid currentColor;
		outline-offset: 2px;
	}
`;

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

const LinkSettingsSection = () => {
	const [ size, setSize ] = useLinkButtonSize();

	const [ enabledMethodIds ] = useEnabledPaymentMethodIds();
	const isLinkEnabled = enabledMethodIds.includes( PAYMENT_METHOD_LINK );

	const [ linkLocations, updateLinkLocations ] = useLinkLocations();

	const makeLocationChangeHandler = ( location ) => ( isChecked ) => {
		if ( isChecked ) {
			updateLinkLocations(
				linkLocations.includes( location )
					? linkLocations
					: [ ...linkLocations, location ]
			);
		} else {
			updateLinkLocations(
				linkLocations.filter( ( name ) => name !== location )
			);
		}
	};

	const checkoutPageId = getSetting( 'storePages' )?.checkout?.id;

	const isButtonStyleOverridden =
		!! wc_stripe_link_settings_params?.is_button_style_overridden; // eslint-disable-line camelcase

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
								checkoutPageLink: checkoutPageId ? (
									<StyledLink
										href={ `${ ADMIN_URL }post.php?post=${ checkoutPageId }&action=edit` }
										target="_blank"
										rel="noreferrer"
										onClick={ ( ev ) => {
											// Stop propagation is necessary so it doesn't trigger the tooltip click event.
											ev.stopPropagation();
										} }
									/>
								) : (
									<span />
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
							disabled={ ! isLinkEnabled }
							checked={
								isLinkEnabled &&
								linkLocations.includes( 'checkout' )
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
							disabled={ ! isLinkEnabled }
							checked={
								isLinkEnabled &&
								linkLocations.includes( 'product' )
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
							disabled={ ! isLinkEnabled }
							checked={
								isLinkEnabled &&
								linkLocations.includes( 'cart' )
							}
							onChange={ makeLocationChangeHandler( 'cart' ) }
							label={ __( 'Cart', 'woocommerce-gateway-stripe' ) }
						/>
					</li>
					{
						// eslint-disable-next-line camelcase
						wc_stripe_link_settings_params?.is_subscriptions_active && (
							<li>
								<CheckboxControl
									disabled={ ! isLinkEnabled }
									checked={
										isLinkEnabled &&
										linkLocations.includes(
											'change_payment_method'
										)
									}
									onChange={ makeLocationChangeHandler(
										'change_payment_method'
									) }
									label={ __(
										'Change payment method for WooCommerce Subscriptions',
										'woocommerce-gateway-stripe'
									) }
								/>
							</li>
						)
					}
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
					<ExpressCheckoutPreviewComponent size={ size } />
				</LoadableAccountSection>
			</CardBody>
		</Card>
	);
};

export default LinkSettingsSection;
