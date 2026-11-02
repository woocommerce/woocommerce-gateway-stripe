jest.mock( 'react', () => {
	throw new Error( 'Classic wallet startup must not load React.' );
} );
jest.mock( '@wordpress/data', () => {
	throw new Error(
		'Classic wallet startup must not load the Blocks registry.'
	);
} );

it( 'loads the Classic wallet API and normalizes payment data without the Blocks runtime', () => {
	global.wc_stripe_express_checkout_params = {
		has_block: false,
		is_checkout_page: true,
	};
	global.wc_stripe_upe_params = { isCheckout: true };
	try {
		const WCStripeAPI = require( '../../api' ).default;
		const { normalizeOrderData } = require( '../utils/normalize' );
		expect( new WCStripeAPI( {}, jest.fn() ) ).toBeInstanceOf(
			WCStripeAPI
		);
		const result = normalizeOrderData( {
			event: { billingDetails: { email: 'shopper@example.test' } },
			paymentMethodId: 'pm_test',
			expressPaymentType: 'link',
		} );
		expect( result.billing_address.email ).toBe( 'shopper@example.test' );
		expect( result.payment_data ).toContainEqual( {
			key: 'wc-stripe-payment-method',
			value: 'pm_test',
		} );
	} finally {
		delete global.wc_stripe_express_checkout_params;
		delete global.wc_stripe_upe_params;
	}
} );
