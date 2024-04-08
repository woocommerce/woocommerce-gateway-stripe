import { __ } from '@wordpress/i18n';
import { React, useState } from 'react';
import { Card, ExternalLink, Button } from '@wordpress/components';
import styled from '@emotion/styled';
import CardBody from '../card-body';
import { AccountKeysModal } from './account-keys-modal';
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
`;

const CardColunm = styled.div`
	flex: 1 auto;
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

const PromotionalBannerSection = ( { setKeepModalContent } ) => {
	const [ modalType, setModalType ] = useState( '' );

	const handleModalDismiss = () => {
		setModalType( '' );
	};

	return (
		<>
			{ modalType && (
				<AccountKeysModal
					type={ modalType }
					onClose={ handleModalDismiss }
					setKeepModalContent={ setKeepModalContent }
				/>
			) }
			<BannerCard>
				<CardBody>
					<CardInner>
						<CardColunm>
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
							<ButtonsRow>
								<LearnMoreLink href="https://stripe.com/en-br/capital" onClick={ () => alert( 'test' ) }>
									{ __(
										'Learn more',
										'woocommerce-gateway-stripe'
									) }
								</LearnMoreLink>
								<DismissButton
									variant="secondary"
									onClick={ () => alert( 'test' ) }
								>
									{ __(
										'Dismiss',
										'woocommerce-gateway-stripe'
									) }
								</DismissButton>
							</ButtonsRow>
						</CardColunm>
						<CardColunm>
							<img src={ bannerIllustration } alt="" />
						</CardColunm>
					</CardInner>
				</CardBody>
			</BannerCard>
		</>
	);
};

export default PromotionalBannerSection;
