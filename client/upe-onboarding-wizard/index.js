/* global wc_stripe_onboarding_params */

import React from 'react';
import ReactDOM from 'react-dom';
import OnboardingWizard from './onboarding-wizard';
import UpeToggleContextProvider from 'wcstripe/settings/upe-toggle/provider';

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
