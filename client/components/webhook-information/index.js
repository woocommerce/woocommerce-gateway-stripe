import { __ } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';
import styled from '@emotion/styled';
import interpolateComponents from 'interpolate-components';
import { useAccount } from 'wcstripe/data/account';

const WebhookEndpointText = styled.strong`
	padding: 0 2px;
	background-color: #f6f7f7; // $studio-gray-0
`;

export const WebhookInformation = () => {
	const { data } = useAccount();
	return (
		<p data-testid="webhook-information">
			{ interpolateComponents( {
				mixedString: __(
					'Click the Edit account keys button to {{settingsLink}}configure a webhook{{/settingsLink}}. This will complete your Stripe account connection process.',
					'woocommerce-gateway-stripe'
				),
				components: {
					webhookUrl: (
						<WebhookEndpointText>
							{ data.webhook_url }
						</WebhookEndpointText>
					),
					settingsLink: (
						<ExternalLink href="https://woocommerce.com/document/stripe/setup-and-configuration/stripe-webhooks/" />
					),
				},
			} ) }
		</p>
	);
};
