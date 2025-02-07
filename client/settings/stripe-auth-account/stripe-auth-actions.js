/* global wc_stripe_settings_params, ajaxurl */
import { __ } from '@wordpress/i18n';
import { React, useState, useEffect } from 'react';
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
	const [ oauthUrls, setOauthUrls ] = useState( {
		oauth_url: '',
		test_oauth_url: '',
	} );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		const fetchOAuthUrls = async () => {
			try {
				const response = await jQuery.ajax( {
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'wc_stripe_get_oauth_urls',
						nonce: wc_stripe_settings_params.oauth_nonce, // eslint-disable-line camelcase
					},
				} );

				if ( response.success ) {
					setOauthUrls( response.data );
				} else {
					setError(
						response.data?.message ||
							__(
								'Failed to fetch OAuth URLs',
								'woocommerce-gateway-stripe'
							)
					);
				}
			} catch ( err ) {
				setError(
					__(
						'Failed to fetch OAuth URLs',
						'woocommerce-gateway-stripe'
					)
				);
			} finally {
				setIsLoading( false );
			}
		};

		fetchOAuthUrls();
	}, [] );

	const oauthUrl = testMode ? oauthUrls.test_oauth_url : oauthUrls.oauth_url;
	const buttonText = testMode
		? __( 'Create or connect a test account', 'woocommerce-gateway-stripe' )
		: __( 'Create or connect an account', 'woocommerce-gateway-stripe' );

	return (
		<div className="woocommerce-stripe-auth__actions">
			{ error ? (
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
			) : (
				<Button
					variant="primary"
					href={ oauthUrl }
					text={ buttonText }
					disabled={ isLoading }
					isBusy={ isLoading }
				/>
			) }
			{ displayWebhookConfigure && (
				<ConfigureWebhookButton testMode={ testMode } />
			) }
		</div>
	);
};

export default StripeAuthActions;
