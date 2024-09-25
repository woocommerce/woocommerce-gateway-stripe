/* global wc_stripe_settings_params */
import { __ } from '@wordpress/i18n';
import { React } from 'react';
import { Button, ExternalLink } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import ConfigureWebhookButton from './configure-webhook-button';
import InlineNotice from 'wcstripe/components/inline-notice';

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
	const oauthUrl = testMode // eslint-disable-next-line camelcase
		? wc_stripe_settings_params.stripe_test_oauth_url // eslint-disable-next-line camelcase
		: wc_stripe_settings_params.stripe_oauth_url;

	return oauthUrl ? (
		<div className="woocommerce-stripe-auth__actions">
			<Button
				variant="primary"
				href={ oauthUrl }
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
	) : (
		<InlineNotice isDismissible={ false } status="error">
			{ interpolateComponents( {
				mixedString: __(
					'An issue occurred generating a connection to Stripe, please ensure your server has a valid SSL certificate and try again.{{br /}}For assistance, refer to our {{Link}}documentation{{/Link}}.',
					'woocommerce-gateway-stripe'
				),
				components: {
					br: <br />,
					Link: (
						<ExternalLink href="https://woocommerce.com/document/stripe/setup-and-configuration/connecting-to-stripe/" />
					),
				},
			} ) }
		</InlineNotice>
	);
};

export default StripeAuthActions;
