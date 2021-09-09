/* global wc_stripe_onboarding_params */
/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import UpeToggleContextProvider from 'wcstripe/settings/upe-toggle/provider';
import OnboardingWizard from './onboarding-wizard';

const container = document.getElementById(
	'wc-stripe-onboarding-wizard-container'
);

if ( container ) {
	ReactDOM.render(
		<UpeToggleContextProvider
			defaultIsUpeEnabled={
				wc_stripe_onboarding_params.is_upe_checkout_enabled === '1'
			}
		>
			<OnboardingWizard />
		</UpeToggleContextProvider>,
		container
	);
}
