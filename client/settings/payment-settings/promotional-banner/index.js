import { React, useEffect } from 'react';
import {
	RECONNECT_BANNER,
	NEW_CHECKOUT_EXPERIENCE_BANNER,
	BNPL_PROMOTION_BANNER,
} from '../constants';
import { useEnabledPaymentMethodIds } from 'wcstripe/data';
import {
	BNPL_METHODS,
	PAYMENT_METHOD_CARD,
} from 'wcstripe/stripe-utils/constants';
import { ReConnectAccountBanner } from 'wcstripe/settings/payment-settings/promotional-banner/re-connect-account-banner';
import { NewCheckoutExperienceAPMsBanner } from 'wcstripe/settings/payment-settings/promotional-banner/new-checkout-experience-apms-banner';
import { NewCheckoutExperienceBanner } from 'wcstripe/settings/payment-settings/promotional-banner/new-checkout-experience-banner';
import { BNPLPromotionBanner } from 'wcstripe/settings/payment-settings/promotional-banner/bnpl-promotion-banner';
import { BannerCard } from 'wcstripe/settings/payment-settings/promotional-banner/banner-layout';

const PromotionalBanner = ( {
	setShowPromotionalBanner,
	setPromotionalBannerType,
	isUpeEnabled,
	setIsUpeEnabled,
	isConnectedViaOAuth,
	oauthUrl,
	testOauthUrl,
} ) => {
	const [ enabledPaymentMethodIds ] = useEnabledPaymentMethodIds();
	const hasAPMEnabled =
		enabledPaymentMethodIds.filter( ( e ) => e !== PAYMENT_METHOD_CARD )
			.length > 0;
	const hasBNPLEnabled =
		enabledPaymentMethodIds.filter( ( e ) => BNPL_METHODS.includes( e ) )
			.length > 0;

	useEffect( () => {
		if ( isConnectedViaOAuth === false ) {
			setPromotionalBannerType( RECONNECT_BANNER );
		} else if ( isUpeEnabled && ! hasBNPLEnabled ) {
			setPromotionalBannerType( BNPL_PROMOTION_BANNER );
		} else if ( ! isUpeEnabled ) {
			setPromotionalBannerType( NEW_CHECKOUT_EXPERIENCE_BANNER );
		}
	}, [
		isUpeEnabled,
		isConnectedViaOAuth,
		setPromotionalBannerType,
		hasBNPLEnabled,
	] );

	let BannerContent = null;
	if ( isConnectedViaOAuth === false ) {
		BannerContent = (
			<ReConnectAccountBanner
				testOauthUrl={ testOauthUrl }
				oauthUrl={ oauthUrl }
			/>
		);
	} else if ( isUpeEnabled && ! hasBNPLEnabled ) {
		BannerContent = (
			<BNPLPromotionBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
			/>
		);
	} else if ( ! isUpeEnabled ) {
		if ( hasAPMEnabled ) {
			BannerContent = (
				<NewCheckoutExperienceAPMsBanner
					setShowPromotionalBanner={ setShowPromotionalBanner }
					setIsUpeEnabled={ setIsUpeEnabled }
				/>
			);
		} else {
			BannerContent = (
				<NewCheckoutExperienceBanner
					setShowPromotionalBanner={ setShowPromotionalBanner }
					setIsUpeEnabled={ setIsUpeEnabled }
				/>
			);
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

export default PromotionalBanner;
