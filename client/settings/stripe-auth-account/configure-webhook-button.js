import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import {
	useAccountKeys,
	useAccountKeysSecretKey,
	useAccountKeysWebhookSecret,
	useAccountKeysTestSecretKey,
	useAccountKeysTestWebhookSecret,
} from 'wcstripe/data/account-keys';

/**
 * WebhookSecretComponent component.
 *
 * @param {Object} props                    The component props.
 * @param {boolean} props.secretKeyHook     Indicates whether the component is in test mode.
 * @param {boolean} props.webhookSecretHook Indicates whether the component is in test mode.
 * @param {boolean} props.liveMode          Indicates whether the component is in test mode.
 *
 * @return {JSX.Element} The rendered StripeAuthAccount component.
 */
const WebhookSecretComponent = ( {
	secretKeyHook,
	webhookSecretHook,
	liveMode,
} ) => {
	const { configureWebhooks, isConfiguring } = useAccountKeys();
	const [ secretKey ] = secretKeyHook();
	const [ webhookSecret ] = webhookSecretHook();

	const hasSecretKey = Boolean( secretKey );
	const buttonType = webhookSecret ? 'secondary' : 'primary';
	const buttonText = webhookSecret
		? __( 'Reconfigure webhooks', 'woocommerce-gateway-stripe' )
		: __( 'Configure webhooks', 'woocommerce-gateway-stripe' );

	return (
		<Button
			disabled={ isConfiguring || ! hasSecretKey }
			isBusy={ isConfiguring }
			onClick={ () => {
				configureWebhooks( {
					live: liveMode,
					secret: secretKey,
				} );
			} }
			variant={ buttonType }
			text={ buttonText }
			style={ {
				display: 'block',
			} }
		/>
	);
};

/**
 * ConfigureWebhookButton component.
 *
 * @param {Object} props           The component props.
 * @param {boolean} props.testMode Indicates whether this is for test mode.
 *
 * @return {JSX.Element} The rendered ConfigureWebhookButton component.
 */
const ConfigureWebhookButton = ( { testMode } ) => {
	return testMode ? (
		<WebhookSecretComponent
			secretKeyHook={ useAccountKeysTestSecretKey }
			webhookSecretHook={ useAccountKeysTestWebhookSecret }
			liveMode={ false }
		/>
	) : (
		<WebhookSecretComponent
			secretKeyHook={ useAccountKeysSecretKey }
			webhookSecretHook={ useAccountKeysWebhookSecret }
			liveMode={ true }
		/>
	);
};

export default ConfigureWebhookButton;
