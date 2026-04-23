const mockStripeConstructor = jest.fn( () => ( {} ) );

global.Stripe = mockStripeConstructor;

import WCStripeAPI from 'wcstripe/api';

describe( 'WCStripeAPI', () => {
	beforeEach( () => {
		mockStripeConstructor.mockReturnValue( {} );
		mockStripeConstructor.mockClear();
	} );

	it( 'initializes Stripe.js with testing assistant disabled', () => {
		const api = new WCStripeAPI(
			{ key: 'pk_test_abc', locale: 'auto' },
			jest.fn()
		);
		api.getStripe();
		expect( mockStripeConstructor ).toHaveBeenCalledWith(
			'pk_test_abc',
			expect.objectContaining( {
				locale: 'auto',
				developerTools: {
					assistant: {
						enabled: false,
					},
				},
			} )
		);
	} );
} );
