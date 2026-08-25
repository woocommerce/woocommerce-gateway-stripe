/* global wc_stripe_link_settings_params */
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
import { Card } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	useLinkLocations,
	useLinkButtonSize,
	useEnabledPaymentMethodIds,
} from 'wcstripe/data';
import {
	PAYMENT_METHOD_LINK,
	PAYMENT_METHOD_CARD,
} from 'wcstripe/stripe-utils/constants';
import CardBody from 'wcstripe/settings/card-body';
import LoadableAccountSection from 'wcstripe/settings/loadable-account-section';

const LinkSettingsSection = () => {
	const [ size, setSize ] = useLinkButtonSize();

	const [ enabledMethodIds ] = useEnabledPaymentMethodIds();
	const isLinkEnabled = enabledMethodIds.includes( PAYMENT_METHOD_LINK );

	const [ linkLocations, updateLinkLocations ] = useLinkLocations();

	const isButtonStyleOverridden =
		!! wc_stripe_link_settings_params?.is_button_style_overridden; // eslint-disable-line camelcase
	// eslint-disable-next-line camelcase
	const previewParams = wc_stripe_link_settings_params;
	// eslint-disable-next-line camelcase
	const isSubscriptionsActive = !! previewParams?.is_subscriptions_active;

	const methodLabel = __( 'Link by Stripe', 'woocommerce-gateway-stripe' );
	const simulatorChecks = [
		...buildBaseChecks( {
			params: previewParams,
			methodEnabled: isLinkEnabled,
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
		linkLocations
	);

	return (
		<Card className="express-checkout-settings">
			<CardBody>
				<ExpressCheckoutAppearanceOverrideNotice
					isOverridden={ isButtonStyleOverridden }
				/>
				<ExpressCheckoutLocationsControl
					methodEnabled={ isLinkEnabled }
					locations={ linkLocations }
					onChange={ updateLinkLocations }
					showChangePaymentMethod={ isSubscriptionsActive }
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
						paymentMethodTypes={ [ PAYMENT_METHOD_LINK, 'card' ] }
						paymentMethods={ {
							link: 'auto',
							amazonPay: 'never',
							googlePay: 'never',
							applePay: 'never',
							klarna: 'never',
						} }
						size={ size }
						errorMessage={ __(
							'Failed to preview the Link by Stripe button. ' +
								'Ensure your store uses HTTPS on a publicly available domain.',
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

export default LinkSettingsSection;
