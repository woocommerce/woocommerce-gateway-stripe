import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { React } from 'react';
import { Card, Button } from '@wordpress/components';
import styled from '@emotion/styled';
import CardBody from '../card-body';
import bannerIllustration from './banner-illustration.svg';
import bannerIllustrationReConnect from './banner-illustration-re-connect.svg';
import Pill from 'wcstripe/components/pill';
import { recordEvent } from 'wcstripe/tracking';
import { useTestMode } from 'wcstripe/data';

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

const MainCTALink = styled( Button )`
	margin-right: 8px;
`;

const DismissButton = styled( Button )`
	box-shadow: none !important;
	color: #757575 !important;
`;

const PromotionalBannerSection = ( {
	setShowPromotionalBanner,
	isUpeEnabled,
	setIsUpeEnabled,
	isConnectedViaOAuth,
	setModalType,
} ) => {
	const { createErrorNotice, createSuccessNotice } = useDispatch(
		'core/notices'
	);
	const [ isTestModeEnabled ] = useTestMode();

	const handleButtonClick = () => {
		const callback = async () => {
			try {
				await setIsUpeEnabled( true );

				recordEvent( 'wcstripe_legacy_experience_disabled', {
					source: 'payment-methods-tab-notice',
				} );

				createSuccessNotice(
					__(
						'New checkout experience enabled',
						'woocommerce-gateway-stripe'
					)
				);
			} catch ( err ) {
				createErrorNotice(
					__(
						'There was an error. Please reload the page and try again.',
						'woocommerce-gateway-stripe'
					)
				);
			}
		};

		// creating a separate callback so that the UI isn't blocked by the async call.
		callback();
	};

	const handleBannerDismiss = () => {
		setShowPromotionalBanner( false );
	};

	const handleReConnectButtonClick = () =>
		setModalType( isTestModeEnabled ? 'test' : 'live' );

	const ReConnectAccountBanner = () => (
		<CardBody data-testid="re-connect-account-banner">
			<CardInner>
				<CardColumn>
					<NewPill>
						{ __( 'New', 'woocommerce-gateway-stripe' ) }
					</NewPill>
					<h4>
						{ __(
							'Make your store more secure',
							'woocommerce-gateway-stripe'
						) }
					</h4>
					<p>
						{ __(
							'Re-connect your Stripe account using the new authentication flow by clicking the "Configure connection" button and make your store safer.',
							'woocommerce-gateway-stripe'
						) }
					</p>
				</CardColumn>
				<CardColumn>
					<BannerIllustration
						src={ bannerIllustrationReConnect }
						alt={ __(
							'Configure connection',
							'woocommerce-gateway-stripe'
						) }
					/>
				</CardColumn>
			</CardInner>
			<ButtonsRow>
				<MainCTALink
					variant="secondary"
					data-testid="re-connect-checkout"
					onClick={ handleReConnectButtonClick }
				>
					{ __(
						'Configure connection',
						'woocommerce-gateway-stripe'
					) }
				</MainCTALink>
			</ButtonsRow>
		</CardBody>
	);

	const NewCheckoutExperienceBanner = () => (
		<CardBody>
			<CardInner>
				<CardColumn>
					<NewPill>
						{ __( 'New', 'woocommerce-gateway-stripe' ) }
					</NewPill>
					<h4>
						{ __(
							'Boost sales and checkout conversion',
							'woocommerce-gateway-stripe'
						) }
					</h4>
					<p>
						{ __(
							'Enable the new checkout to boost sales, increase order value, and reach new customers with Klarna, Afterpay, Affirm and Link, a one-click checkout.',
							'woocommerce-gateway-stripe'
						) }
					</p>
				</CardColumn>
				<CardColumn>
					<BannerIllustration
						src={ bannerIllustration }
						alt={ __(
							'New Checkout',
							'woocommerce-gateway-stripe'
						) }
					/>
				</CardColumn>
			</CardInner>
			<ButtonsRow>
				<MainCTALink
					variant="secondary"
					data-testid="enable-the-new-checkout"
					onClick={ handleButtonClick }
				>
					{ __(
						'Enable the new checkout',
						'woocommerce-gateway-stripe'
					) }
				</MainCTALink>
				<DismissButton
					variant="secondary"
					onClick={ handleBannerDismiss }
					data-testid="dismiss"
				>
					{ __( 'Dismiss', 'woocommerce-gateway-stripe' ) }
				</DismissButton>
			</ButtonsRow>
		</CardBody>
	);

	let BannerContent = null;
	if ( ! isConnectedViaOAuth ) {
		BannerContent = <ReConnectAccountBanner />;
	} else if ( ! isUpeEnabled ) {
		BannerContent = <NewCheckoutExperienceBanner />;
	}

	return (
		<BannerCard data-testid="promotional-banner-card">
			{ BannerContent }
		</BannerCard>
	);
};

export default PromotionalBannerSection;
