/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import SettingsManager from './settings-manager';

const settingsContainer = document.getElementById(
	'wc-stripe-account-settings-container'
);

if ( settingsContainer ) {
	ReactDOM.render( <SettingsManager />, settingsContainer );
}
