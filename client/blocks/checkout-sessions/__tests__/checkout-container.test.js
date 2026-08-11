import { extensionCartUpdate } from '@woocommerce/blocks-checkout';
import { useState } from 'react';
import { render } from '@testing-library/react';
import { CheckoutElementsProvider } from '@stripe/react-stripe-js/checkout';
import { CheckoutContainer } from 'wcstripe/blocks/checkout-sessions/checkout-container';
import { initializeUPEAppearance } from 'wcstripe/stripe-utils/upe-appearance';
import { getFontRulesFromPage } from 'wcstripe/styles/upe';

jest.mock( 'react', () => ( {
	...jest.requireActual( 'react' ),
	useState: jest.fn(),
} ) );

jest.mock(
	'@woocommerce/blocks-checkout',
	() => ( {
		StoreNotice: jest.fn( ( { children } ) => <div>{ children }</div> ),
		extensionCartUpdate: jest.fn().mockResolvedValue( {
			extensions: {
				'wc-stripe/checkout-session': {
					client_secret: 'test_secret',
					status: 'success',
				},
			},
		} ),
	} ),
	{ virtual: true }
);

jest.mock( '@stripe/react-stripe-js/checkout', () => ( {
	CheckoutElementsProvider: jest.fn( ( { children, ...props } ) => (
		<div { ...props }>{ children }</div>
	) ),
} ) );

jest.mock( 'wcstripe/blocks/checkout-sessions/checkout-form' );

jest.mock( 'wcstripe/stripe-utils' );

jest.mock( 'wcstripe/stripe-utils/upe-appearance' );

jest.mock( 'wcstripe/styles/upe' );

jest.mock( 'wcstripe/blocks/load-stripe', () => ( {
	loadStripe: jest.fn( () => Promise.resolve( true ) ),
} ) );

jest.mock( 'wcstripe/blocks/utils', () => ( {
	getBlocksConfiguration: jest.fn( () => ( { isAdmin: false } ) ),
} ) );

describe( 'CheckoutSessionsContainer', () => {
	const api = {
		checkoutSessionsCreateSession: jest.fn().mockResolvedValue( {
			data: { client_secret: 'test_secret' },
		} ),
	};
	const setShouldLoadStripeElements = jest.fn();
	let consoleErrorSpy;

	beforeEach( () => {
		consoleErrorSpy = jest
			.spyOn( console, 'error' )
			.mockImplementation( () => {} );
		initializeUPEAppearance.mockReturnValue( {} );
		getFontRulesFromPage.mockReturnValue( [] );
		useState.mockReturnValue( [ null, jest.fn() ] );
		extensionCartUpdate.mockClear();
		CheckoutElementsProvider.mockClear();
		setShouldLoadStripeElements.mockClear();
	} );

	afterEach( () => {
		consoleErrorSpy.mockRestore();
	} );

	it( 'initializes from the Checkout Session embedded in the Store API response', async () => {
		render(
			<CheckoutContainer
				api={ api }
				setShouldLoadStripeElements={ setShouldLoadStripeElements }
			/>
		);

		expect( CheckoutElementsProvider ).toHaveBeenCalledWith(
			expect.objectContaining( {
				stripe: expect.any( Promise ),
				options: expect.objectContaining( {
					elementsOptions: expect.objectContaining( {
						savedPaymentMethod: {
							enableRedisplay: 'never',
							enableSave: 'never',
						},
					} ),
				} ),
			} ),
			{}
		);
		expect( extensionCartUpdate ).toHaveBeenCalledWith( {
			namespace: 'wc-stripe/checkout-session',
			data: { action: 'sync' },
		} );
		expect( api.checkoutSessionsCreateSession ).not.toHaveBeenCalled();
		await expect(
			CheckoutElementsProvider.mock.calls[ 0 ][ 0 ].options.clientSecret
		).resolves.toBe( 'test_secret' );
	} );

	it( 'falls back when the Store API reports a synchronization error', async () => {
		extensionCartUpdate.mockResolvedValueOnce( {
			extensions: {
				'wc-stripe/checkout-session': {
					client_secret: 'stale_secret',
					status: 'error',
				},
			},
		} );

		render(
			<CheckoutContainer
				api={ api }
				setShouldLoadStripeElements={ setShouldLoadStripeElements }
			/>
		);

		await expect(
			CheckoutElementsProvider.mock.calls[ 0 ][ 0 ].options.clientSecret
		).resolves.toBeNull();
		expect( setShouldLoadStripeElements ).toHaveBeenCalledWith( true );
		expect( consoleErrorSpy ).toHaveBeenCalled();
	} );

	it( 'falls back when the Store API request rejects', async () => {
		const requestError = new Error( 'Network request failed' );
		extensionCartUpdate.mockRejectedValueOnce( requestError );

		render(
			<CheckoutContainer
				api={ api }
				setShouldLoadStripeElements={ setShouldLoadStripeElements }
			/>
		);

		await expect(
			CheckoutElementsProvider.mock.calls[ 0 ][ 0 ].options.clientSecret
		).resolves.toBeNull();
		expect( setShouldLoadStripeElements ).toHaveBeenCalledWith( true );
		expect( consoleErrorSpy ).toHaveBeenCalledWith(
			expect.stringContaining(
				'Unable to initialize a checkout session'
			),
			requestError
		);
	} );
} );
