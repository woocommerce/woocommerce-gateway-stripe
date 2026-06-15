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

export const OCSAndAPBanner = ( { setShowPromotionalBanner } ) => {
	const handleBannerDismiss = () => {
		dismissNotice( 'wc_stripe_show_ocs_ap_banner', () => {
			setShowPromotionalBanner( false );
		} );
	};

	return (
		<CardBody>
			<CardInner>
				<CardColumn>
					<BannerTitle>
						{ __(
							'Stripe Optimized Checkout Suite and Adaptive Pricing are now active',
							'woocommerce-gateway-stripe'
						) }
					</BannerTitle>
					<BannerIntro>
						{ __(
							'Your checkout dynamically displays available payment methods most likely to drive conversions. International shoppers also see prices in their local currency, growing cross-border revenue by an average of 17.8%.',
							'woocommerce-gateway-stripe'
						) }
					</BannerIntro>
					<BannerFootnote>
						{ __(
							'*Data is from Stripe global holdback study conducted in 2024',
							'woocommerce-gateway-stripe'
						) }
					</BannerFootnote>
				</CardColumn>
				<CenteredColumnIllustration>
					<BannerIllustrationWithOffset
						src={ illustration }
						alt={ __(
							'Optimized Checkout Suite and Adaptive Pricing illustration',
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
