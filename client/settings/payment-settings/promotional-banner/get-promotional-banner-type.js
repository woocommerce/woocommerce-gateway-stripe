/* global wc_stripe_settings_params */
import {
	AP_ONLY_BANNER,
	BNPL_PROMOTION_BANNER,
	OC_PROMOTION_BANNER,
	OCS_AP_BANNER,
	OCS_ONLY_BANNER,
	RECONNECT_BANNER,
	STRIPE_TAX_BANNER,
} from 'wcstripe/settings/payment-settings/constants';
import { BNPL_METHODS } from 'wcstripe/stripe-utils/constants';

/**
 * Returns the type of promotional banner to display based on the current extension state.
 *
 * @param {Object}  accountData             Stripe account data.
 * @param {boolean} isOCEnabled             Whether OC is currently enabled.
 * @param {Array}   enabledPaymentMethodIds Currently enabled payment method IDs.
 * @return {null|string} The type of promotional banner to display, or null if no banner is applicable.
 */
export const getPromotionalBannerType = (
	accountData,
	isOCEnabled,
	enabledPaymentMethodIds
) => {
	const isTestModeEnabled = Boolean( accountData.testmode );
	const oauthConnected = isTestModeEnabled
		? accountData?.oauth_connections?.test?.connected
		: accountData?.oauth_connections?.live?.connected;
	const hasBNPLEnabled =
		enabledPaymentMethodIds.filter( ( e ) => BNPL_METHODS.includes( e ) )
			.length > 0;
	// eslint-disable-next-line camelcase
	const isOCAvailable = wc_stripe_settings_params?.is_oc_available === '1';

	if ( oauthConnected === false ) {
		return RECONNECT_BANNER;
	}

	// eslint-disable-next-line camelcase
	if ( wc_stripe_settings_params?.show_ocs_ap_banner === '1' ) {
		return OCS_AP_BANNER;
	}

	// eslint-disable-next-line camelcase
	if ( wc_stripe_settings_params?.show_ap_only_banner === '1' ) {
		return AP_ONLY_BANNER;
	}

	// eslint-disable-next-line camelcase
	if ( wc_stripe_settings_params?.show_ocs_only_banner === '1' ) {
		return OCS_ONLY_BANNER;
	}

	if (
		isOCAvailable &&
		isOCEnabled &&
		// eslint-disable-next-line camelcase
		wc_stripe_settings_params?.show_stripe_tax_banner === '1'
	) {
		return STRIPE_TAX_BANNER;
	}

	if (
		isOCAvailable &&
		! isOCEnabled &&
		// eslint-disable-next-line camelcase
		wc_stripe_settings_params?.show_oc_promotional_banner === '1'
	) {
		return OC_PROMOTION_BANNER;
	}

	if (
		! hasBNPLEnabled &&
		// eslint-disable-next-line camelcase
		wc_stripe_settings_params?.has_other_bnpl_plugins !== '1' &&
		// eslint-disable-next-line camelcase
		wc_stripe_settings_params?.show_bnpl_promotional_banner === '1'
	) {
		return BNPL_PROMOTION_BANNER;
	}

	return null;
};
