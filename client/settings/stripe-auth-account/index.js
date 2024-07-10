import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import interpolateComponents from 'interpolate-components';
import StripeAuthDiagram from './stripe-auth-diagram';
import StripeAuthActions from './stripe-auth-actions';
import './styles.scss';
import AccountStatusPanel from './account-status-panel';
import {
	useAccountKeysWebhookURL,
	useAccountKeysTestWebhookURL,
	useAccountKeysWebhookSecret,
	useAccountKeysTestWebhookSecret,
} from 'wcstripe/data/account-keys';
import { useAccount } from 'wcstripe/data/account';

/**
 * Generates the help text for the component based on the mode.
 *
 * @param {boolean} testMode - Indicates whether the component is in test mode.
 * @return {string} The generated help text.
 */
const getHelpText = ( testMode ) => {
	return interpolateComponents( {
		mixedString: testMode
			? __(
					'By clicking "Connect a test account", you agree to the {{tosLink}}Terms of service.{{/tosLink}}',
					'woocommerce-gateway-stripe'
			  )
			: __(
					'By clicking "Connect an account", you agree to the {{tosLink}}Terms of service.{{/tosLink}}',
					'woocommerce-gateway-stripe'
			  ),
		components: {
			tosLink: (
				// eslint-disable-next-line jsx-a11y/anchor-has-content
				<a
					target="_blank"
					rel="noreferrer"
					href="https://wordpress.com/tos"
				/>
			),
		},
	} );
};

/**
 * Generates the heading text for the component based on the mode.
 *
 * @param {boolean} testMode - Indicates whether the component is in test mode.
 * @return {string} The generated help text.
 */
const getHeading = ( testMode ) => {
	return testMode
		? __( 'Connect with Stripe in test mode', 'woocommerce-gateway-stripe' )
		: __(
				'Connect with Stripe in live mode',
				'woocommerce-gateway-stripe'
		  );
};

/**
 * Generates the webhook help text for the component based on the mode.
 *
 * @param {string} mode The mode of the webhook. Should be either 'live' or 'test'.
 * @return {JSX.Element} The generated help text.
 */
const getWebhookHelpComponentForMode = ( mode ) => {
	const getWebhookURL =
		mode === 'live'
			? useAccountKeysWebhookURL
			: useAccountKeysTestWebhookURL;
	const getWebhookSecret =
		mode === 'live'
			? useAccountKeysWebhookSecret
			: useAccountKeysTestWebhookSecret;

	return (
		<WebhookHelpComponent
			getWebhookURL={ getWebhookURL }
			getWebhookSecret={ getWebhookSecret }
			mode={ mode }
		/>
	);
};

/**
 * Generates the webhook help text for the component.
 *
 * @param {Object} props The component props.
 * @param {Function} props.getWebhookURL The hook to get the webhook URL.
 * @param {Function} props.getWebhookSecret The hook to get the webhook secret.
 * @param {string} props.mode The mode of the webhook. Should be either 'live' or 'test'.
 *
 * @return {JSX.Element} The generated help text.
 */
const WebhookHelpComponent = ( { getWebhookURL, getWebhookSecret, mode } ) => {
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
					'Your webhooks are configured.',
					'woocommerce-gateway-stripe'
			  );
	}

	return <p className="woocommerce-stripe-auth__help">{ helpText }</p>;
};

/**
 * StripeAuthAccount component.
 * Renders a Stripe authentication diagram, description and actions.
 *
 * @param {Object} props - The component props.
 * @param {boolean} props.testMode - Indicates whether the component is in test mode.
 * @return {JSX.Element} The rendered StripeAuthAccount component.
 */
const StripeAuthAccount = ( { testMode } ) => {
	return (
		<div className="woocommerce-stripe-auth">
			<StripeAuthDiagram />
			<AccountStatusPanel testMode={ testMode } />
			<h2>{ getHeading( testMode ) }</h2>
			<p>
				Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do
				eiusmod tempor incididunt ut labore et dolore magna aliqua. Nunc
				id cursus metus aliquam eleifend mi in nulla posuere.
			</p>
			<p className="woocommerce-stripe-auth__help">
				{ getHelpText( testMode ) }
			</p>
			<StripeAuthActions testMode={ testMode } />
			{ getWebhookHelpComponentForMode( testMode ? 'test' : 'live' ) }
		</div>
	);
};

export default StripeAuthAccount;
