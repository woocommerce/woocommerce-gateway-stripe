import { createRoot } from 'react-dom/client';
import {
	checkPaymentMethodIsAvailable,
	preloadPaymentMethodAvailability,
} from 'wcstripe/express-checkout/utils/check-payment-method-availability';

jest.mock( 'react-dom/client', () => ( {
	createRoot: jest.fn(),
} ) );

// Babel's timer polyfill captures native timers before Jest installs fake ones.
jest.mock(
	'@babel/runtime-corejs3/core-js-stable/set-timeout',
	() =>
		( ...args ) =>
			Reflect.get( global, 'setTimeout' )( ...args )
);

describe( 'checkPaymentMethodIsAvailable', () => {
	let api;
	const render = jest.fn();
	const unmount = jest.fn();
	const wallets = [ 'applePay', 'googlePay', 'link' ];

	const hydratedCart = ( totals = {} ) => ( {
		cartTotals: {
			total_price: '1000',
			currency_minor_unit: 2,
			currency_code: 'USD',
			...totals,
		},
	} );

	const getRenderedErrorBoundary = ( call = 0 ) =>
		render.mock.calls[ call ][ 0 ];
	const getRenderedElements = ( call = 0 ) =>
		getRenderedErrorBoundary( call ).props.children;
	const getRenderedExpressCheckoutElement = ( call = 0 ) =>
		getRenderedElements( call ).props.children;
	const ready = ( availablePaymentMethods, call = 0 ) =>
		getRenderedExpressCheckoutElement( call ).props.onReady( {
			availablePaymentMethods,
		} );
	const checkWallets = ( cart = hydratedCart() ) =>
		wallets.map( ( method ) =>
			checkPaymentMethodIsAvailable( method, api, cart )
		);

	beforeEach( () => {
		jest.clearAllMocks();
		api = { loadStripe: jest.fn( () => Promise.resolve( {} ) ) };
		createRoot.mockReturnValue( { render, unmount } );
		global.wcSettings = {
			stripe_data: {
				isApplePayEnabled: true,
				isGooglePayEnabled: true,
				isLinkEnabled: true,
				isAmazonPayEnabled: true,
				paymentMethodsConfig: { card: {}, link: {} },
			},
		};
		global.wc_stripe_express_checkout_params = {
			has_free_trial: false,
			stripe: { is_amazon_pay_enabled: true },
		};
	} );

	afterEach( async () => {
		await new Promise( ( resolve ) => setTimeout( resolve ) );
		delete global.wcSettings;
		global.wc_stripe_express_checkout_params = {};
		document.body.innerHTML = '';
	} );

	it.each( [ undefined, {}, hydratedCart( { currency_code: '' } ) ] )(
		'waits for cart hydration without caching an unavailable result (%j)',
		async ( cart ) => {
			expect(
				await checkPaymentMethodIsAvailable( 'applePay', api, cart )
			).toBe( false );
			expect( createRoot ).not.toHaveBeenCalled();

			const results = checkWallets();
			expect( createRoot ).toHaveBeenCalledTimes( 1 );
			ready( { applePay: true, googlePay: true, link: true } );
			expect( await Promise.all( results ) ).toEqual( [
				true,
				true,
				true,
			] );
		}
	);

	it( 'returns initialization errors through the eligibility promise', async () => {
		await expect(
			checkPaymentMethodIsAvailable(
				'applePay',
				api,
				hydratedCart( { currency_code: 123 } )
			)
		).rejects.toThrow( TypeError );
	} );

	it.each( [ 'loader', 'ready event' ] )(
		'releases a probe stalled on the %s and permits a shared retry',
		async ( stalledAt ) => {
			jest.useFakeTimers();
			try {
				if ( stalledAt === 'loader' ) {
					api.loadStripe.mockReturnValueOnce(
						new Promise( () => {} )
					);
				}
				const completed = jest.fn();
				const result = Promise.all( checkWallets() ).then( completed );
				expect( jest.getTimerCount() ).toBe( 1 );
				await jest.advanceTimersByTimeAsync( 14999 );
				expect( completed ).not.toHaveBeenCalled();
				expect( createRoot ).toHaveBeenCalledTimes( 1 );
				await jest.advanceTimersByTimeAsync( 1 );
				// The assertions observe settlement without hanging on an unbounded promise.
				expect( completed ).toHaveBeenCalledWith( [
					false,
					false,
					false,
				] );
				await result;
				await jest.advanceTimersByTimeAsync( 1 );
				expect( unmount ).toHaveBeenCalledTimes( 1 );
				expect( document.body ).toBeEmptyDOMElement();
				const retry = checkWallets();
				expect( createRoot ).toHaveBeenCalledTimes( 2 );
				ready( { applePay: true, googlePay: true, link: true } );
				getRenderedExpressCheckoutElement().props.onLoadError();
				ready( { applePay: true, googlePay: true, link: true }, 1 );
				expect( await Promise.all( retry ) ).toEqual( [
					true,
					true,
					true,
				] );
				expect( await Promise.all( checkWallets() ) ).toEqual( [
					true,
					true,
					true,
				] );
				expect( createRoot ).toHaveBeenCalledTimes( 2 );
				await jest.advanceTimersByTimeAsync( 1 );
				expect( jest.getTimerCount() ).toBe( 0 );
				expect( unmount ).toHaveBeenCalledTimes( 2 );
			} finally {
				jest.useRealTimers();
			}
		}
	);

	it.each( [
		[
			{ applePay: true, googlePay: false, link: true },
			[ true, false, true ],
		],
		[
			{ applePay: true, googlePay: true, link: false },
			[ true, true, false ],
		],
		[ { applePay: true, googlePay: true }, [ true, true, false ] ],
	] )(
		'shares card-wallet availability across pending and completed amount updates (%j)',
		async ( available, expected ) => {
			const results = checkWallets();
			const pendingUpdate = checkWallets(
				hydratedCart( { total_price: '2000' } )
			);
			expect( createRoot ).toHaveBeenCalledTimes( 1 );
			expect( api.loadStripe ).toHaveBeenCalledTimes( 1 );
			expect( getRenderedElements().props.options ).toEqual( {
				mode: 'payment',
				paymentMethodCreation: 'manual',
				amount: 1000,
				currency: 'usd',
			} );
			expect(
				getRenderedExpressCheckoutElement().props.options.paymentMethods
			).toEqual( {
				applePay: 'always',
				googlePay: 'always',
				link: 'auto',
				amazonPay: 'never',
				paypal: 'never',
				klarna: 'never',
			} );

			ready( available );
			expect( await Promise.all( results ) ).toEqual( expected );
			expect( await Promise.all( pendingUpdate ) ).toEqual( expected );
			expect(
				await Promise.all(
					checkWallets( hydratedCart( { total_price: '3000' } ) )
				)
			).toEqual( expected );
			expect( await Promise.all( checkWallets() ) ).toEqual( expected );
			expect( createRoot ).toHaveBeenCalledTimes( 1 );
			await new Promise( ( resolve ) => setTimeout( resolve ) );
			expect( unmount ).toHaveBeenCalledTimes( 1 );
			expect( document.body ).toBeEmptyDOMElement();
		}
	);

	it.each( [ undefined, null, {}, { applePay: false } ] )(
		'caches a successful probe with unavailable or missing wallets (%j)',
		async ( available ) => {
			const results = checkWallets();
			ready( available );
			expect( await Promise.all( results ) ).toEqual( [
				false,
				false,
				false,
			] );
			expect( await Promise.all( checkWallets() ) ).toEqual( [
				false,
				false,
				false,
			] );
			expect( createRoot ).toHaveBeenCalledTimes( 1 );
		}
	);

	it.each( [
		[ 'applePay', 'isApplePayEnabled' ],
		[ 'googlePay', 'isGooglePayEnabled' ],
		[ 'link', 'isLinkEnabled' ],
		[ 'amazonPay', 'isAmazonPayEnabled' ],
	] )( 'does not probe disabled %s', async ( method, flag ) => {
		global.wcSettings.stripe_data[ flag ] = false;
		expect(
			await checkPaymentMethodIsAvailable( method, api, hydratedCart() )
		).toBe( false );
		expect( createRoot ).not.toHaveBeenCalled();

		const enabledMethod = method === 'applePay' ? 'googlePay' : 'applePay';
		const result = checkPaymentMethodIsAvailable(
			enabledMethod,
			api,
			hydratedCart()
		);
		expect(
			getRenderedExpressCheckoutElement().props.options.paymentMethods[
				method
			]
		).toBe( 'never' );
		expect( getRenderedElements().props.options ).not.toHaveProperty(
			'paymentMethodTypes'
		);
		ready( { [ enabledMethod ]: true } );
		expect( await result ).toBe( true );
	} );

	it( 'does not inspect unhydrated amounts for a disabled wallet', async () => {
		global.wcSettings.stripe_data.isGooglePayEnabled = false;
		const cart = hydratedCart();
		Object.defineProperty( cart.cartTotals, 'total_price', {
			get: () => {
				throw new Error( 'Cart amount is not ready' );
			},
		} );
		await expect(
			checkPaymentMethodIsAvailable( 'googlePay', api, cart )
		).resolves.toBe( false );
		expect( createRoot ).not.toHaveBeenCalled();
	} );

	it.each( [ false, true ] )(
		'keeps Amazon Pay separate with its own Elements options (free trial: %s)',
		async ( hasFreeTrial ) => {
			global.wc_stripe_express_checkout_params.has_free_trial =
				hasFreeTrial;
			const cart = hydratedCart( {
				total_price: hasFreeTrial ? '0' : '1000',
			} );
			const results = checkWallets( cart );
			const amazon = checkPaymentMethodIsAvailable(
				'amazonPay',
				api,
				cart
			);
			expect( createRoot ).toHaveBeenCalledTimes( 2 );
			expect( getRenderedElements( 1 ).props.options ).toEqual( {
				mode: hasFreeTrial ? 'subscription' : 'payment',
				...( hasFreeTrial && { paymentMethodCreation: 'manual' } ),
				amount: hasFreeTrial ? 0 : 1000,
				currency: 'usd',
				paymentMethodTypes: [ 'amazon_pay' ],
			} );
			expect(
				getRenderedExpressCheckoutElement( 1 ).props.options
					.paymentMethods
			).toEqual( {
				amazonPay: 'auto',
				applePay: 'never',
				googlePay: 'never',
				link: 'never',
				paypal: 'never',
				klarna: 'never',
			} );
			expect( getRenderedElements().props.options ).toMatchObject( {
				mode: hasFreeTrial ? 'subscription' : 'payment',
				paymentMethodCreation: 'manual',
				amount: hasFreeTrial ? 0 : 1000,
			} );
			ready( { applePay: true, googlePay: true, link: true } );
			ready( { amazonPay: false }, 1 );
			expect( await Promise.all( results ) ).toEqual( [
				true,
				true,
				true,
			] );
			expect( await amazon ).toBe( false );
		}
	);

	it( 'reprobes Amazon Pay when the amount changes', async () => {
		const first = checkPaymentMethodIsAvailable(
			'amazonPay',
			api,
			hydratedCart()
		);
		ready( { amazonPay: true } );
		expect( await first ).toBe( true );

		const second = checkPaymentMethodIsAvailable(
			'amazonPay',
			api,
			hydratedCart( { total_price: '2000' } )
		);
		expect( createRoot ).toHaveBeenCalledTimes( 2 );
		expect( getRenderedElements( 1 ).props.options.amount ).toBe( 2000 );
		ready( { amazonPay: false }, 1 );
		expect( await second ).toBe( false );
	} );

	it( 'bounds Amazon amount history without evicting the shared card-wallet result', async () => {
		const cards = checkWallets();
		ready( { applePay: true, googlePay: true, link: true } );
		await Promise.all( cards );
		for ( let amount = 1; amount <= 21; amount++ ) {
			const result = checkPaymentMethodIsAvailable(
				'amazonPay',
				api,
				hydratedCart( { total_price: String( amount ) } )
			);
			ready( { amazonPay: true }, render.mock.calls.length - 1 );
			expect( await result ).toBe( true );
		}
		const renders = render.mock.calls.length;
		expect(
			await checkPaymentMethodIsAvailable(
				'amazonPay',
				api,
				hydratedCart( { total_price: '21' } )
			)
		).toBe( true );
		expect( await Promise.all( checkWallets() ) ).toEqual( [
			true,
			true,
			true,
		] );
		expect( render ).toHaveBeenCalledTimes( renders );
		const oldAmount = checkPaymentMethodIsAvailable(
			'amazonPay',
			api,
			hydratedCart( { total_price: '1' } )
		);
		expect( render ).toHaveBeenCalledTimes( renders + 1 );
		ready( { amazonPay: false }, renders );
		expect( await oldAmount ).toBe( false );
	} );

	it( 'does not let an evicted Amazon failure delete a replacement probe', async () => {
		const cart = hydratedCart( { total_price: '1' } );
		const evicted = checkPaymentMethodIsAvailable( 'amazonPay', api, cart );
		for ( let amount = 2; amount <= 21; amount++ ) {
			const result = checkPaymentMethodIsAvailable(
				'amazonPay',
				api,
				hydratedCart( { total_price: String( amount ) } )
			);
			ready( { amazonPay: true }, render.mock.calls.length - 1 );
			await result;
		}
		const replacement = checkPaymentMethodIsAvailable(
			'amazonPay',
			api,
			cart
		);
		expect( render ).toHaveBeenCalledTimes( 22 );
		getRenderedExpressCheckoutElement().props.onLoadError();
		expect( await evicted ).toBe( false );
		const shared = checkPaymentMethodIsAvailable( 'amazonPay', api, cart );
		expect( render ).toHaveBeenCalledTimes( 22 );
		ready( { amazonPay: true }, 21 );
		expect( await Promise.all( [ replacement, shared ] ) ).toEqual( [
			true,
			true,
		] );
	} );

	it.each( [
		[ 'currency', () => hydratedCart( { currency_code: 'EUR' } ) ],
		[
			'mode',
			() => {
				global.wc_stripe_express_checkout_params.has_free_trial = true;
				return hydratedCart();
			},
		],
		[
			'enabled wallets',
			() => {
				global.wcSettings.stripe_data.isLinkEnabled = false;
				return hydratedCart();
			},
		],
	] )( 'reprobes when %s changes', async ( _name, changeContext ) => {
		const first = checkPaymentMethodIsAvailable(
			'applePay',
			api,
			hydratedCart()
		);
		ready( { applePay: true } );
		expect( await first ).toBe( true );
		const second = checkPaymentMethodIsAvailable(
			'applePay',
			api,
			changeContext()
		);
		expect( createRoot ).toHaveBeenCalledTimes( 2 );
		ready( { applePay: false }, 1 );
		expect( await second ).toBe( false );
	} );

	it( 'does not share results between API instances', async () => {
		const first = checkPaymentMethodIsAvailable(
			'applePay',
			api,
			hydratedCart()
		);
		const second = checkPaymentMethodIsAvailable(
			'applePay',
			{ loadStripe: api.loadStripe },
			hydratedCart()
		);
		expect( createRoot ).toHaveBeenCalledTimes( 2 );
		ready( { applePay: true } );
		ready( { applePay: false }, 1 );
		expect( await Promise.all( [ first, second ] ) ).toEqual( [
			true,
			false,
		] );
	} );

	it( 'normalizes the amount using the store’s currency precision', async () => {
		global.wc_stripe_express_checkout_params.checkout = {
			currency_decimals: 0,
		};
		const result = checkPaymentMethodIsAvailable(
			'applePay',
			api,
			hydratedCart( { currency_code: 'JPY' } )
		);
		expect( getRenderedElements().props.options ).toMatchObject( {
			amount: 10,
			currency: 'jpy',
		} );
		ready( { applePay: true } );
		expect( await result ).toBe( true );
	} );

	it.each( [ 'render', 'load' ] )(
		'releases all waiting wallets after a %s error and allows a shared retry',
		async ( errorType ) => {
			const first = checkWallets();
			const fail =
				errorType === 'render'
					? getRenderedErrorBoundary().props.onError
					: getRenderedExpressCheckoutElement().props.onLoadError;
			fail();
			expect( await Promise.all( first ) ).toEqual( [
				false,
				false,
				false,
			] );

			const second = checkWallets(
				hydratedCart( { total_price: '2000' } )
			);
			expect( createRoot ).toHaveBeenCalledTimes( 2 );
			expect( getRenderedElements( 1 ).props.options.amount ).toBe(
				2000
			);
			fail();
			ready( { applePay: false } );
			ready( { applePay: true, googlePay: true, link: true }, 1 );
			expect( await Promise.all( second ) ).toEqual( [
				true,
				true,
				true,
			] );
			await Promise.all( checkWallets() );
			expect( createRoot ).toHaveBeenCalledTimes( 2 );
			await new Promise( ( resolve ) => setTimeout( resolve ) );
			expect( unmount ).toHaveBeenCalledTimes( 2 );
			expect( document.body ).toBeEmptyDOMElement();
		}
	);

	it.each( [ 'missing', 'error payload', 'rejection' ] )(
		'releases waiting wallets when Stripe loading returns %s and allows a retry',
		async ( failure ) => {
			const loaded =
				failure === 'rejection'
					? Promise.reject( new Error( 'Stripe could not load' ) )
					: Promise.resolve(
							failure === 'missing'
								? null
								: {
										error: new Error(
											'Stripe could not load'
										),
								  }
					  );
			// The renderer double does not consume the provider promise.
			loaded.catch( () => {} );
			api.loadStripe.mockReturnValueOnce( loaded );
			let availability;
			Promise.all( checkWallets() ).then( ( result ) => {
				availability = result;
			} );
			await new Promise( ( resolve ) => setTimeout( resolve ) );
			expect( availability ).toEqual( [ false, false, false ] );
			await new Promise( ( resolve ) => setTimeout( resolve ) );
			expect( document.body ).toBeEmptyDOMElement();

			const retry = checkWallets();
			ready( { applePay: true, googlePay: true, link: true }, 1 );
			expect( await Promise.all( retry ) ).toEqual( [
				true,
				true,
				true,
			] );
			expect( createRoot ).toHaveBeenCalledTimes( 2 );
		}
	);

	it( 'ignores a late load error after a successful ready event', async () => {
		const first = checkWallets();
		ready( { applePay: true, googlePay: true, link: true } );
		getRenderedExpressCheckoutElement().props.onLoadError();
		expect( await Promise.all( first ) ).toEqual( [ true, true, true ] );
		await Promise.all( checkWallets() );
		expect( createRoot ).toHaveBeenCalledTimes( 1 );
		await new Promise( ( resolve ) => setTimeout( resolve ) );
		expect( unmount ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'retries after a synchronous React root initialization failure', async () => {
		createRoot.mockImplementationOnce( () => {
			throw new Error( 'React root could not initialize' );
		} );
		const first = checkWallets();
		const failedContainer = createRoot.mock.calls[ 0 ][ 0 ];
		expect( createRoot ).toHaveBeenCalledTimes( 1 );
		expect( failedContainer ).toBeInTheDocument();
		expect( render ).not.toHaveBeenCalled();
		expect( api.loadStripe ).not.toHaveBeenCalled();
		await expect( Promise.all( first ) ).resolves.toEqual( [
			false,
			false,
			false,
		] );
		await new Promise( ( resolve ) => setTimeout( resolve ) );
		expect( failedContainer ).not.toBeInTheDocument();
		expect( unmount ).not.toHaveBeenCalled();

		const retry = checkWallets();
		const retryContainer = createRoot.mock.calls[ 1 ][ 0 ];
		ready( { applePay: true, googlePay: true, link: true } );
		await expect( Promise.all( retry ) ).resolves.toEqual( [
			true,
			true,
			true,
		] );
		await expect( Promise.all( checkWallets() ) ).resolves.toEqual( [
			true,
			true,
			true,
		] );
		expect( createRoot ).toHaveBeenCalledTimes( 2 );
		expect( api.loadStripe ).toHaveBeenCalledTimes( 1 );
		await new Promise( ( resolve ) => setTimeout( resolve ) );
		expect( unmount ).toHaveBeenCalledTimes( 1 );
		expect( retryContainer ).not.toBeInTheDocument();
	} );

	it( 'retries after a synchronous Stripe initialization failure', async () => {
		api.loadStripe.mockImplementationOnce( () => {
			throw new Error( 'Stripe could not initialize' );
		} );
		expect( await Promise.all( checkWallets() ) ).toEqual( [
			false,
			false,
			false,
		] );
		expect( createRoot ).toHaveBeenCalledTimes( 1 );

		const retry = checkWallets();
		ready( { applePay: true, googlePay: true, link: true } );
		expect( await Promise.all( retry ) ).toEqual( [ true, true, true ] );
		expect( createRoot ).toHaveBeenCalledTimes( 2 );
		await new Promise( ( resolve ) => setTimeout( resolve ) );
		expect( unmount ).toHaveBeenCalledTimes( 2 );
	} );

	describe( 'server cart preloading', () => {
		beforeEach( () => {
			Object.assign( global.wcSettings.stripe_data, {
				currency: 'USD',
				cartTotal: 1000,
				shouldShowExpressCheckoutButton: true,
				isAdmin: false,
			} );
		} );

		afterEach( () => {
			for ( let call = 0; call < render.mock.calls.length; call++ ) {
				ready( {}, call );
			}
		} );

		it( 'starts one shared probe before hydration without bypassing live cart guards', async () => {
			const preloaded = preloadPaymentMethodAvailability( api );
			const duplicate = preloadPaymentMethodAvailability( api );
			expect( createRoot ).toHaveBeenCalledTimes( 1 );
			expect( getRenderedElements().props.options ).toEqual( {
				mode: 'payment',
				paymentMethodCreation: 'manual',
				currency: 'usd',
				amount: 1000,
			} );
			expect(
				await checkPaymentMethodIsAvailable( 'googlePay', api, {} )
			).toBe( false );
			const hydrated = checkWallets();
			ready( { applePay: false, googlePay: true, link: true } );
			expect( await preloaded ).toBe( false );
			expect( await duplicate ).toBe( false );
			expect( await Promise.all( hydrated ) ).toEqual( [
				false,
				true,
				true,
			] );
			expect( createRoot ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'uses the server minor-unit amount without a second conversion', async () => {
			Object.assign( global.wcSettings.stripe_data, {
				currency: 'JPY',
				cartTotal: '10',
			} );
			global.wc_stripe_express_checkout_params.checkout = {
				currency_decimals: 0,
			};
			const preloaded = preloadPaymentMethodAvailability( api );
			expect( getRenderedElements().props.options.amount ).toBe( 10 );
			const hydrated = checkPaymentMethodIsAvailable(
				'applePay',
				api,
				hydratedCart( { currency_code: 'JPY' } )
			);
			ready( { applePay: true } );
			expect( await preloaded ).toBe( true );
			expect( await hydrated ).toBe( true );
			expect( createRoot ).toHaveBeenCalledTimes( 1 );
		} );

		it.each( [
			{ currency: undefined },
			{ currency: 'US' },
			{ cartTotal: undefined },
			{ cartTotal: null },
			{ cartTotal: '' },
			{ cartTotal: false },
			{ cartTotal: [] },
			{ cartTotal: 'invalid' },
			{ cartTotal: -1 },
			{ cartTotal: 1.5 },
			{ cartTotal: Infinity },
			{ cartTotal: 0 },
			{ isAdmin: true },
			{ shouldShowExpressCheckoutButton: false },
		] )( 'skips unsafe or unsupported server context %p', ( context ) => {
			Object.assign( global.wcSettings.stripe_data, context );
			expect( preloadPaymentMethodAvailability( api ) ).toBeUndefined();
			expect( createRoot ).not.toHaveBeenCalled();
		} );

		it( 'does not preload amount-sensitive Amazon Pay', () => {
			Object.assign( global.wcSettings.stripe_data, {
				isApplePayEnabled: false,
				isGooglePayEnabled: false,
				isLinkEnabled: false,
				isAmazonPayEnabled: true,
			} );
			expect( preloadPaymentMethodAvailability( api ) ).toBeUndefined();
			expect( createRoot ).not.toHaveBeenCalled();
		} );

		it( 'waits when Blocks settings have not loaded', () => {
			global.wcSettings.stripe_data = null;
			expect( preloadPaymentMethodAvailability( api ) ).toBeUndefined();
			expect( createRoot ).not.toHaveBeenCalled();
		} );

		it( 'keeps a zero-total free trial in subscription mode', async () => {
			global.wcSettings.stripe_data.cartTotal = 0;
			global.wc_stripe_express_checkout_params.has_free_trial = true;
			const preloaded = preloadPaymentMethodAvailability( api );
			expect( getRenderedElements().props.options ).toMatchObject( {
				amount: 0,
				mode: 'subscription',
			} );
			ready( { applePay: true } );
			expect( await preloaded ).toBe( true );
		} );

		it( 'rechecks when the live cart changes currency', async () => {
			const preloaded = preloadPaymentMethodAvailability( api );
			const hydrated = checkPaymentMethodIsAvailable(
				'applePay',
				api,
				hydratedCart( { currency_code: 'EUR' } )
			);
			expect( createRoot ).toHaveBeenCalledTimes( 2 );
			ready( { applePay: true } );
			ready( { applePay: false }, 1 );
			expect( await preloaded ).toBe( true );
			expect( await hydrated ).toBe( false );
		} );

		it( 'allows normal discovery after preloading fails', async () => {
			const preloaded = preloadPaymentMethodAvailability( api );
			getRenderedExpressCheckoutElement().props.onLoadError();
			expect( await preloaded ).toBe( false );
			const hydrated = checkPaymentMethodIsAvailable(
				'googlePay',
				api,
				hydratedCart()
			);
			expect( createRoot ).toHaveBeenCalledTimes( 2 );
			ready( { googlePay: true }, 1 );
			expect( await hydrated ).toBe( true );
		} );

		it( 'waits if the document body is not ready', () => {
			const body = document.body;
			body.remove();
			try {
				expect(
					preloadPaymentMethodAvailability( api )
				).toBeUndefined();
				expect( createRoot ).not.toHaveBeenCalled();
			} finally {
				document.documentElement.appendChild( body );
			}
		} );
	} );
} );
