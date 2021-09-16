/* global wc_stripe_settings_params */

import React from 'react';
import ReactDOM from 'react-dom';
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
				wc_stripe_settings_params.is_upe_checkout_enabled === '1'
			}
		>
			<SettingsManager />
		</UpeToggleContextProvider>,
		settingsContainer
	);
}
