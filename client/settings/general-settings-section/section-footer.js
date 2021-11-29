import { __ } from '@wordpress/i18n';
import React from 'react';
import { ExternalLink } from '@wordpress/components';
import CardFooter from 'wcstripe/settings/card-footer';
import PaymentMethodsDisabledList from 'wcstripe/settings/general-settings-section/payment-methods-disabled-list';

const SectionFooter = () => (
	<CardFooter>
		<div style={ { display: 'flex', alignItems: 'center' } }>
			<ExternalLink
				className="components-button is-secondary"
				href="https://dashboard.stripe.com/settings/payments"
			>
				{ __(
					'Get more payment methods',
					'woocommerce-gateway-stripe'
				) }
			</ExternalLink>
			<PaymentMethodsDisabledList />
		</div>
	</CardFooter>
);

export default SectionFooter;
