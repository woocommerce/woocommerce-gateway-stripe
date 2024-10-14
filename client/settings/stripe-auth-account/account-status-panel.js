import { __ } from '@wordpress/i18n';
import { Icon } from '@wordpress/components';
import { help, link, linkOff } from '@wordpress/icons';
import Chip from 'wcstripe/components/chip';
import Tooltip from 'wcstripe/components/tooltip';
import {
	useAccountKeys,
	useAccountKeysTestWebhookSecret,
	useAccountKeysWebhookSecret,
	useAccountKeysWebhookURL,
	useAccountKeysTestWebhookURL,
} from 'wcstripe/data/account-keys';
import { useAccount } from 'wcstripe/data/account';

/**
 * Generates a status chip.
 *
 * @param {Object} props           The component props.
 * @param {string} props.label     The label/type of the status.
 * @param {string} props.text      The text of the status. The actual status message. eg "Enabled".
 * @param {string} props.color     The color of the status.
 * @param {JSX.Element} props.icon The icon of the status.
 *
 * @return {JSX.Element} The status chip.
 */
const Status = ( { label, text, color, icon } ) => {
	return (
		<div className="wcstripe-status">
			<span className="wcstripe-status-text">{ label }</span>
			<Chip text={ text } color={ color } icon={ icon } />
		</div>
	);
};

/**
 * Generates an unconnected account tip.
 *
 * The tip is displayed when the account is incomplete and explains that the account is set up but not connected to
 * the WooCommerce Stripe App.
 *
 * @return {JSX.Element} The unauthenticated account tip.
 */
const unconnectedAccountTip = () => {
	return (
		<Tooltip
			content={ __(
				'Your store has Stripe Account keys, however, we cannot verify their origin, please re-connect.',
				'woocommerce-gateway-stripe'
			) }
		>
			<span>
				<Icon icon={ help } size="16" />
			</span>
		</Tooltip>
	);
};

/**
 * Generates an tip for accounts connected via the WooCommerce Stripe App.
 *
 * @return {JSX.Element} The connected via app tip.
 */
const connectedViaAppTip = () => {
	return (
		<Tooltip
			content={ __(
				'Your store is connected to Stripe via the WooCommerce Stripe App.',
				'woocommerce-gateway-stripe'
			) }
		>
			<span>
				<Icon icon={ link } size="16" />
			</span>
		</Tooltip>
	);
};

/**
 * Generates an tip for accounts connected via the WooCommerce Stripe App that has expired keys.
 *
 * @return {JSX.Element} The connected via app tip.
 */
const expiredAppKeysTip = () => {
	return (
		<Tooltip
			content={ __(
				'Your Stripe API keys have expired, please refresh your keys, or re-connect your account.',
				'woocommerce-gateway-stripe'
			) }
		>
			<span>
				<Icon icon={ linkOff } size="16" />
			</span>
		</Tooltip>
	);
};

/**
 * Gets the account status.
 *
 * An account can be in one of three states:
 *   - connected: The account is connected to WooCommerce.
 *   - unconnected: There are account keys stored, but the account isn't connected to the WooCommerce Stripe App.
 *   - disconnected: The account is not set up.
 *
 * @param {Object} accountKeys The account keys.
 * @param {Object} data        The account data.
 * @param {boolean} testMode   Whether the component is for test mode.
 *
 * @return {Object} The account status. Contains the status text (label), color, and optionally an icon.
 */
