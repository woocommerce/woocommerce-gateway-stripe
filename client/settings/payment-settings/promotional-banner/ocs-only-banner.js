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
					<Title>
						{ __(
							'Stripe Optimized Checkout is now active',
							'woocommerce-gateway-stripe'
						) }
					</Title>
					<Intro>
						{ __(
							'Your checkout dynamically displays the payment methods most likely to drive conversions. [Placeholder copy — pending Stripe approval.]',
							'woocommerce-gateway-stripe'
						) }
					</Intro>
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
