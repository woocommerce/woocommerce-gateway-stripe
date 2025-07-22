import { React } from 'react';
import {
	RECONNECT_BANNER,
	NEW_CHECKOUT_EXPERIENCE_BANNER,
	BNPL_PROMOTION_BANNER,
	NEW_CHECKOUT_EXPERIENCE_APMS_BANNER,
	OC_PROMOTION_BANNER,
} from '../constants';
import { ReConnectAccountBanner } from 'wcstripe/settings/payment-settings/promotional-banner/re-connect-account-banner';
import { NewCheckoutExperienceAPMsBanner } from 'wcstripe/settings/payment-settings/promotional-banner/new-checkout-experience-apms-banner';
import { NewCheckoutExperienceBanner } from 'wcstripe/settings/payment-settings/promotional-banner/new-checkout-experience-banner';
import { BNPLPromotionBanner } from 'wcstripe/settings/payment-settings/promotional-banner/bnpl-promotion-banner';
import { BannerCard } from 'wcstripe/settings/payment-settings/promotional-banner/banner-layout';
import { OCPromotionBanner } from 'wcstripe/settings/payment-settings/promotional-banner/oc-promotion-banner';

const PromotionalBanner = ( {
	setShowPromotionalBanner,
	promotionalBannerType,
	setIsUpeEnabled,
	setIsOCEnabled,
	oauthUrl,
	testOauthUrl,
} ) => {
	let BannerContent = null;
	switch ( promotionalBannerType ) {
		case RECONNECT_BANNER:
			BannerContent = (
				<ReConnectAccountBanner
					testOauthUrl={ testOauthUrl }
					oauthUrl={ oauthUrl }
				/>
			);
			break;
		case OC_PROMOTION_BANNER:
			BannerContent = (
				<OCPromotionBanner
					setShowPromotionalBanner={ setShowPromotionalBanner }
					setIsOCEnabled={ setIsOCEnabled }
				/>
			);
			break;
		case BNPL_PROMOTION_BANNER:
			BannerContent = (
				<BNPLPromotionBanner
					setShowPromotionalBanner={ setShowPromotionalBanner }
				/>
			);
			break;
		case NEW_CHECKOUT_EXPERIENCE_APMS_BANNER:
			BannerContent = (
				<NewCheckoutExperienceAPMsBanner
					setShowPromotionalBanner={ setShowPromotionalBanner }
					setIsUpeEnabled={ setIsUpeEnabled }
				/>
			);
			break;
		case NEW_CHECKOUT_EXPERIENCE_BANNER:
			BannerContent = (
				<NewCheckoutExperienceBanner
					setShowPromotionalBanner={ setShowPromotionalBanner }
					setIsUpeEnabled={ setIsUpeEnabled }
				/>
			);
			break;
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
