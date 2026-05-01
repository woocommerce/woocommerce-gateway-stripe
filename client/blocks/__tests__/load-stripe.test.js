const mockLoadStripe = jest.fn( () => Promise.resolve( {} ) );

jest.mock( '@stripe/stripe-js', () => ( {
	loadStripe: ( ...args ) => mockLoadStripe( ...args ),
} ) );

jest.mock( 'wcstripe/blocks/utils', () => ( {
	getApiKey: jest.fn( () => 'pk_test_xxx' ),
	getBlocksConfiguration: jest.fn( () => ( { stripe_locale: 'en' } ) ),
} ) );

import { loadStripe } from 'wcstripe/blocks/load-stripe';
import { getStripeDevWidgetOptions } from 'wcstripe/stripe-utils';

jest.mock( 'wcstripe/stripe-utils', () => ( {
	getStripeDevWidgetOptions: jest.fn(),
} ) );

describe( 'load-stripe', () => {
	beforeEach( () => {
		mockLoadStripe.mockClear();
		getStripeDevWidgetOptions.mockReset();
	} );

	it( 'passes developerTools.assistant.enabled false to Stripe loadStripe when disabled', async () => {
		getStripeDevWidgetOptions.mockReturnValue( {
			developerTools: {
				assistant: {
					enabled: false,
				},
			},
		} );
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

	it( 'passes developerTools.assistant.enabled true to Stripe loadStripe when enabled', async () => {
		getStripeDevWidgetOptions.mockReturnValue( {
			developerTools: {
				assistant: {
					enabled: true,
				},
			},
		} );
		await loadStripe();
		expect( mockLoadStripe ).toHaveBeenCalledWith(
			'pk_test_xxx',
			expect.objectContaining( {
				locale: 'en',
				developerTools: {
					assistant: {
						enabled: true,
					},
				},
			} )
		);
	} );
} );
