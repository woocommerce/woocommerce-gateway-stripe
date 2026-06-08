import { React } from 'react';
import { __ } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';
import CardBody from 'wcstripe/settings/card-body';
import illustration from 'wcstripe/settings/payment-settings/promotional-banner/illustrations/default.svg';
import {
	CardColumn,
	CardInner,
	DismissButton,
	BannerIllustrationWithOffset,
	ButtonsRowWithMargin,
	CenteredColumnIllustration,
	BannerTitle,
	BannerIntro,
	BannerFootnote,
} from 'wcstripe/settings/payment-settings/promotional-banner/banner-layout';
import { OCS_AP_PRODUCT_UPDATE_URL } from 'wcstripe/settings/payment-settings/constants';
import { dismissNotice } from 'wcstripe/utils';

export const APOnlyBanner = ( { setShowPromotionalBanner } ) => {
	const handleBannerDismiss = () => {
		dismissNotice( 'wc_stripe_show_ap_only_banner', () => {
			setShowPromotionalBanner( false );
		} );
	};

	return (
		<CardBody>
			<CardInner>
				<CardColumn>
					<BannerTitle>
						{ __(
							'Stripe Adaptive Pricing is now active',
							'woocommerce-gateway-stripe'
						) }
					</BannerTitle>
					<BannerIntro>
						{ __(
							"Your checkout now shows prices in shoppers' local currency across 150+ countries, growing cross-border revenue by an average of 17.8%. Stripe handles real-time exchange rates with no currency conversion fees.",
							'woocommerce-gateway-stripe'
						) }
					</BannerIntro>
					<BannerFootnote>
						{ __(
							'*Data is from Stripe global holdback study conducted in 2024.',
							'woocommerce-gateway-stripe'
						) }
					</BannerFootnote>
				</CardColumn>
				<CenteredColumnIllustration>
					<BannerIllustrationWithOffset
						src={ illustration }
						alt={ __(
							'Adaptive Pricing illustration',
							'woocommerce-gateway-stripe'
						) }
					/>
				</CenteredColumnIllustration>
			</CardInner>
			<ButtonsRowWithMargin>
				<ExternalLink href={ OCS_AP_PRODUCT_UPDATE_URL }>
					{ __( 'Learn more', 'woocommerce-gateway-stripe' ) }
				</ExternalLink>
				<DismissButton
					variant="secondary"
					onClick={ handleBannerDismiss }
					data-testid="dismiss"
				>
					{ __( 'Dismiss', 'woocommerce-gateway-stripe' ) }
				</DismissButton>
			</ButtonsRowWithMargin>
		</CardBody>
	);
};
