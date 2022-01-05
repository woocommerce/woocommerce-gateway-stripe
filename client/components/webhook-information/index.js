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
		<p>
			{ interpolateComponents( {
				mixedString: __(
					"Add the following webhook endpoint {{webhookUrl/}} to your {{settingsLink}}Stripe account settings{{/settingsLink}} (if there isn't one already). This will enable you to receive notifications on the charge statuses.",
					'woocommerce-gateway-stripe'
				),
				components: {
					webhookUrl: (
						<WebhookEndpointText>
							{ data.webhook_url }
						</WebhookEndpointText>
					),
					settingsLink: (
						<ExternalLink href="https://dashboard.stripe.com/account/webhooks" />
					),
				},
			} ) }
		</p>
	);
};
