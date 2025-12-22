import { getPromotionalBannerType } from 'wcstripe/settings/payment-settings/promotional-banner/get-promotional-banner-type';
import {
	BNPL_PROMOTION_BANNER,
	OC_PROMOTION_BANNER,
	RECONNECT_BANNER,
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
