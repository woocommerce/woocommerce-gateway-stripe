import React from 'react';
import interpolateComponents from '@automattic/interpolate-components';
import { __ } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';
import './style.scss';

export const WebhookInformation = () => {
	return (
		<p
			className="wc-stripe-webhook-information"
			data-testid="webhook-information"
		>
			{ interpolateComponents( {
				mixedString: __(
					'Click the {{configureButtonText/}} button to {{settingsLink}}configure a webhook{{/settingsLink}}. This will complete your Stripe account connection process.',
					'woocommerce-gateway-stripe'
				),
				components: {
					configureButtonText: (
						<span className="wc-stripe-webhook-information__button-text">
							{ __(
								'Configure connection',
								'woocommerce-gateway-stripe'
							) }
						</span>
					),
					settingsLink: (
						<ExternalLink href="https://woocommerce.com/document/stripe/setup-and-configuration/stripe-webhooks/" />
					),
				},
			} ) }
		</p>
	);
};
