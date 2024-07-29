/* global wc_stripe_settings_params */
import { __ } from '@wordpress/i18n';
import { React } from 'react';
import { Button } from '@wordpress/components';
import ConfigureWebhookButton from './configure-webhook-button';

/**
 * StripeAuthActions component.
 *
 * @param {Object} props                          The component props.
 * @param {boolean} props.testMode                Indicates whether the component is in test mode.
 * @param {boolean} props.displayWebhookConfigure Indicates whether to display the webhook configuration button.
 *
 * @return {JSX.Element} The rendered StripeAuthActions component.
 */
const StripeAuthActions = ( { testMode, displayWebhookConfigure } ) => {
	return (
		<div className="woocommerce-stripe-auth__actions">
			<Button
				variant="primary"
				href={
					testMode
						? wc_stripe_settings_params.stripe_test_oauth_url
						: wc_stripe_settings_params.stripe_oauth_url
				}
				text={
					testMode
						? __(
								'Create or connect a test account',
								'woocommerce-gateway-stripe'
						  )
						: __(
								'Create or connect an account',
								'woocommerce-gateway-stripe'
						  )
				}
			/>
			{ displayWebhookConfigure && (
				<ConfigureWebhookButton testMode={ testMode } />
			) }
		</div>
	);
};

export default StripeAuthActions;
