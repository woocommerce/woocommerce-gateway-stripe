import { React } from 'react';
import ConnectButton from './connect-button';
import ConfigureWebhookButton from './configure-webhook-button';

/**
 * StripeAuthActions component.
 *
 * @param {Object}  props                         The component props.
 * @param {boolean} props.testMode                Indicates whether the component is in test mode.
 * @param {boolean} props.displayWebhookConfigure Indicates whether to display the webhook configuration button.
 *
 * @return {JSX.Element} The rendered StripeAuthActions component.
 */
const StripeAuthActions = ( { testMode, displayWebhookConfigure } ) => {
	return (
		<div className="woocommerce-stripe-auth__actions">
			<ConnectButton testMode={ testMode } buttonVariant="primary" />
			{ displayWebhookConfigure && (
				<ConfigureWebhookButton testMode={ testMode } />
			) }
		</div>
	);
};

export default StripeAuthActions;
