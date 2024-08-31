import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import interpolateComponents from 'interpolate-components';
import {
	useAccountKeysWebhookURL,
	useAccountKeysTestWebhookURL,
	useAccountKeysWebhookSecret,
	useAccountKeysTestWebhookSecret,
} from 'wcstripe/data/account-keys';
import { useAccount } from 'wcstripe/data/account';

/**
 * Generates the webhook help text for the component.
 *
 * @param {Object} props            The component props.
 * @param {Function} props.testMode Whether the component is for test mode.
 *
 * @return {JSX.Element} The generated help text.
 */
const WebhookHelpText = ( { testMode } ) => {
	const mode = testMode ? 'test' : 'live';

	// Map the mode to the correct hooks.
	const getWebhookURL = testMode
		? useAccountKeysTestWebhookURL
		: useAccountKeysWebhookURL;
	const getWebhookSecret = testMode
		? useAccountKeysTestWebhookSecret
		: useAccountKeysWebhookSecret;

	const [ webhookURL ] = getWebhookURL();
	const [ webhookSecret ] = getWebhookSecret();
	const { data } = useAccount();
	const initialWebhookURL = data?.configured_webhook_urls?.[ mode ] ?? '';

	const [ webhookURLForDisplay, setDisplayWebhookURL ] = useState(
		initialWebhookURL
	);

	// If the webhook URL is changed via the hook, use that value in the component.
	useEffect( () => {
		if ( webhookURL ) {
			setDisplayWebhookURL( webhookURL );
		}
	}, [ webhookURL ] );

	let helpText = __(
		'Configuring webhooks will enable your store to receive notifications on charge statuses from Stripe.',
		'woocommerce-gateway-stripe'
	);

	if ( webhookSecret ) {
		helpText = webhookURLForDisplay
			? interpolateComponents( {
					mixedString: sprintf(
						/* translators: %s: the site's URL where webhooks will be sent.*/
						__(
							'Your webhooks are configured and will be sent to: {{webhookURL}}%s{{/webhookURL}}.',
							'woocommerce-gateway-stripe'
						),
						decodeURIComponent( webhookURLForDisplay )
					),
					components: {
						webhookURL: <strong />,
					},
			  } )
			: __(
					'Webhooks have been manually configured via a webhook secret.',
					'woocommerce-gateway-stripe'
			  );
	}

	return <p className="woocommerce-stripe-auth__help">{ helpText }</p>;
};

export default WebhookHelpText;
