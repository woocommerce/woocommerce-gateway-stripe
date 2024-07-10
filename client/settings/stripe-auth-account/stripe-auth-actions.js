import { React } from 'react';
import { Button } from '@wordpress/components';
import ConfigureWebhookButton from './configure-webhook-button';

const StripeAuthActions = ( { testMode } ) => {
	return (
		<div className="woocommerce-stripe-auth__actions">
			<Button
				variant="primary"
				text={
					testMode ? 'Connect a test account' : 'Connect an account'
				}
			/>
			<ConfigureWebhookButton testMode={ testMode } />
		</div>
	);
};

export default StripeAuthActions;
