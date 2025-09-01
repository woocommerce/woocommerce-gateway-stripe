/* global wc_stripe_settings_params */
import React from 'react';
import { createRoot } from 'react-dom/client';
import ConnectStripeAccount from './connect-stripe-account';
import StripeAccountConnectedNotice from './stripe-account-connected-notice';
import SettingsManager from './settings-manager';
import PaymentGatewayManager from './payment-gateway-manager';
import UpeToggleContextProvider from './upe-toggle/provider';
import './styles.scss';
import OCToggleContextProvider from 'wcstripe/settings/oc-toggle/provider';

const settingsContainer = document.getElementById(
	'wc-stripe-account-settings-container'
);

const paymentGatewayContainer = document.getElementById(
	'wc-stripe-payment-gateway-container'
);

const newAccountContainer = document.getElementById(
	'wc-stripe-new-account-container'
);

if ( settingsContainer ) {
	createRoot( settingsContainer ).render(
		<UpeToggleContextProvider
			defaultIsUpeEnabled={
				// eslint-disable-next-line camelcase
				wc_stripe_settings_params.is_upe_checkout_enabled === '1'
			}
		>
			<OCToggleContextProvider
				defaultIsOCEnabled={
					// eslint-disable-next-line camelcase
					wc_stripe_settings_params.is_oc_enabled === '1'
				}
			>
				<StripeAccountConnectedNotice />
				<SettingsManager />
			</OCToggleContextProvider>
		</UpeToggleContextProvider>
	);
}

if ( paymentGatewayContainer ) {
	createRoot( paymentGatewayContainer ).render(
		<UpeToggleContextProvider
			defaultIsUpeEnabled={
				// eslint-disable-next-line camelcase
				wc_stripe_settings_params.is_upe_checkout_enabled === '1'
			}
		>
			<OCToggleContextProvider
				defaultIsOCEnabled={
					// eslint-disable-next-line camelcase
					wc_stripe_settings_params.is_oc_enabled === '1'
				}
			>
				<PaymentGatewayManager />
			</OCToggleContextProvider>
		</UpeToggleContextProvider>
	);
}

if ( newAccountContainer ) {
	createRoot( newAccountContainer ).render(
		<ConnectStripeAccount
			oauthUrl={
				// eslint-disable-next-line camelcase
				wc_stripe_settings_params.stripe_oauth_url
			}
			testOauthUrl={
				// eslint-disable-next-line camelcase
				wc_stripe_settings_params.stripe_test_oauth_url
			}
		/>
	);
}
