/* global wc_stripe_upe_opt_in_params */
import { __, sprintf } from '@wordpress/i18n';
import React from 'react';
import ReactDOM from 'react-dom';
import styled from '@emotion/styled';
import AllPaymentMethodsIcon from './all-payment-methods';
import UpeOptInBanner from 'wcstripe/settings/upe-opt-in-banner';

const StyledUpeOptInBanner = styled( UpeOptInBanner )`
	max-width: 680px;
	margin: 12px 0;
`;

const bannerContainer = document.getElementById(
	'wc-stripe-upe-opt-in-banner'
);

if ( bannerContainer ) {
	ReactDOM.render(
		<StyledUpeOptInBanner
			title={ __(
				'Enable the new Stripe payment management experience',
				'woocommerce-gateway-stripe'
			) }
			description={ sprintf(
				/* translators: %s: a payment method name (e.g.: Stripe giropay, Stripe SEPA, Stripe Sofort, etc). */
				__(
					'Spend less time managing %s and other payment methods in an improved settings and checkout experience, now available to select merchants.',
					'woocommerce-gateway-stripe'
				),
				wc_stripe_upe_opt_in_params.method_name
			) }
			Image={ AllPaymentMethodsIcon }
		/>,
		bannerContainer
	);
}
