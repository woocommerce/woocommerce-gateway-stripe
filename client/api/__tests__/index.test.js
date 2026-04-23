const mockStripeConstructor = jest.fn( () => ( {} ) );

global.Stripe = mockStripeConstructor;

const mockWrapStripe = jest.fn();
jest.mock( 'wcstripe/diagnostics/recorder', () => ( {
	getRecorder: () => ( {
		wrapStripe: mockWrapStripe,
	} ),
} ) );

import WCStripeAPI from 'wcstripe/api';

describe( 'WCStripeAPI', () => {
	beforeEach( () => {
		mockStripeConstructor.mockReturnValue( {} );
		mockStripeConstructor.mockClear();
		mockWrapStripe.mockClear();
		delete window.wcStripeDiag;
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

	describe( 'diagnostics integration', () => {
		it( 'wraps the Stripe instance with the diagnostics recorder when wcStripeDiag is active', () => {
			window.wcStripeDiag = { active: true };
			const stripeInstance = { id: 'mock-stripe' };
			mockStripeConstructor.mockReturnValue( stripeInstance );

			const api = new WCStripeAPI(
				{ key: 'pk_test_abc', locale: 'auto' },
				jest.fn()
			);
			const result = api.getStripe();

			expect( result ).toBe( stripeInstance );
			expect( mockWrapStripe ).toHaveBeenCalledTimes( 1 );
			expect( mockWrapStripe ).toHaveBeenCalledWith( stripeInstance );
		} );

		it( 'does not wrap the Stripe instance when wcStripeDiag is absent', () => {
			const api = new WCStripeAPI(
				{ key: 'pk_test_abc', locale: 'auto' },
				jest.fn()
			);
			api.getStripe();

			expect( mockWrapStripe ).not.toHaveBeenCalled();
		} );

		it( 'wraps only on the first getStripe() call (singleton stripe)', () => {
			window.wcStripeDiag = { active: true };
			const api = new WCStripeAPI(
				{ key: 'pk_test_abc', locale: 'auto' },
				jest.fn()
			);

			api.getStripe();
			api.getStripe();
			api.getStripe();

			expect( mockWrapStripe ).toHaveBeenCalledTimes( 1 );
		} );
	} );
} );
