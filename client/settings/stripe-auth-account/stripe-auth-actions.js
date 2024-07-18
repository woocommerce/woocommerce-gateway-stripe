/* global wc_stripe_settings_params */
import { React } from 'react';
import { Button } from '@wordpress/components';
import ConfigureWebhookButton from './configure-webhook-button';

/**
 * StripeAuthActions component.
 *
 * @param {Object} props           The component props.
 * @param {boolean} props.testMode Indicates whether the component is in test mode.
 *
 * @return {JSX.Element} The rendered StripeAuthActions component.
 */
const StripeAuthActions = ( { testMode } ) => {
	return (
		<div className="woocommerce-stripe-auth__actions">
			<Button
				variant="primary"
				href={ wc_stripe_settings_params.stripe_oauth_url }
				text={
					testMode ? 'Connect a test account' : 'Connect an account'
				}
			/>
			<ConfigureWebhookButton testMode={ testMode } />
		</div>
	);
};

export default StripeAuthActions;
