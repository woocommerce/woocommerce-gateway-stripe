/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import OnboardingWizard from './onboarding-wizard';
import UpeToggleContextProvider from '../settings/upe-toggle/provider';

const container = document.getElementById(
	'wc-stripe-onboarding-wizard-container'
);

if ( container ) {
	ReactDOM.render(
		(
			<UpeToggleContextProvider
				defaultIsUpeEnabled={ window.wc_stripe_onboarding_params.is_upe_checkout_enabled }
			>
				<OnboardingWizard />
			</UpeToggleContextProvider>
		),
		container
	);
}
