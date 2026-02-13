/* global wc_stripe_settings_params, ajaxurl */
import { React, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import CardBody from 'wcstripe/settings/card-body';
import illustration from 'wcstripe/settings/payment-settings/promotional-banner/illustrations/reconnect.svg';
import { recordEvent } from 'wcstripe/tracking';
import { useTestMode } from 'wcstripe/data';
import {
	BannerIllustration,
	ButtonsRow,
	CardColumn,
	CardInner,
	MainCTALink,
	NewPill,
} from 'wcstripe/settings/payment-settings/promotional-banner/banner-layout';

export const ReConnectAccountBanner = () => {
	const [ isTestModeEnabled ] = useTestMode();
	const { createErrorNotice } = useDispatch( 'core/notices' );
	const [ isLoading, setIsLoading ] = useState( false );

	const handleButtonClick = async () => {
		const mode = isTestModeEnabled ? 'test' : 'live';

		recordEvent(
			isTestModeEnabled
				? 'wcstripe_create_or_connect_test_account_click'
				: 'wcstripe_create_or_connect_account_click',
			{}
		);
		recordEvent( 'wcstripe_reconnect_button_click', {
			source: 're-connect-account-banner',
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
		<CardBody data-testid="re-connect-account-banner">
			<CardInner>
				<CardColumn>
					<NewPill>
						{ __( 'New', 'woocommerce-gateway-stripe' ) }
					</NewPill>
					<h4>
						{ __(
							'Make your store more secure',
							'woocommerce-gateway-stripe'
						) }
					</h4>
					<p>
						{ __(
							'Re-connect your Stripe account using the new authentication flow by clicking the "Re-authenticate" button and make your store safer.',
							'woocommerce-gateway-stripe'
						) }
					</p>
				</CardColumn>
				<CardColumn>
					<BannerIllustration
						src={ illustration }
						alt={ __(
							'Re-authenticate',
							'woocommerce-gateway-stripe'
						) }
					/>
				</CardColumn>
			</CardInner>
			<ButtonsRow>
				<MainCTALink
					variant="secondary"
					data-testid="re-connect-checkout"
					onClick={ handleButtonClick }
					disabled={ isLoading }
					isBusy={ isLoading }
				>
					{ __( 'Re-authenticate', 'woocommerce-gateway-stripe' ) }
				</MainCTALink>
			</ButtonsRow>
		</CardBody>
	);
};
