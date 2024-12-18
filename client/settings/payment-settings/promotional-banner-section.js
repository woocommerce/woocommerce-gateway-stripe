/* global wc_stripe_settings_params */
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { React, useEffect } from 'react';
import { Card, Button, ExternalLink } from '@wordpress/components';
import styled from '@emotion/styled';
import interpolateComponents from 'interpolate-components';
import CardBody from '../card-body';
import bannerIllustration from './banner-illustration.svg';
import bannerIllustrationReConnect from './banner-illustration-re-connect.svg';
import { RECONNECT_BANNER, NEW_CHECKOUT_EXPERIENCE_BANNER } from './constants';
import Pill from 'wcstripe/components/pill';
import { recordEvent } from 'wcstripe/tracking';
import { useEnabledPaymentMethodIds, useTestMode } from 'wcstripe/data';
import { PAYMENT_METHOD_CARD } from 'wcstripe/stripe-utils/constants';

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
	setPromotionalBannerType,
	isUpeEnabled,
	setIsUpeEnabled,
	isConnectedViaOAuth,
	oauthUrl,
	testOauthUrl,
} ) => {
	const { createErrorNotice, createSuccessNotice } = useDispatch(
		'core/notices'
	);
	const [ isTestModeEnabled ] = useTestMode();
	const [ enabledPaymentMethodIds ] = useEnabledPaymentMethodIds();
	const hasAPMEnabled =
		enabledPaymentMethodIds.filter( ( e ) => e !== PAYMENT_METHOD_CARD )
			.length > 0;

	useEffect( () => {
		if ( isConnectedViaOAuth === false ) {
			setPromotionalBannerType( RECONNECT_BANNER );
		} else if ( ! isUpeEnabled ) {
			setPromotionalBannerType( NEW_CHECKOUT_EXPERIENCE_BANNER );
		}
	}, [ isUpeEnabled, isConnectedViaOAuth, setPromotionalBannerType ] );

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

	const handleReConnectButtonClick = () => {
		if ( isTestModeEnabled && testOauthUrl ) {
			recordEvent( 'wcstripe_create_or_connect_test_account_click', {} );
			window.location.assign( testOauthUrl );
		} else if ( ! isTestModeEnabled && oauthUrl ) {
			recordEvent( 'wcstripe_create_or_connect_account_click', {} );
			window.location.assign( oauthUrl );
		} else {
			createErrorNotice(
				__(
					'There was an error. Please reload the page and try again.',
					'woocommerce-gateway-stripe'
				)
			);
		}
	};

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
							'Re-connect your Stripe account using the new authentication flow by clicking the "Re-authenticate" button and make your store safer.',
							'woocommerce-gateway-stripe'
						) }
					</p>
				</CardColumn>
				<CardColumn>
					<BannerIllustration
						src={ bannerIllustrationReConnect }
						alt={ __(
							'Re-authenticate',
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
					{ __( 'Re-authenticate', 'woocommerce-gateway-stripe' ) }
				</MainCTALink>
			</ButtonsRow>
		</CardBody>
	);

	let newCheckoutExperienceAPMsBannerDescription = '';
	// eslint-disable-next-line camelcase
	if ( wc_stripe_settings_params.are_apms_deprecated ) {
		newCheckoutExperienceAPMsBannerDescription = __(
			'Stripe ended support for non-card payment methods in the {{StripeLegacyLink}}legacy checkout on October 29, 2024{{/StripeLegacyLink}}. To continue accepting non-card payments, you must enable the new checkout experience or remove non-card payment methods from your checkout to avoid payment disruptions.',
			'woocommerce-gateway-stripe'
		);
	} else {
		newCheckoutExperienceAPMsBannerDescription = __(
			'Stripe will end support for non-card payment methods in the {{StripeLegacyLink}}legacy checkout on October 29, 2024{{/StripeLegacyLink}}. To continue accepting non-card payments, you must enable the new checkout experience or remove non-card payment methods from your checkout to avoid payment disruptions.',
			'woocommerce-gateway-stripe'
		);
	}

	const NewCheckoutExperienceAPMsBanner = () => (
		<CardBody data-testid="new-checkout-apms-banner">
			<CardInner>
				<CardColumn>
					<NewPill>
						{ __( 'New', 'woocommerce-gateway-stripe' ) }
					</NewPill>
					<h4>
						{ __(
							'Enable the new Stripe checkout to continue accepting non-card payments',
							'woocommerce-gateway-stripe'
						) }
					</h4>
					<p>
						{ interpolateComponents( {
							mixedString: newCheckoutExperienceAPMsBannerDescription,
							components: {
								StripeLegacyLink: (
									<ExternalLink href="https://support.stripe.com/topics/shutdown-of-the-legacy-sources-api-for-non-card-payment-methods" />
								),
							},
						} ) }
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
					data-testid="disable-the-legacy-checkout"
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
	if ( isConnectedViaOAuth === false ) {
		BannerContent = <ReConnectAccountBanner />;
	} else if ( ! isUpeEnabled ) {
		if ( hasAPMEnabled ) {
			BannerContent = <NewCheckoutExperienceAPMsBanner />;
		} else {
			BannerContent = <NewCheckoutExperienceBanner />;
		}
	}

	return (
		BannerContent && (
			<BannerCard data-testid="promotional-banner-card">
				{ BannerContent }
			</BannerCard>
		)
	);
};

export default PromotionalBannerSection;
