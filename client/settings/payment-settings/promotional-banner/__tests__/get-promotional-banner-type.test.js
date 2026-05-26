import { getPromotionalBannerType } from 'wcstripe/settings/payment-settings/promotional-banner/get-promotional-banner-type';
import {
	AP_ONLY_BANNER,
	BNPL_PROMOTION_BANNER,
	OC_PROMOTION_BANNER,
	OCS_AP_BANNER,
	RECONNECT_BANNER,
	STRIPE_TAX_BANNER,
} from 'wcstripe/settings/payment-settings/constants';
import {
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_KLARNA,
} from 'wcstripe/stripe-utils/constants';

const connectedAccount = {
	testmode: false,
	oauth_connections: {
		live: { connected: true },
	},
};

describe( 'getPromotionalBannerType', () => {
	beforeEach( () => {
		global.wc_stripe_settings_params = {};
	} );

	it( 'returns RECONNECT_BANNER when OAuth is disconnected', () => {
		const accountData = {
			testmode: false,
			oauth_connections: { live: { connected: false } },
		};

		expect(
			getPromotionalBannerType( accountData, false, [
				PAYMENT_METHOD_CARD,
			] )
		).toBe( RECONNECT_BANNER );
	} );

	it( 'returns OCS_AP_BANNER when show_ocs_ap_banner is "1"', () => {
		global.wc_stripe_settings_params = {
			is_oc_available: true,
			show_ocs_ap_banner: '1',
		};

		expect(
			getPromotionalBannerType( connectedAccount, true, [
				PAYMENT_METHOD_CARD,
			] )
		).toBe( OCS_AP_BANNER );
	} );

	it( 'returns AP_ONLY_BANNER when only show_ap_only_banner is "1"', () => {
		global.wc_stripe_settings_params = {
			is_oc_available: true,
			show_ap_only_banner: '1',
		};

		expect(
			getPromotionalBannerType( connectedAccount, true, [
				PAYMENT_METHOD_CARD,
			] )
		).toBe( AP_ONLY_BANNER );
	} );

	it( 'prefers OCS_AP_BANNER when both show_*_banner flags are "1"', () => {
		global.wc_stripe_settings_params = {
			is_oc_available: true,
			show_ocs_ap_banner: '1',
			show_ap_only_banner: '1',
		};

		expect(
			getPromotionalBannerType( connectedAccount, true, [
				PAYMENT_METHOD_CARD,
			] )
		).toBe( OCS_AP_BANNER );
	} );

	it( 'falls through to STRIPE_TAX_BANNER when OC is enabled and neither new flag is "1"', () => {
		global.wc_stripe_settings_params = {
			is_oc_available: true,
		};

		expect(
			getPromotionalBannerType( connectedAccount, true, [
				PAYMENT_METHOD_CARD,
			] )
		).toBe( STRIPE_TAX_BANNER );
	} );

	it( 'returns OC_PROMOTION_BANNER when OC is disabled', () => {
		global.wc_stripe_settings_params = {
			is_oc_available: true,
		};

		expect(
			getPromotionalBannerType( connectedAccount, false, [
				PAYMENT_METHOD_CARD,
			] )
		).toBe( OC_PROMOTION_BANNER );
	} );

	it( 'returns BNPL_PROMOTION_BANNER when no BNPL methods are enabled and other BNPL plugins are absent', () => {
		global.wc_stripe_settings_params = {
			has_other_bnpl_plugins: false,
		};

		expect(
			getPromotionalBannerType( connectedAccount, false, [
				PAYMENT_METHOD_CARD,
			] )
		).toBe( BNPL_PROMOTION_BANNER );
	} );

	it( 'returns null when no condition matches', () => {
		expect(
			getPromotionalBannerType( connectedAccount, false, [
				PAYMENT_METHOD_CARD,
				PAYMENT_METHOD_KLARNA,
			] )
		).toBeNull();
	} );
} );
