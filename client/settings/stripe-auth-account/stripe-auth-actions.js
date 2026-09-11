import { React, useState } from 'react';
import ConnectButton from './connect-button';
import ConfigureWebhookButton from './configure-webhook-button';
import ConnectionErrorNotice from './connection-error-notice';

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
	const [ error, setError ] = useState( null );

	return (
		<>
			{ error && (
				<ConnectionErrorNotice
					message={ typeof error === 'string' ? error : undefined }
				/>
			) }
			<div className="woocommerce-stripe-auth__actions">
				<ConnectButton
					testMode={ testMode }
					buttonVariant="primary"
					onErrorChange={ setError }
				/>
				{ displayWebhookConfigure && (
					<ConfigureWebhookButton testMode={ testMode } />
				) }
			</div>
		</>
	);
};

export default StripeAuthActions;
