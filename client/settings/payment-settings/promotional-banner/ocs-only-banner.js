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
} from 'wcstripe/settings/payment-settings/promotional-banner/banner-layout';
import { OCS_AP_PRODUCT_UPDATE_URL } from 'wcstripe/settings/payment-settings/constants';
import { dismissNotice } from 'wcstripe/utils';

export const OCSOnlyBanner = ( { setShowPromotionalBanner } ) => {
	const handleBannerDismiss = () => {
		dismissNotice( 'wc_stripe_show_ocs_only_banner', () => {
			setShowPromotionalBanner( false );
		} );
	};

	return (
		<CardBody>
			<CardInner>
				<CardColumn>
					<BannerTitle>
						{ __(
							'Stripe Optimized Checkout is now active',
							'woocommerce-gateway-stripe'
						) }
					</BannerTitle>
					<BannerIntro>
						{ __(
							"Your checkout is optimized for sales by dynamically displaying the most relevant payment methods you've enabled for each customer.",
							'woocommerce-gateway-stripe'
						) }
					</BannerIntro>
				</CardColumn>
				<CenteredColumnIllustration>
					<BannerIllustrationWithOffset
						src={ illustration }
						alt={ __(
							'Optimized Checkout illustration',
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
