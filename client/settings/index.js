/* global wc_stripe_settings_params */
import React from 'react';
import ReactDOM from 'react-dom';
import ConnectStripeAccount from './connect-stripe-account';
import SettingsManager from './settings-manager';
import PaymentGatewayManager from './payment-gateway-manager';
import UpeToggleContextProvider from './upe-toggle/provider';
import './styles.scss';

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
	ReactDOM.render(
		<UpeToggleContextProvider
			defaultIsUpeEnabled={
				// eslint-disable-next-line camelcase
				wc_stripe_settings_params.is_upe_checkout_enabled === '1'
			}
		>
			<SettingsManager />
		</UpeToggleContextProvider>,
		settingsContainer
	);
}

if ( paymentGatewayContainer ) {
	ReactDOM.render(
		<UpeToggleContextProvider
			defaultIsUpeEnabled={
				// eslint-disable-next-line camelcase
				wc_stripe_settings_params.is_upe_checkout_enabled === '1'
			}
		>
			<PaymentGatewayManager />
		</UpeToggleContextProvider>,
		paymentGatewayContainer
	);
}

if ( newAccountContainer ) {
	ReactDOM.render(
		<ConnectStripeAccount
			oauthUrl={
				// eslint-disable-next-line camelcase
				wc_stripe_settings_params.stripe_oauth_url
			}
			testOauthUrl={
				// eslint-disable-next-line camelcase
				wc_stripe_settings_params.stripe_test_oauth_url
			}
		/>,
		newAccountContainer
	);
}
