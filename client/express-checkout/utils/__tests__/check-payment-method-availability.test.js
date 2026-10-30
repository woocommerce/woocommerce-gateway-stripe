import { createRoot } from 'react-dom/client';
import { checkPaymentMethodIsAvailable } from 'wcstripe/express-checkout/utils/check-payment-method-availability';

jest.mock( 'react-dom/client', () => ( {
	createRoot: jest.fn(),
} ) );

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

	it( 'shares one in-flight probe and returns each wallet’s own availability', async () => {
		const results = checkWallets();
		expect( createRoot ).toHaveBeenCalledTimes( 1 );
		expect( api.loadStripe ).toHaveBeenCalledTimes( 1 );
		expect( getRenderedElements().props.options ).toEqual( {
			mode: 'payment',
			paymentMethodCreation: 'manual',
			amount: 1000,
			currency: 'usd',
			paymentMethodTypes: [ 'card', 'link' ],
		} );
		expect(
			getRenderedExpressCheckoutElement().props.options.paymentMethods
		).toEqual( {
			applePay: 'always',
			googlePay: 'always',
			link: 'auto',
			amazonPay: 'never',
			paypal: 'never',
		} );

		ready( { applePay: true, googlePay: false, link: true } );
		expect( await Promise.all( results ) ).toEqual( [ true, false, true ] );
		expect( await Promise.all( checkWallets() ) ).toEqual( [
			true,
			false,
			true,
		] );
		expect( createRoot ).toHaveBeenCalledTimes( 1 );
		await new Promise( ( resolve ) => setTimeout( resolve ) );
		expect( unmount ).toHaveBeenCalledTimes( 1 );
		expect( document.body ).toBeEmptyDOMElement();
	} );

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
		expect(
			getRenderedElements().props.options.paymentMethodTypes
		).toEqual( method === 'link' ? [ 'card' ] : [ 'card', 'link' ] );
		ready( { [ enabledMethod ]: true } );
		expect( await result ).toBe( true );
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

	it.each( [
		[ 'currency', () => hydratedCart( { currency_code: 'EUR' } ) ],
		[ 'amount', () => hydratedCart( { total_price: '2000' } ) ],
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

			const second = checkWallets();
			expect( createRoot ).toHaveBeenCalledTimes( 2 );
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
} );
