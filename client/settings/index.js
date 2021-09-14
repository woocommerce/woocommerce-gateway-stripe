/* global wc_stripe_settings_params */
/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import ConnectStripeAccount from './connect-stripe-account';
import SettingsManager from './settings-manager';
import UpeToggleContextProvider from './upe-toggle/provider';
import './styles.scss';

const settingsContainer = document.getElementById(
	'wc-stripe-account-settings-container'
);

const newAccountContainer = document.getElementById(
	'wc-stripe-new-account-container'
);

if ( settingsContainer ) {
	ReactDOM.render(
		<UpeToggleContextProvider
			defaultIsUpeEnabled={
				wc_stripe_settings_params.is_upe_checkout_enabled === '1'
			}
		>
			<SettingsManager />
		</UpeToggleContextProvider>,
		settingsContainer
	);
}

if ( newAccountContainer ) {
	ReactDOM.render(
		<ConnectStripeAccount
			oauthUrl={ wc_stripe_settings_params.stripe_oauth_url }
		/>,
		newAccountContainer
	);
}
