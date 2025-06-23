import { getPromotionalBannerType } from 'wcstripe/settings/payment-settings/promotional-banner/get-promotional-banner-type';
import {
	BNPL_PROMOTION_BANNER,
	NEW_CHECKOUT_EXPERIENCE_APMS_BANNER,
	NEW_CHECKOUT_EXPERIENCE_BANNER,
	RECONNECT_BANNER,
} from 'wcstripe/settings/payment-settings/constants';
import {
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_IDEAL,
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
		const isUpeEnabled = true;
		const enabledPaymentMethodIds = [ PAYMENT_METHOD_CARD ];

		expect(
			getPromotionalBannerType(
				accountData,
				isUpeEnabled,
				enabledPaymentMethodIds
			)
		).toBe( RECONNECT_BANNER );
	} );
	it( 'BNPL promotion banner', () => {
		global.wc_stripe_settings_params = {
			plugin_version: '9.7.0',
		};

		const accountData = {
			testmode: false,
			oauth_connections: {
				live: { connected: true },
			},
		};
		const isUpeEnabled = true;
		const enabledPaymentMethodIds = [ PAYMENT_METHOD_CARD ];

		expect(
			getPromotionalBannerType(
				accountData,
				isUpeEnabled,
				enabledPaymentMethodIds
			)
		).toBe( BNPL_PROMOTION_BANNER );
	} );
	it( 'New checkout experience APMs banner', () => {
		const accountData = {
			testmode: false,
			oauth_connections: {
				live: { connected: true },
			},
		};
		const isUpeEnabled = false;
		const enabledPaymentMethodIds = [
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_IDEAL,
		];

		expect(
			getPromotionalBannerType(
				accountData,
				isUpeEnabled,
				enabledPaymentMethodIds
			)
		).toBe( NEW_CHECKOUT_EXPERIENCE_APMS_BANNER );
	} );
	it( 'New checkout experience banner', () => {
		const accountData = {
			testmode: false,
			oauth_connections: {
				live: { connected: true },
			},
		};
		const isUpeEnabled = false;
		const enabledPaymentMethodIds = [ PAYMENT_METHOD_CARD ];

		expect(
			getPromotionalBannerType(
				accountData,
				isUpeEnabled,
				enabledPaymentMethodIds
			)
		).toBe( NEW_CHECKOUT_EXPERIENCE_BANNER );
	} );
	it( 'No banner', () => {
		const accountData = {
			testmode: false,
			oauth_connections: {
				live: { connected: true },
			},
		};
		const isUpeEnabled = true;
		const enabledPaymentMethodIds = [
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_KLARNA,
		];

		expect(
			getPromotionalBannerType(
				accountData,
				isUpeEnabled,
				enabledPaymentMethodIds
			)
		).toBeNull();
	} );
} );
