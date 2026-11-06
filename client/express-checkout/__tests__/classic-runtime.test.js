jest.mock( 'react', () => {
	throw new Error( 'Classic wallet startup must not load React.' );
} );
jest.mock( '@wordpress/data', () => {
	throw new Error(
		'Classic wallet startup must not load the Blocks registry.'
	);
} );

it( 'loads the Classic wallet entrypoint and normalizes payment data without the Blocks runtime', async () => {
	global.wc_stripe_express_checkout_params = {
		has_block: false,
		is_checkout_page: true,
	};
	global.wc_stripe_upe_params = { isCheckout: true };
	try {
		require( '../../entrypoints/express-checkout' );
		// Wait for the entrypoint's jQuery-ready callback before removing its globals.
		await new Promise( ( resolve ) =>
			require( 'jquery' )( () => resolve() )
		);
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