const getAccountStatus = ( accountKeys, data, testMode ) => {
	const secretKey = testMode
		? accountKeys.test_secret_key
		: accountKeys.secret_key;
	const publishableKey = testMode
		? accountKeys.test_publishable_key
		: accountKeys.publishable_key;

	// eslint-disable-next-line camelcase
	const { oauth_connections } = data;
	const oauthStatus = testMode // eslint-disable-next-line camelcase
		? oauth_connections?.test // eslint-disable-next-line camelcase
		: oauth_connections?.live;

	const hasKeys = secretKey && publishableKey;
	const isConnected = oauthStatus?.connected;
	const isConnectedViaApp = oauthStatus?.type === 'app';
	const isExpired = oauthStatus?.expired;

	const accountStatusMap = {
		connected: {
			text: __( 'Connected', 'woocommerce-gateway-stripe' ),
			color: 'green',
			icon: isConnectedViaApp ? connectedViaAppTip() : null,
		},
		unconnected: {
			text: __( 'Incomplete', 'woocommerce-gateway-stripe' ),
			color: 'yellow',
			icon: unconnectedAccountTip(),
		},
		disconnected: {
			text: __( 'Disconnected', 'woocommerce-gateway-stripe' ),
			color: 'red',
		},
		expired: {
			text: __( 'Expired', 'woocommerce-gateway-stripe' ),
			color: 'red',
			icon: expiredAppKeysTip(),
		},
	};

	if ( ! hasKeys ) {
		return accountStatusMap.disconnected;
	}

	if ( ! isConnected ) {
		return accountStatusMap.unconnected;
	}

	let accountStatus = 'connected';

	if ( isConnectedViaApp && isExpired ) {
		accountStatus = 'expired';
	}

	return accountStatusMap[ accountStatus ];
};

/**
 * Gets the webhook status.
 *
 * @param {string} webhookSecret  The webhook secret.
 * @param {boolean} hasWebhookURL Whether the webhook URL is set.
 *
 * @return {Object} The webhook status. Contains the status text (label), color, and optionally an icon.
 */
const getWebhookStatus = ( webhookSecret, hasWebhookURL ) => {
	// If webhook secret is set, then it's at the very least enabled. If it's not set, then it's disabled.
	let webhookStatus = webhookSecret ? 'enabled' : 'disabled';

	// If webhook secret and URL are set, then the webhook has been configured by us.
	if ( webhookSecret && hasWebhookURL ) {
		webhookStatus = 'configured';
	}

	const webhookStatusMap = {
		configured: {
			text: __( 'Configured', 'woocommerce-gateway-stripe' ),
			color: 'green',
		},
		enabled: {
			text: __( 'Enabled', 'woocommerce-gateway-stripe' ),
			color: 'green',
		},
		disabled: {
			text: __( 'Disabled', 'woocommerce-gateway-stripe' ),
			color: 'red',
		},
	};

	return webhookStatusMap[ webhookStatus ];
};

/**
 * The AccountStatusPanel component.
 *
 * @param {Object} props           The component props.
 * @param {boolean} props.testMode Indicates whether the component is for test mode.
 *
 * @return {JSX.Element} The rendered AccountStatusPanel component.
 */
const AccountStatusPanel = ( { testMode } ) => {
	const { accountKeys } = useAccountKeys();
	const { data } = useAccount();
	const getWebhookSecret = testMode
		? useAccountKeysTestWebhookSecret
		: useAccountKeysWebhookSecret;
	const getWebhookURL = testMode
		? useAccountKeysTestWebhookURL
		: useAccountKeysWebhookURL;
	const [ webhookSecret ] = getWebhookSecret();
	const [ webhookURL ] = getWebhookURL();
	const initialWebhookURL = testMode
		? data?.configured_webhook_urls?.test || ''
		: data?.configured_webhook_urls?.live || '';

	const accountStatus = getAccountStatus( accountKeys, data, testMode );
	const webhookStatus = getWebhookStatus(
		webhookSecret,
		initialWebhookURL || webhookURL
	);

	return (
		<div className="woocommerce-stripe-auth__status-panel">
			<Status
				label={ __( 'Account', 'woocommerce-gateway-stripe' ) }
				text={ accountStatus.text }
				color={ accountStatus.color }
				icon={ accountStatus?.icon }
			/>
			<Status
				label={ __( 'Webhooks', 'woocommerce-gateway-stripe' ) }
				text={ webhookStatus.text }
				color={ webhookStatus.color }
			/>
		</div>
	);
};

export default AccountStatusPanel;
