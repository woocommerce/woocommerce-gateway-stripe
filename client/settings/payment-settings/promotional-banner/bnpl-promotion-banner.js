import { React } from 'react';
import styled from '@emotion/styled';
import { __ } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import CardBody from 'wcstripe/settings/card-body';
import illustration from 'wcstripe/settings/payment-settings/promotional-banner/illustrations/bnpl.svg';
import {
	BannerIllustration,
	ButtonsRow,
	CardColumn,
	CardInner,
	DismissButton,
} from 'wcstripe/settings/payment-settings/promotional-banner/banner-layout';

const BannerIllustrationBNPL = styled( BannerIllustration )`
	@media ( min-width: 600px ) {
		margin: 0 0 -40px 24px;
	}
`;

const ButtonsRowBNPL = styled( ButtonsRow )`
	@media ( min-width: 600px ) {
		margin-bottom: 0.7em;
	}
`;

const ColumnIllustration = styled( CardColumn )`
	@media ( max-width: 599px ) {
		text-align: center;
	}
`;

const IntroBNPL = styled.p`
	line-height: 20px;
`;

const TitleBNPL = styled.h4`
	margin-top: 0.6em !important;
`;

export const BNPLPromotionBanner = ( { setShowPromotionalBanner } ) => {
	const handleBannerDismiss = () => {
		apiFetch( {
			path: '/wc/v3/wc_stripe/settings/notice',
			method: 'POST',
			data: { wc_stripe_show_bnpl_promotion_banner: 'no' },
		} ).finally( () => {
			setShowPromotionalBanner( false );
		} );
		window.location.reload();
	};

	return (
		<CardBody>
			<CardInner>
				<CardColumn>
					<TitleBNPL>
						{ __(
							'Offer more ways to pay with Buy Now, Pay Later',
							'woocommerce-gateway-stripe'
						) }
					</TitleBNPL>
					<IntroBNPL>
						{ __(
							'Flexible pay-over-time options can boost revenue by up to 14%*.',
							'woocommerce-gateway-stripe'
						) }
						<br />
						{ __(
							'Affirm and Klarna payments are auto-enabled with Stripe for eligible merchants.',
							'woocommerce-gateway-stripe'
						) }
					</IntroBNPL>
					<p>
						{ __(
							'*Source: Stripe 2024',
							'woocommerce-gateway-stripe'
						) }
					</p>
				</CardColumn>
				<ColumnIllustration>
					<BannerIllustrationBNPL
						src={ illustration }
						alt={ __(
							'Try Buy Now, Pay Later',
							'woocommerce-gateway-stripe'
						) }
					/>
				</ColumnIllustration>
			</CardInner>
			<ButtonsRowBNPL>
				<ExternalLink href="https://woocommerce.com/document/stripe/setup-and-configuration/additional-payment-methods/">
					{ __( 'Learn more', 'woocommerce-gateway-stripe' ) }
				</ExternalLink>
				<DismissButton
					variant="secondary"
					onClick={ handleBannerDismiss }
					data-testid="dismiss"
				>
					{ __( 'Dismiss', 'woocommerce-gateway-stripe' ) }
				</DismissButton>
			</ButtonsRowBNPL>
		</CardBody>
	);
};
