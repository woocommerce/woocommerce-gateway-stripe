/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import SettingsManager from './settings-manager';
import UpeToggleContextProvider from './upe-toggle/provider';
import './styles.scss';

const settingsContainer = document.getElementById(
	'wc-stripe-account-settings-container'
);

if ( settingsContainer ) {
	ReactDOM.render(
		<UpeToggleContextProvider
			defaultIsUpeEnabled={
				woocommerce_stripe_admin.upe_setting_value === 'yes'
			}
		>
			<SettingsManager />
		</UpeToggleContextProvider>,
		settingsContainer
	);
}
