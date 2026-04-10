const mockLoadStripe = jest.fn( () => Promise.resolve( {} ) );

jest.mock( '@stripe/stripe-js', () => ( {
	loadStripe: ( ...args ) => mockLoadStripe( ...args ),
} ) );

jest.mock( 'wcstripe/blocks/utils', () => ( {
	getApiKey: jest.fn( () => 'pk_test_xxx' ),
	getBlocksConfiguration: jest.fn( () => ( { stripe_locale: 'en' } ) ),
} ) );

import { loadStripe } from 'wcstripe/blocks/load-stripe';

describe( 'load-stripe', () => {
	beforeEach( () => {
		mockLoadStripe.mockClear();
	} );

	it( 'passes developerTools.assistant.enabled false to Stripe loadStripe', async () => {
		await loadStripe();
		expect( mockLoadStripe ).toHaveBeenCalledWith(
			'pk_test_xxx',
			expect.objectContaining( {
				locale: 'en',
				developerTools: {
					assistant: {
						enabled: false,
					},
				},
			} )
		);
	} );
} );
