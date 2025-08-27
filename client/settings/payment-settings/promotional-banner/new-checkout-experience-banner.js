import { React } from 'react';
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import CardBody from 'wcstripe/settings/card-body';
import illustration from 'wcstripe/settings/payment-settings/promotional-banner/illustrations/default.svg';
import { recordEvent } from 'wcstripe/tracking';
import {
	BannerIllustration,
	ButtonsRow,
	CardColumn,
	CardInner,
	DismissButton,
	MainCTALink,
	NewPill,
} from 'wcstripe/settings/payment-settings/promotional-banner/banner-layout';

export const NewCheckoutExperienceBanner = ( {
	setShowPromotionalBanner,
	setIsUpeEnabled,
} ) => {
	const { createErrorNotice, createSuccessNotice } =
		useDispatch( 'core/notices' );

	const handleBannerDismiss = () => {
		setShowPromotionalBanner( false );
	};

	const handleButtonClick = () => {
		const callback = async () => {
			try {
				await setIsUpeEnabled( true );

				recordEvent( 'wcstripe_legacy_experience_disabled', {
					source: 'payment-methods-tab-notice',
				} );

				createSuccessNotice(
					__(
						'New checkout experience enabled',
						'woocommerce-gateway-stripe'
					)
				);

				window.location.reload();
			} catch ( err ) {
				createErrorNotice(
					__(
						'There was an error. Please reload the page and try again.',
						'woocommerce-gateway-stripe'
					)
				);
			}
		};

		// creating a separate callback so that the UI isn't blocked by the async call.
		callback();
	};

	return (
		<CardBody>
			<CardInner>
				<CardColumn>
					<NewPill>
						{ __( 'New', 'woocommerce-gateway-stripe' ) }
					</NewPill>
					<h4>
						{ __(
							'Boost sales and checkout conversion',
							'woocommerce-gateway-stripe'
						) }
					</h4>
					<p>
						{ __(
							'Enable the new checkout to boost sales, increase order value, and reach new customers with Klarna, Afterpay, Affirm and Link, a one-click checkout.',
							'woocommerce-gateway-stripe'
						) }
					</p>
				</CardColumn>
				<CardColumn>
					<BannerIllustration
						src={ illustration }
						alt={ __(
							'New Checkout',
							'woocommerce-gateway-stripe'
						) }
					/>
				</CardColumn>
			</CardInner>
			<ButtonsRow>
				<MainCTALink
					variant="secondary"
					data-testid="enable-the-new-checkout"
					onClick={ handleButtonClick }
				>
					{ __(
						'Enable the new checkout',
						'woocommerce-gateway-stripe'
					) }
				</MainCTALink>
				<DismissButton
					variant="secondary"
					onClick={ handleBannerDismiss }
					data-testid="dismiss"
				>
					{ __( 'Dismiss', 'woocommerce-gateway-stripe' ) }
				</DismissButton>
			</ButtonsRow>
		</CardBody>
	);
};
