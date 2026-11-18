import { extensionCartUpdate } from '@woocommerce/blocks-checkout';
import { act, render } from '@testing-library/react';
import { useEffect } from 'react';
import { CheckoutElementsProvider } from '@stripe/react-stripe-js/checkout';
import { CheckoutContainer } from 'wcstripe/blocks/checkout-sessions/checkout-container';
import { initializeUPEAppearance } from 'wcstripe/stripe-utils/upe-appearance';
import { getFontRulesFromPage } from 'wcstripe/styles/upe';

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

	describe( 'with a session embedded in the cart data', () => {
		const INITIAL = {
			session_id: 'cs_test_initial',
			client_secret: 'test_secret',
		};
		const REPLACEMENT = {
			session_id: 'cs_test_replacement',
			client_secret: 'replacement_secret',
		};
		const withSession = ( session ) => ( {
			extensions: {
				'wc-stripe/checkout-session': { status: 'success', ...session },
			},
		} );
		const renderContainer = ( session ) => (
			<CheckoutContainer
				api={ api }
				setShouldLoadStripeElements={ setShouldLoadStripeElements }
				cartData={ withSession( session ) }
			/>
		);
		let providerMounts;

		beforeEach( () => {
			providerMounts = 0;
			// Counting mounts proves the provider is remounted, as it can't switch client secrets.
			CheckoutElementsProvider.mockImplementation( ( { children } ) => {
				// eslint-disable-next-line react-hooks/rules-of-hooks
				useEffect( () => void ( providerMounts += 1 ), [] );
				return children;
			} );
			extensionCartUpdate.mockResolvedValueOnce( withSession( INITIAL ) );
		} );

		afterEach( () => {
			CheckoutElementsProvider.mockImplementation(
				( { children, ...props } ) => (
					<div { ...props }>{ children }</div>
				)
			);
		} );

		it.each( [
			[ 'a later cart response', INITIAL, REPLACEMENT, 2 ],
			[
				'the cart before the initial sync settles',
				REPLACEMENT,
				REPLACEMENT,
				2,
			],
			[ 'the same session', INITIAL, INITIAL, 1 ],
			[
				'a failed synchronization',
				INITIAL,
				{ ...REPLACEMENT, status: 'error' },
				1,
			],
			[
				'a session without a client secret',
				INITIAL,
				{ ...REPLACEMENT, client_secret: '' },
				1,
			],
		] )(
			'mounts the right session for %s',
			async ( _label, renderedSession, laterSession, expectedMounts ) => {
				const view = render( renderContainer( renderedSession ) );
				await act( async () => {
					await CheckoutElementsProvider.mock.calls[ 0 ][ 0 ].options
						.clientSecret;
				} );
				await act( async () => {
					view.rerender( renderContainer( laterSession ) );
				} );

				expect( providerMounts ).toBe( expectedMounts );
				// The provider accepts the client secret as a string or a promise.
				await expect(
					Promise.resolve(
						CheckoutElementsProvider.mock.calls.at( -1 )[ 0 ]
							.options.clientSecret
					)
				).resolves.toBe(
					expectedMounts === 2
						? REPLACEMENT.client_secret
						: INITIAL.client_secret
				);
			}
		);
	} );

	it( 'ignores a sync failure that completes after unmount', async () => {
		let rejectUpdate;
		extensionCartUpdate.mockImplementationOnce(
			() =>
				new Promise( ( resolve, reject ) => {
					rejectUpdate = reject;
				} )
		);

		const { unmount } = render(
			<CheckoutContainer
				api={ api }
				setShouldLoadStripeElements={ setShouldLoadStripeElements }
			/>
		);

		unmount();
		rejectUpdate( new Error( 'Network request failed' ) );
		// Give the in-flight synchronization a chance to observe the rejection.
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( setShouldLoadStripeElements ).not.toHaveBeenCalled();
		expect( consoleErrorSpy ).not.toHaveBeenCalled();
	} );
} );
