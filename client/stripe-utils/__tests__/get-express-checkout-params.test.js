import { getExpressCheckoutParams } from '../get-express-checkout-params';
import { isAmazonPayEnabled } from '../is-amazon-pay-enabled';
import { getExpressCheckoutData } from 'wcstripe/express-checkout/utils';

describe( 'getExpressCheckoutParams', () => {
	afterEach( () => {
		delete global.wc_stripe_express_checkout_params;
		delete global.wcSettings;
	} );

	it( 'prefers the classic global when it is present', () => {
		global.wc_stripe_express_checkout_params = { has_block: false };
		global.wcSettings = { stripe_data: { has_block: true } };

		expect( getExpressCheckoutParams() ).toEqual( { has_block: false } );
	} );

	it( 'reads the blocks stripe_data setting on block cart and checkout pages', () => {
		global.wcSettings = {
			paymentMethodData: {
				stripe: {
					has_block: true,
					nonce: { wc_store_api: 'store-api-nonce' },
					stripe: { is_amazon_pay_enabled: true },
				},
			},
		};

		expect( getExpressCheckoutParams()?.has_block ).toBe( true );
		expect( getExpressCheckoutData( 'nonce' ) ).toEqual( {
			wc_store_api: 'store-api-nonce',
		} );
		expect( isAmazonPayEnabled() ).toBe( true );
	} );

	it( 'ignores stripe_data on pages without a cart or checkout block', () => {
		global.wcSettings = {
			stripe_data: {
				has_block: false,
				stripe: { is_amazon_pay_enabled: true },
			},
		};

		expect( getExpressCheckoutParams() ).toBeNull();
		expect( getExpressCheckoutData( 'stripe' ) ).toBeNull();
		expect( isAmazonPayEnabled() ).toBe( false );
	} );

	it( 'returns null when neither source is available', () => {
		expect( getExpressCheckoutParams() ).toBeNull();
	} );
} );
