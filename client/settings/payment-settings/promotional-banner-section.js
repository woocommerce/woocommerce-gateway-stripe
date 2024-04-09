import { __ } from '@wordpress/i18n';
import { React } from 'react';
import { Card, ExternalLink, Button } from '@wordpress/components';
import styled from '@emotion/styled';
import CardBody from '../card-body';
import bannerIllustration from './banner-illustration.svg';
import Pill from 'wcstripe/components/pill';

const NewPill = styled( Pill )`
	border-color: #674399;
	color: #674399;
	margin-bottom: 13px;
`;

const BannerCard = styled( Card )`
	margin-bottom: 12px;
`;

const CardInner = styled.div`
	display: flex;
	align-items: center;
	padding-bottom: 0;
	margin-bottom: 0;
	p {
		color: #757575;
	}
	@media ( max-width: 599px ) {
		display: block;
	}
`;

const CardColumn = styled.div`
	flex: 1 auto;
`;

const BannerIllustration = styled.img`
	margin: 24px 0 0 24px;
`;

const ButtonsRow = styled.p`
	margin: 0;
`;

const LearnMoreLink = styled( ExternalLink )`
	margin-right: 8px;
`;

const DismissButton = styled( Button )`
	box-shadow: none !important;
	color: #757575 !important;
`;

const PromotionalBannerSection = ( { setShowPromotionalBanner } ) => {
	const handleBannerDismiss = () => {
		setShowPromotionalBanner( false );
	};

	return (
		<BannerCard>
			<CardBody>
				<CardInner>
					<CardColumn>
						<NewPill>
							{ __( 'New', 'woocommerce-gateway-stripe' ) }
						</NewPill>
						<h4>
							{ __(
								'You’re eligible: Fast financing with Stripe',
								'woocommerce-gateway-stripe'
							) }
						</h4>
						<p>
							{ __(
								'Based on your business’ strong performance, you’re pre-qualified for a loan offer through our partnership with Stripe Capital. You can use the financing for whatever your business needs.',
								'woocommerce-gateway-stripe'
							) }
						</p>
					</CardColumn>
					<CardColumn>
						<BannerIllustration
							src={ bannerIllustration }
							alt={ __(
								'Stripe Capital',
								'woocommerce-gateway-stripe'
							) }
						/>
					</CardColumn>
				</CardInner>
				<ButtonsRow>
					<LearnMoreLink href="https://stripe.com/en-br/capital">
						{ __( 'Learn more', 'woocommerce-gateway-stripe' ) }
					</LearnMoreLink>
					<DismissButton
						variant="secondary"
						onClick={ handleBannerDismiss }
					>
						{ __( 'Dismiss', 'woocommerce-gateway-stripe' ) }
					</DismissButton>
				</ButtonsRow>
			</CardBody>
		</BannerCard>
	);
};

export default PromotionalBannerSection;
