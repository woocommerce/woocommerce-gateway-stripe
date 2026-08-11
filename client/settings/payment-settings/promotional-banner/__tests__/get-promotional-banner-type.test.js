import { getPromotionalBannerType } from 'wcstripe/settings/payment-settings/promotional-banner/get-promotional-banner-type';
import {
	AP_ONLY_BANNER,
	BNPL_PROMOTION_BANNER,
	OC_PROMOTION_BANNER,
	OCS_AP_BANNER,
	OCS_ONLY_BANNER,
	RECONNECT_BANNER,
	STRIPE_TAX_BANNER,
} from 'wcstripe/settings/payment-settings/constants';
import {
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_AFFIRM,
	PAYMENT_METHOD_AFTERPAY_CLEARPAY,
	PAYMENT_METHOD_KLARNA,
} from 'wcstripe/stripe-utils/constants';

describe( 'getPromotionalBannerType', () => {
	beforeEach( () => {
		global.wc_stripe_settings_params = {};
	} );

	afterEach( () => {
		delete global.wc_stripe_settings_params;
	} );

	it( 'Reconnect banner', () => {
		global.wc_stripe_settings_params = {};

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

	describe( 'OCS+AP banner', () => {
		const accountData = {
			testmode: false,
			oauth_connections: {
				live: { connected: true },
			},
		};
		const isOCEnabled = true;
		const enabledPaymentMethodIds = [ PAYMENT_METHOD_CARD ];

		it( 'should not be selected when the server flag is missing', () => {
			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					enabledPaymentMethodIds
				)
			).toBeNull();
		} );

		it( 'should not be selected when the server flag is empty', () => {
			global.wc_stripe_settings_params = {
				show_ocs_ap_banner: '',
			};

			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					enabledPaymentMethodIds
				)
			).toBeNull();
		} );

		it( 'should be selected when the server flag is set to 1', () => {
			global.wc_stripe_settings_params = {
				show_ocs_ap_banner: '1',
			};

			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					enabledPaymentMethodIds
				)
			).toBe( OCS_AP_BANNER );
		} );

		it( 'should take precedence over AP-only banner when both flags are set', () => {
			global.wc_stripe_settings_params = {
				show_ocs_ap_banner: '1',
				show_ap_only_banner: '1',
			};

			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					enabledPaymentMethodIds
				)
			).toBe( OCS_AP_BANNER );
		} );
	} );

	describe( 'AP-only banner', () => {
		const accountData = {
			testmode: false,
			oauth_connections: {
				live: { connected: true },
			},
		};
		const isOCEnabled = true;
		const enabledPaymentMethodIds = [ PAYMENT_METHOD_CARD ];

		it( 'should not be selected when the server flag is missing', () => {
			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					enabledPaymentMethodIds
				)
			).toBeNull();
		} );

		it( 'should be selected when the server flag is set to 1', () => {
			global.wc_stripe_settings_params = {
				show_ap_only_banner: '1',
			};

			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					enabledPaymentMethodIds
				)
			).toBe( AP_ONLY_BANNER );
		} );
	} );

	describe( 'OCS-only banner', () => {
		const accountData = {
			testmode: false,
			oauth_connections: {
				live: { connected: true },
			},
		};
		const isOCEnabled = true;
		const enabledPaymentMethodIds = [ PAYMENT_METHOD_CARD ];

		it( 'should not be selected when the server flag is missing', () => {
			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					enabledPaymentMethodIds
				)
			).toBeNull();
		} );

		it( 'should be selected when the server flag is set to 1', () => {
			global.wc_stripe_settings_params = {
				show_ocs_only_banner: '1',
			};

			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					enabledPaymentMethodIds
				)
			).toBe( OCS_ONLY_BANNER );
		} );

		it( 'should yield to OCS+AP and AP-only when multiple flags are set', () => {
			global.wc_stripe_settings_params = {
				show_ocs_ap_banner: '1',
				show_ap_only_banner: '1',
				show_ocs_only_banner: '1',
			};

			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					enabledPaymentMethodIds
				)
			).toBe( OCS_AP_BANNER );
		} );
	} );

	describe( 'Stripe Tax banner', () => {
		beforeEach( () => {
			global.wc_stripe_settings_params = {
				is_oc_available: '1',
			};
		} );

		const accountData = {
			testmode: false,
			oauth_connections: {
				live: { connected: true },
			},
		};
		const isOCEnabled = true;
		const enabledPaymentMethodIds = [ PAYMENT_METHOD_CARD ];

		it( 'should not be selected when the server flag is missing', () => {
			delete global.wc_stripe_settings_params.show_stripe_tax_banner;

			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					enabledPaymentMethodIds
				)
			).toBeNull();
		} );

		it( 'should not be selected when the server flag is empty', () => {
			global.wc_stripe_settings_params = {
				...global.wc_stripe_settings_params,
				show_stripe_tax_banner: '',
			};

			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					enabledPaymentMethodIds
				)
			).toBeNull();
		} );

		it( 'should be selected when the server flag is set to 1', () => {
			global.wc_stripe_settings_params = {
				...global.wc_stripe_settings_params,
				show_stripe_tax_banner: '1',
			};

			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					enabledPaymentMethodIds
				)
			).toBe( STRIPE_TAX_BANNER );
		} );
	} );

	describe( 'OC promotion banner', () => {
		beforeEach( () => {
			global.wc_stripe_settings_params = {
				is_oc_available: '1',
			};
		} );

		const accountData = {
			testmode: false,
			oauth_connections: {
				live: { connected: true },
			},
		};
		const isOCEnabled = false;
		const enabledPaymentMethodIds = [ PAYMENT_METHOD_CARD ];

		it( 'should not be selected when the server flag is missing', () => {
			delete global.wc_stripe_settings_params.show_oc_promotional_banner;

			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					enabledPaymentMethodIds
				)
			).toBeNull();
		} );

		it( 'should not be selected when the server flag is empty', () => {
			global.wc_stripe_settings_params = {
				...global.wc_stripe_settings_params,
				show_oc_promotional_banner: '',
			};

			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					enabledPaymentMethodIds
				)
			).toBeNull();
		} );

		it( 'should be selected when the server flag is set to 1', () => {
			global.wc_stripe_settings_params = {
				...global.wc_stripe_settings_params,
				show_oc_promotional_banner: '1',
			};

			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					enabledPaymentMethodIds
				)
			).toBe( OC_PROMOTION_BANNER );
		} );
	} );

	describe( 'BNPL promotion banner', () => {
		beforeEach( () => {
			global.wc_stripe_settings_params = {
				has_other_bnpl_plugins: '',
			};
		} );

		const accountData = {
			testmode: false,
			oauth_connections: {
				live: { connected: true },
			},
		};
		const isOCEnabled = false;
		const enabledPaymentMethodIds = [ PAYMENT_METHOD_CARD ];

		it( 'should not be selected when the server flag is missing', () => {
			delete global.wc_stripe_settings_params
				.show_bnpl_promotional_banner;

			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					enabledPaymentMethodIds
				)
			).toBeNull();
		} );

		it( 'should not be selected when the server flag is empty', () => {
			global.wc_stripe_settings_params = {
				...global.wc_stripe_settings_params,
				show_bnpl_promotional_banner: '',
			};

			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					enabledPaymentMethodIds
				)
			).toBeNull();
		} );

		it( 'should be selected when the server flag is set to 1', () => {
			global.wc_stripe_settings_params = {
				...global.wc_stripe_settings_params,
				show_bnpl_promotional_banner: '1',
			};

			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					enabledPaymentMethodIds
				)
			).toBe( BNPL_PROMOTION_BANNER );
		} );

		it( 'should not be selected when other BNPL plugins are enabled', () => {
			global.wc_stripe_settings_params = {
				...global.wc_stripe_settings_params,
				has_other_bnpl_plugins: '1',
				show_bnpl_promotional_banner: '1',
			};

			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					enabledPaymentMethodIds
				)
			).toBeNull();
		} );

		it( 'should not be selected when Affirm is enabled', () => {
			global.wc_stripe_settings_params = {
				...global.wc_stripe_settings_params,
				has_other_bnpl_plugins: '',
				show_bnpl_promotional_banner: '1',
			};

			const paymentMethodIds = [
				PAYMENT_METHOD_CARD,
				PAYMENT_METHOD_AFFIRM,
			];

			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					paymentMethodIds
				)
			).toBeNull();
		} );

		it( 'should not be selected when Afterpay/Clearpay is enabled', () => {
			global.wc_stripe_settings_params = {
				...global.wc_stripe_settings_params,
				has_other_bnpl_plugins: '',
				show_bnpl_promotional_banner: '1',
			};

			const paymentMethodIds = [
				PAYMENT_METHOD_CARD,
				PAYMENT_METHOD_AFTERPAY_CLEARPAY,
			];

			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					paymentMethodIds
				)
			).toBeNull();
		} );

		it( 'should not be selected when Klarna is enabled', () => {
			global.wc_stripe_settings_params = {
				...global.wc_stripe_settings_params,
				has_other_bnpl_plugins: '',
				show_bnpl_promotional_banner: '1',
			};

			const paymentMethodIds = [
				PAYMENT_METHOD_CARD,
				PAYMENT_METHOD_KLARNA,
			];

			expect(
				getPromotionalBannerType(
					accountData,
					isOCEnabled,
					paymentMethodIds
				)
			).toBeNull();
		} );
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
