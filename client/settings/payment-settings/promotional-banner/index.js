import { React } from 'react';
import {
	AP_ONLY_BANNER,
	BNPL_PROMOTION_BANNER,
	OC_PROMOTION_BANNER,
	OCS_AP_BANNER,
	OCS_ONLY_BANNER,
	RECONNECT_BANNER,
	STRIPE_TAX_BANNER,
} from '../constants';
import { ReConnectAccountBanner } from 'wcstripe/settings/payment-settings/promotional-banner/re-connect-account-banner';
import { BNPLPromotionBanner } from 'wcstripe/settings/payment-settings/promotional-banner/bnpl-promotion-banner';
import { BannerCard } from 'wcstripe/settings/payment-settings/promotional-banner/banner-layout';
import { OCPromotionBanner } from 'wcstripe/settings/payment-settings/promotional-banner/oc-promotion-banner';
import { StripeTaxBanner } from 'wcstripe/settings/payment-settings/promotional-banner/stripe-tax-banner';
import { OCSAndAPBanner } from 'wcstripe/settings/payment-settings/promotional-banner/ocs-ap-banner';
import { APOnlyBanner } from 'wcstripe/settings/payment-settings/promotional-banner/ap-only-banner';
import { OCSOnlyBanner } from 'wcstripe/settings/payment-settings/promotional-banner/ocs-only-banner';

const PromotionalBanner = ( {
	setShowPromotionalBanner,
	promotionalBannerType,
	setIsOCEnabled,
} ) => {
	let BannerContent = null;
	switch ( promotionalBannerType ) {
		case RECONNECT_BANNER:
			BannerContent = <ReConnectAccountBanner />;
			break;
		case OCS_AP_BANNER:
			BannerContent = (
				<OCSAndAPBanner
					setShowPromotionalBanner={ setShowPromotionalBanner }
				/>
			);
			break;
		case AP_ONLY_BANNER:
			BannerContent = (
				<APOnlyBanner
					setShowPromotionalBanner={ setShowPromotionalBanner }
				/>
			);
			break;
		case OCS_ONLY_BANNER:
			BannerContent = (
				<OCSOnlyBanner
					setShowPromotionalBanner={ setShowPromotionalBanner }
				/>
			);
			break;
		case STRIPE_TAX_BANNER:
			BannerContent = (
				<StripeTaxBanner
					setShowPromotionalBanner={ setShowPromotionalBanner }
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
