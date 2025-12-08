/* global wc_stripe_settings_params, ajaxurl */

import { React, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { recordEvent } from 'wcstripe/tracking';
import ConnectionErrorNotice from 'wcstripe/settings/stripe-auth-account/connection-error-notice';
import Tooltip from 'wcstripe/components/tooltip';

/**
 * ConnectButton component.
 *
 * @param {Object}   props               The component props.
 * @param {boolean}  props.testMode      Indicates whether this is for test mode.
 * @param {string}   props.buttonVariant Indicates the variant of the button.
 * @param {Function} props.onErrorChange Callback when error state changes.
 * @param {boolean}  props.disabled      Whether the button should be disabled.
 *
 * @return {JSX.Element} The rendered ConnectButton component.
 */
const ConnectButton = ( {
	testMode,
	buttonVariant,
	onErrorChange,
	disabled = false,
} ) => {
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const isSSL = window.location.protocol === 'https:';
	const isLiveModeWithoutSSL = ! testMode && ! isSSL;

	const buttonText = testMode
		? __( 'Create or connect a test account', 'woocommerce-gateway-stripe' )
		: __( 'Create or connect an account', 'woocommerce-gateway-stripe' );

	const handleClick = async () => {
		setIsLoading( true );
		setError( null );
		// Clear parent error when any button is clicked
		if ( onErrorChange ) {
			onErrorChange( null );
		}

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
				if ( onErrorChange ) {
					onErrorChange( true );
				}
			}
		} catch ( err ) {
			setError( true );
			setIsLoading( false );
			if ( onErrorChange ) {
				onErrorChange( true );
			}
		}
	};

	// If onErrorChange is provided, parent handles error display
	// Otherwise, show error inline for backward compatibility
	if ( ! onErrorChange && error ) {
		return <ConnectionErrorNotice />;
	}

	const button = (
		<Button
			variant={ buttonVariant }
			onClick={ handleClick }
			text={ buttonText }
			disabled={ isLoading || disabled || isLiveModeWithoutSSL }
			isBusy={ isLoading }
		/>
	);

	// Wrap in tooltip if live mode without SSL
	if ( isLiveModeWithoutSSL ) {
		return (
			<Tooltip
				content={ __(
					'Live mode requires a valid SSL certificate. Please enable SSL on your site to connect a live Stripe account.',
					'woocommerce-gateway-stripe'
				) }
			>
				<span style={ { display: 'inline-block' } }>{ button }</span>
			</Tooltip>
		);
	}

	return button;
};

export default ConnectButton;
