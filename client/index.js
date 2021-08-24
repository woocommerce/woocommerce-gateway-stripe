/**
 * External dependencies
 */
 import { __ } from '@wordpress/i18n';
 import { addFilter } from '@wordpress/hooks';

import OnboardingWizard from './additional-methods-setup/onboarding-wizard.js';

addFilter(
	'woocommerce_admin_pages_list',
	'woocommerce-gateway-stripe',
	( pages ) => {
		pages.push( {
			container: OnboardingWizard,
			path: '/onboarding',
			breadcrumbs: [ __('Onboarding', 'woocommerce-gateway-stripe') ],
			navArgs: {
				id: 'wc-stripe-onboarding',
			},
		} );

		return pages;
	}
);
