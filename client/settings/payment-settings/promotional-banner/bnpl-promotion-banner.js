import { __ } from '@wordpress/i18n';
import { React } from 'react';
import styled from '@emotion/styled';
import CardBody from 'wcstripe/settings/card-body';
import bannerIllustrationBNPL from 'wcstripe/settings/payment-settings/promotional-banner/banner-illustration-bnpl.svg';
import {
	BannerIllustration,
	ButtonsRow,
	CardColumn,
	CardInner,
	DismissButton,
	MainCTALink,
} from 'wcstripe/settings/payment-settings/promotional-banner/banner-layout';

const BannerIllustrationBNPL = styled( BannerIllustration )`
	margin: 0 0 -40px 24px;
`;

const ButtonsRowBNPL = styled( ButtonsRow )`
	margin-bottom: 0.7em;
`;

const IntroBNPL = styled.p`
	line-height: 20px;
`;

const TitleBNPL = styled.h4`
	margin-top: 0.6em !important;
`;

export const BNPLPromotionBanner = ( { setShowPromotionalBanner } ) => {
	const handleBannerDismiss = () => {
		setShowPromotionalBanner( false );
	};

	const handleButtonClick = () => {};

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
				<CardColumn>
					<BannerIllustrationBNPL
						src={ bannerIllustrationBNPL }
						alt={ __(
							'Try Buy Now, Pay Later',
							'woocommerce-gateway-stripe'
						) }
					/>
				</CardColumn>
			</CardInner>
			<ButtonsRowBNPL>
				<MainCTALink
					variant="secondary"
					data-testid="learn-more-bnpl"
					onClick={ handleButtonClick }
				>
					{ __( 'Learn more', 'woocommerce-gateway-stripe' ) }
				</MainCTALink>
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
