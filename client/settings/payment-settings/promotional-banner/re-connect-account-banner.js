import { React } from 'react';
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

export const ReConnectAccountBanner = ( { testOauthUrl, oauthUrl } ) => {
	const [ isTestModeEnabled ] = useTestMode();
	const { createErrorNotice } = useDispatch( 'core/notices' );

	const handleButtonClick = () => {
		if ( isTestModeEnabled && testOauthUrl ) {
			recordEvent( 'wcstripe_create_or_connect_test_account_click', {} );
			recordEvent( 'wcstripe_reconnect_button_click', {
				source: 're-connect-account-banner',
				mode: 'test',
			} );
			window.location.assign( testOauthUrl );
		} else if ( ! isTestModeEnabled && oauthUrl ) {
			recordEvent( 'wcstripe_create_or_connect_account_click', {} );
			recordEvent( 'wcstripe_reconnect_button_click', {
				source: 're-connect-account-banner',
				mode: 'live',
			} );
			window.location.assign( oauthUrl );
		} else {
			createErrorNotice(
				__(
					'There was an error. Please reload the page and try again.',
					'woocommerce-gateway-stripe'
				)
			);
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
				>
					{ __( 'Re-authenticate', 'woocommerce-gateway-stripe' ) }
				</MainCTALink>
			</ButtonsRow>
		</CardBody>
	);
};
