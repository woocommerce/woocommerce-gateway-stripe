import { __ } from '@wordpress/i18n';
import React from 'react';
import { ExternalLink } from '@wordpress/components';
import CardFooter from 'wcstripe/settings/card-footer';
import PaymentMethodsUnavailableList from 'wcstripe/settings/general-settings-section/payment-methods-unavailable-list';

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
			<PaymentMethodsUnavailableList />
		</div>
	</CardFooter>
);

export default SectionFooter;
