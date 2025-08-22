/* global wc_stripe_settings_params */
import {
	BNPL_PROMOTION_BANNER,
	NEW_CHECKOUT_EXPERIENCE_APMS_BANNER,
	NEW_CHECKOUT_EXPERIENCE_BANNER,
	OC_PROMOTION_BANNER,
	RECONNECT_BANNER,
} from 'wcstripe/settings/payment-settings/constants';
import {
	BNPL_METHODS,
	PAYMENT_METHOD_CARD,
} from 'wcstripe/stripe-utils/constants';

/**
 * Returns the type of promotional banner to display based on the current extension state.
 *
 * @param {Object}  accountData             The account data object containing information about the Stripe account.
 * @param {boolean} isUpeEnabled            Whether the Unified Payments Experience (UPE) is enabled.
 * @param {boolean} isOCEnabled             Whether the Optimized Checkout Suite (OC) is enabled.
 * @param {Array}   enabledPaymentMethodIds List of enabled payment method IDs.
 * @return {null|string} The type of promotional banner to display, or null if no banner is applicable.
 */
export const getPromotionalBannerType = (
	accountData,
	isUpeEnabled,
	isOCEnabled,
	enabledPaymentMethodIds
) => {
	const isTestModeEnabled = Boolean( accountData.testmode );
	const oauthConnected = isTestModeEnabled
		? accountData?.oauth_connections?.test?.connected
		: accountData?.oauth_connections?.live?.connected;
	const hasAPMEnabled =
		enabledPaymentMethodIds.filter( ( e ) => e !== PAYMENT_METHOD_CARD )
			.length > 0;
	const hasBNPLEnabled =
		enabledPaymentMethodIds.filter( ( e ) => BNPL_METHODS.includes( e ) )
			.length > 0;

	if ( oauthConnected === false ) {
		return RECONNECT_BANNER;
	} else if (
		// eslint-disable-next-line camelcase
		wc_stripe_settings_params?.is_oc_available &&
		! isOCEnabled
	) {
		return OC_PROMOTION_BANNER;
	} else if (
		isUpeEnabled &&
		! hasBNPLEnabled &&
		// eslint-disable-next-line camelcase
		! wc_stripe_settings_params?.has_other_bnpl_plugins
	) {
		return BNPL_PROMOTION_BANNER;
	} else if ( ! isUpeEnabled ) {
		if ( hasAPMEnabled ) {
			return NEW_CHECKOUT_EXPERIENCE_APMS_BANNER;
		}
		return NEW_CHECKOUT_EXPERIENCE_BANNER;
	}
	return null;
};
