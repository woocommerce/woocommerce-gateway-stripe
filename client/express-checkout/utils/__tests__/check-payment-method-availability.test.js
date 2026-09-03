import { createRoot } from 'react-dom/client';
import { checkPaymentMethodIsAvailable } from 'wcstripe/express-checkout/utils/check-payment-method-availability';
import { getExpressCheckoutData } from 'wcstripe/express-checkout/utils';

jest.mock( 'react-dom/client', () => ( {
	createRoot: jest.fn(),
} ) );

jest.mock( 'wcstripe/express-checkout/utils', () => ( {
	getExpressCheckoutData: jest.fn(),
	getPaymentMethodTypesForExpressMethod: jest.fn( () => [ 'card' ] ),
	isManualPaymentMethodCreation: jest.fn( () => false ),
} ) );

describe( 'checkPaymentMethodIsAvailable', () => {
	const api = { loadStripe: jest.fn( () => Promise.resolve( {} ) ) };

	const render = jest.fn();
	const unmount = jest.fn();

	const hydratedCart = ( currencyCode = 'USD' ) => ( {
		cartTotals: {
			total_price: '1000',
			currency_minor_unit: 2,
			currency_code: currencyCode,
		},
	} );

	// The default cart totals @woocommerce/block-data seeds before the Store
	// API response is applied.
	const unhydratedCart = () => ( {
		cartTotals: {
			total_price: '0',
			currency_minor_unit: 2,
			currency_code: '',
		},
	} );

	// The rendered tree is ProbeErrorBoundary > Elements > ExpressCheckoutElement.
	const getRenderedErrorBoundary = ( call = 0 ) =>
		render.mock.calls[ call ][ 0 ];
	const getRenderedElements = ( call = 0 ) =>
		getRenderedErrorBoundary( call ).props.children;
	const getRenderedExpressCheckoutElement = ( call = 0 ) =>
		getRenderedElements( call ).props.children;

	beforeEach( () => {
		jest.clearAllMocks();
		createRoot.mockReturnValue( { render, unmount } );
		getExpressCheckoutData.mockReturnValue( false );
	} );

	it( 'resolves false without mounting a probe element when the cart currency is not hydrated', async () => {
		const result = await checkPaymentMethodIsAvailable(
			'amazonPay',
			api,
			unhydratedCart()
		);

		expect( result ).toBe( false );
		expect( createRoot ).not.toHaveBeenCalled();
	} );

	it( 'probes again once the cart hydrates instead of reusing the unhydrated result', async () => {
		const beforeHydration = await checkPaymentMethodIsAvailable(
			'applePay',
			api,
			unhydratedCart()
		);
		expect( beforeHydration ).toBe( false );
		expect( createRoot ).not.toHaveBeenCalled();

		const afterHydration = checkPaymentMethodIsAvailable(
			'applePay',
			api,
			hydratedCart()
		);
		expect( createRoot ).toHaveBeenCalledTimes( 1 );
		expect( getRenderedElements().props.options.currency ).toBe( 'usd' );

		getRenderedExpressCheckoutElement().props.onReady( {
			availablePaymentMethods: { applePay: true },
		} );

		expect( await afterHydration ).toBe( true );
		expect( unmount ).toHaveBeenCalled();
	} );

	it( 'memoizes the probe per payment method for hydrated carts', async () => {
		const first = checkPaymentMethodIsAvailable(
			'googlePay',
			api,
			hydratedCart()
		);
		getRenderedExpressCheckoutElement().props.onReady( {
			availablePaymentMethods: { googlePay: true },
		} );

		const second = checkPaymentMethodIsAvailable(
			'googlePay',
			api,
			hydratedCart()
		);

		expect( createRoot ).toHaveBeenCalledTimes( 1 );
		expect( await first ).toBe( true );
		expect( await second ).toBe( true );
	} );

	it( 'resolves false when the ready event does not report the method as available', async () => {
		const result = checkPaymentMethodIsAvailable(
			'link',
			api,
			hydratedCart()
		);

		getRenderedExpressCheckoutElement().props.onReady( {
			availablePaymentMethods: { link: false },
		} );

		expect( await result ).toBe( false );
	} );

	it( 'resolves false on probe failures without caching them, so later calls probe again', async () => {
		// Mount error caught by the error boundary.
		const first = checkPaymentMethodIsAvailable(
			'amazonPay',
			api,
			hydratedCart()
		);
		getRenderedErrorBoundary().props.onError();
		expect( await first ).toBe( false );

		// The failure was evicted, so this call probes again; this time the
		// element reports a load error.
		const second = checkPaymentMethodIsAvailable(
			'amazonPay',
			api,
			hydratedCart()
		);
		expect( createRoot ).toHaveBeenCalledTimes( 2 );
		getRenderedExpressCheckoutElement( 1 ).props.onLoadError();
		expect( await second ).toBe( false );

		checkPaymentMethodIsAvailable( 'amazonPay', api, hydratedCart() );
		expect( createRoot ).toHaveBeenCalledTimes( 3 );

		// Failed probes tear down their container on a deferred tick.
		await new Promise( ( resolve ) => setTimeout( resolve ) );
		expect( unmount ).toHaveBeenCalled();
	} );
} );
