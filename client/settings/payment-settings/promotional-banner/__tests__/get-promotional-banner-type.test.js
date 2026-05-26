import { getPromotionalBannerType } from 'wcstripe/settings/payment-settings/promotional-banner/get-promotional-banner-type';
import {
	ADAPTIVE_PRICING_BANNER,
	BNPL_PROMOTION_BANNER,
	OC_PROMOTION_BANNER,
	RECONNECT_BANNER,
	STRIPE_TAX_BANNER,
} from 'wcstripe/settings/payment-settings/constants';
import {
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_KLARNA,
} from 'wcstripe/stripe-utils/constants';

describe( 'getPromotionalBannerType', () => {
	it( 'Reconnect banner', () => {
		const accountData = {
			testmode: false,
			oauth_connections: {
				live: { connected: false },
			},
		};
		const isOCEnabled = false;
		const enabledPaymentMethodIds = [ PAYMENT_METHOD_CARD ];

		expect(
			getPromotionalBannerType(
				accountData,
				isOCEnabled,
				enabledPaymentMethodIds
			)
		).toBe( RECONNECT_BANNER );
	} );
	it( 'Adaptive Pricing banner — OC enabled, AP disabled', () => {
		global.wc_stripe_settings_params = {
			is_oc_available: true,
		};

		const accountData = {
			testmode: false,
			oauth_connections: {
				live: { connected: true },
			},
		};
		const isOCEnabled = true;
		const enabledPaymentMethodIds = [ PAYMENT_METHOD_CARD ];
		const isAdaptivePricingEnabled = false;

		expect(
			getPromotionalBannerType(
				accountData,
				isOCEnabled,
				enabledPaymentMethodIds,
				isAdaptivePricingEnabled
			)
		).toBe( ADAPTIVE_PRICING_BANNER );
	} );
	it( 'Stripe Tax banner — OC enabled and AP also enabled', () => {
		global.wc_stripe_settings_params = {
			is_oc_available: true,
		};

		const accountData = {
			testmode: false,
			oauth_connections: {
				live: { connected: true },
			},
		};
		const isOCEnabled = true;
		const enabledPaymentMethodIds = [ PAYMENT_METHOD_CARD ];
		const isAdaptivePricingEnabled = true;

		expect(
			getPromotionalBannerType(
				accountData,
				isOCEnabled,
				enabledPaymentMethodIds,
				isAdaptivePricingEnabled
			)
		).toBe( STRIPE_TAX_BANNER );
	} );
	it( 'OC promotion banner', () => {
		global.wc_stripe_settings_params = {
			is_oc_available: true,
		};

		const accountData = {
			testmode: false,
			oauth_connections: {
				live: { connected: true },
			},
		};
		const isOCEnabled = false;
		const enabledPaymentMethodIds = [ PAYMENT_METHOD_CARD ];

		expect(
			getPromotionalBannerType(
				accountData,
				isOCEnabled,
				enabledPaymentMethodIds
			)
		).toBe( OC_PROMOTION_BANNER );
	} );
	it( 'BNPL promotion banner', () => {
		global.wc_stripe_settings_params = {
			has_other_bnpl_plugins: false,
		};

		const accountData = {
			testmode: false,
			oauth_connections: {
				live: { connected: true },
			},
		};
		const isOCEnabled = false;
		const enabledPaymentMethodIds = [ PAYMENT_METHOD_CARD ];

		expect(
			getPromotionalBannerType(
				accountData,
				isOCEnabled,
				enabledPaymentMethodIds
			)
		).toBe( BNPL_PROMOTION_BANNER );
	} );
	it( 'No banner', () => {
		const accountData = {
			testmode: false,
			oauth_connections: {
				live: { connected: true },
			},
		};
		const isOCEnabled = false;
		const enabledPaymentMethodIds = [
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_KLARNA,
		];

		expect(
			getPromotionalBannerType(
				accountData,
				isOCEnabled,
				enabledPaymentMethodIds
			)
		).toBeNull();
	} );
} );
