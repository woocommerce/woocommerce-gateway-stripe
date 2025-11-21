/* global wc_stripe_settings_params, ajaxurl */

import { React, useState } from 'react';
import interpolateComponents from '@automattic/interpolate-components';
import { __ } from '@wordpress/i18n';
import InlineNotice from 'wcstripe/components/inline-notice';
import { Button, ExternalLink } from '@wordpress/components';
import { recordEvent } from 'wcstripe/tracking';

/**
 * ConnectButton component.
 *
 * @param {Object}  props               The component props.
 * @param {boolean} props.testMode      Indicates whether this is for test mode.
 * @param {string} props.buttonVariant  Indicates the variant of the button.
 *
 * @return {JSX.Element} The rendered ConnectButton component.
 */
const ConnectButton = ( { testMode, buttonVariant } ) => {
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	const buttonText = testMode
		? __( 'Create or connect a test account', 'woocommerce-gateway-stripe' )
		: __( 'Create or connect an account', 'woocommerce-gateway-stripe' );

	const handleClick = async () => {
		setIsLoading( true );
		setError( null );

		if ( testMode ) {
			recordEvent( 'wcstripe_create_or_connect_test_account_click', {} );
		} else {
			recordEvent( 'wcstripe_create_or_connect_account_click', {} );
		}

		try {
			const response = await jQuery.ajax( {
				url: ajaxurl,
				method: 'POST',
				data: {
					action: 'wc_stripe_get_oauth_url',
					mode: testMode ? 'test' : 'live',
					nonce: wc_stripe_settings_params.oauth_nonce, // eslint-disable-line camelcase
				},
			} );

			if ( response.success && response.data.oauth_url ) {
				window.location.assign( response.data.oauth_url );
			} else {
				setError( true );
				setIsLoading( false );
			}
		} catch ( err ) {
			setError( true );
			setIsLoading( false );
		}
	};

	return error ? (
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
			variant={ buttonVariant }
			onClick={ handleClick }
			text={ buttonText }
			disabled={ isLoading }
			isBusy={ isLoading }
		/>
	);
};

export default ConnectButton;
