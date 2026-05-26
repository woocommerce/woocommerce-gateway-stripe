import { React } from 'react';
import styled from '@emotion/styled';
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
} from 'wcstripe/settings/payment-settings/promotional-banner/banner-layout';
import { dismissNotice } from 'wcstripe/utils';

const IntroAP = styled.p`
	line-height: 20px;
`;

const TitleAP = styled.h4`
	margin-top: 0.6em !important;
	font-weight: 500;
`;

const FootnoteAP = styled.p`
	font-size: 12px;
	color: #757575;
`;

export const AdaptivePricingBanner = ( { setShowPromotionalBanner } ) => {
	const handleBannerDismiss = () => {
		dismissNotice( 'wc_stripe_show_adaptive_pricing_banner', () => {
			setShowPromotionalBanner( false );
		} );
	};

	return (
		<CardBody>
			<CardInner>
				<CardColumn>
					<TitleAP>
						{ __(
							'Stripe Optimized Checkout Suite is now active',
							'woocommerce-gateway-stripe'
						) }
					</TitleAP>
					<IntroAP>
						{ __(
							"Your checkout dynamically displays the most relevant payment methods you've enabled for each customer. International shoppers also see prices in their local currency, growing cross-border revenue by an average of 17.8%.",
							'woocommerce-gateway-stripe'
						) }
					</IntroAP>
					<FootnoteAP>
						{ __(
							'*Data is from a Stripe global holdback study conducted in 2024.',
							'woocommerce-gateway-stripe'
						) }
					</FootnoteAP>
				</CardColumn>
				<CenteredColumnIllustration>
					<BannerIllustrationWithOffset
						src={ illustration }
						alt={ __(
							'Optimized Checkout Suite is active',
							'woocommerce-gateway-stripe'
						) }
					/>
				</CenteredColumnIllustration>
			</CardInner>
			<ButtonsRowWithMargin>
				<ExternalLink href="https://woocommerce.com/product-update/stripe-for-woocommerce-10-8-0">
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
