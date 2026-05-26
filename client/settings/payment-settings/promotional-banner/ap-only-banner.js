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

const Title = styled.h4`
	margin-top: 0.6em !important;
	font-weight: 500;
`;

const Intro = styled.p`
	line-height: 20px;
`;

const Footnote = styled.p`
	font-size: 12px;
	color: #757575;
`;

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
					<Title>
						{ __(
							'Stripe Adaptive Pricing is now active',
							'woocommerce-gateway-stripe'
						) }
					</Title>
					<Intro>
						{ __(
							"Your checkout now shows prices in shoppers' local currency across 150+ countries, growing cross-border revenue by an average of 17.8%. Stripe handles real-time exchange rates with no currency conversion fees.",
							'woocommerce-gateway-stripe'
						) }
					</Intro>
					<Footnote>
						{ __(
							'*Data is from Stripe global holdback study conducted in 2024.',
							'woocommerce-gateway-stripe'
						) }
					</Footnote>
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
