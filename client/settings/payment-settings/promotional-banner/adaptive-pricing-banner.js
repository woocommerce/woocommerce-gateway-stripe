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
							'A new (included) feature to sell more globally',
							'woocommerce-gateway-stripe'
						) }
					</TitleAP>
					<IntroAP>
						{ __(
							'Did you know that around 90% of buyers prefer to pay in their local currency? By using local currency presentment, you can grow your global revenue by an average of 17.8%.',
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
							'Try Adaptive Pricing',
							'woocommerce-gateway-stripe'
						) }
					/>
				</CenteredColumnIllustration>
			</CardInner>
			<ButtonsRowWithMargin>
				<ExternalLink href="https://docs.stripe.com/payments/currencies/localize-prices/adaptive-pricing">
					{ __(
						'Learn more about Adaptive Pricing',
						'woocommerce-gateway-stripe'
					) }
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
