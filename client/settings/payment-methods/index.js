/* global wc_stripe_settings_params */
import { __ } from '@wordpress/i18n';
import React, { useContext, useState } from 'react';
import { ExternalLink } from '@wordpress/components';
import SettingsSection from '../settings-section';
import PaymentRequestSection from '../payment-request-section';
import GeneralSettingsSection from '../general-settings-section';
import LoadableSettingsSection from '../loadable-settings-section';
import DisplayOrderCustomizationNotice from '../display-order-customization-notice';
import { NEW_CHECKOUT_EXPERIENCE_BANNER } from 'wcstripe/settings/payment-settings/constants';
import PromotionalBannerSection from 'wcstripe/settings/payment-settings/promotional-banner-section';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';
import { useAccount } from 'wcstripe/data/account';

const PaymentMethodsDescription = () => {
	return (
		<>
			<h2>
				{ __(
					'Payments accepted on checkout',
					'woocommerce-gateway-stripe'
				) }
			</h2>

			<p>
				{ __(
					'Select payments available to customers at checkout. ' +
						'Based on their device type, location, and purchase history, ' +
						'your customers will only see the most relevant payment methods.',
					'woocommerce-gateway-stripe'
				) }
			</p>
		</>
	);
};

const PaymentRequestDescription = () => (
	<>
		<h2>{ __( 'Express checkouts', 'woocommerce-gateway-stripe' ) }</h2>
		<p>
			{ __(
				'Let your customers use their favorite express payment methods and digital wallets for faster, more secure checkouts across different parts of your store.',
				'woocommerce-gateway-stripe'
			) }
		</p>
		<ExternalLink href="https://woocommerce.com/document/stripe/customer-experience/express-checkouts/">
			{ __( 'Learn more', 'woocommerce-gateway-stripe' ) }
		</ExternalLink>
	</>
);

const PaymentMethodsPanel = ( { onSaveChanges } ) => {
	const [ showPromotionalBanner, setShowPromotionalBanner ] = useState(
		true
	);
	const [ promotionalBannerType, setPromotionalBannerType ] = useState( '' );
	const { isUpeEnabled, setIsUpeEnabled } = useContext( UpeToggleContext );
	const { data } = useAccount();
	const isTestModeEnabled = Boolean( data.testmode );
	const oauthConnected = isTestModeEnabled
		? data?.oauth_connections?.test?.connected
		: data?.oauth_connections?.live?.connected;

	return (
		<>
			{ showPromotionalBanner && (
				<SettingsSection>
					<PromotionalBannerSection
						setShowPromotionalBanner={ setShowPromotionalBanner }
						setPromotionalBannerType={ setPromotionalBannerType }
						isUpeEnabled={ isUpeEnabled }
						setIsUpeEnabled={ setIsUpeEnabled }
						isConnectedViaOAuth={ oauthConnected }
						oauthUrl={
							// eslint-disable-next-line camelcase
							wc_stripe_settings_params.stripe_oauth_url
						}
						testOauthUrl={
							// eslint-disable-next-line camelcase
							wc_stripe_settings_params.stripe_test_oauth_url
						}
					/>
				</SettingsSection>
			) }
			<SettingsSection Description={ PaymentMethodsDescription }>
				<DisplayOrderCustomizationNotice />
				<GeneralSettingsSection
					onSaveChanges={ onSaveChanges }
					showLegacyExperienceTransitionNotice={
						promotionalBannerType !== NEW_CHECKOUT_EXPERIENCE_BANNER
					}
				/>
			</SettingsSection>
			<SettingsSection Description={ PaymentRequestDescription }>
				<LoadableSettingsSection numLines={ 20 }>
					<PaymentRequestSection />
				</LoadableSettingsSection>
			</SettingsSection>
		</>
	);
};

export default PaymentMethodsPanel;
