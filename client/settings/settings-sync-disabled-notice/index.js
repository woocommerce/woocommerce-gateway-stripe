/* global wc_stripe_settings_params, ajaxurl */
import React, { useState } from 'react';
import styled from '@emotion/styled';
import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { recordEvent } from 'wcstripe/tracking';
import { useTestMode } from 'wcstripe/data';

const NoticeWrapper = styled( Notice )`
	margin: 0 0 24px 0;
`;

/**
 * Warning shown when the account is connected but payment method settings can no longer
 * be synced with Stripe (e.g. legacy connections without a usable Payment Method
 * Configuration). Re-authenticating through the OAuth flow restores syncing.
 */
const SettingsSyncDisabledNotice = () => {
	const [ isTestModeEnabled ] = useTestMode();
	const { createErrorNotice } = useDispatch( 'core/notices' );
	const [ isLoading, setIsLoading ] = useState( false );

	// eslint-disable-next-line camelcase
	if ( ! wc_stripe_settings_params?.is_pmc_sync_disabled ) {
		return null;
	}

	const handleReauthenticate = async () => {
		if ( isLoading ) {
			return;
		}

		const mode = isTestModeEnabled ? 'test' : 'live';

		recordEvent( 'wcstripe_reconnect_button_click', {
			source: 'settings-sync-disabled-notice',
			mode,
		} );

		setIsLoading( true );

		try {
			const response = await jQuery.ajax( {
				url: ajaxurl,
				method: 'POST',
				data: {
					action: 'wc_stripe_get_oauth_url',
					mode,
					nonce: wc_stripe_settings_params.oauth_nonce, // eslint-disable-line camelcase
				},
			} );

			if ( response.success && response.data.oauth_url ) {
				window.location.assign( response.data.oauth_url );
			} else {
				createErrorNotice(
					__(
						'There was an error. Please reload the page and try again.',
						'woocommerce-gateway-stripe'
					)
				);
				setIsLoading( false );
			}
		} catch ( err ) {
			createErrorNotice(
				__(
					'There was an error. Please reload the page and try again.',
					'woocommerce-gateway-stripe'
				)
			);
			setIsLoading( false );
		}
	};

	return (
		<NoticeWrapper
			status="warning"
			isDismissible={ false }
			actions={ [
				{
					label: __(
						'Re-authenticate',
						'woocommerce-gateway-stripe'
					),
					onClick: handleReauthenticate,
					variant: 'secondary',
				},
			] }
		>
			{ __(
				'Your payment method settings are no longer synced with your Stripe account. To restore syncing, please re-authenticate your Stripe account connection.',
				'woocommerce-gateway-stripe'
			) }
		</NoticeWrapper>
	);
};

export default SettingsSyncDisabledNotice;
