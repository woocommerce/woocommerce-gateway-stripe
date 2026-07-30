// Mocking createRoot captures the rendered probe tree so tests can drive
// onReady/onLoadError without a real Stripe iframe.
jest.mock( 'react-dom/client', () => ( { createRoot: jest.fn() } ) );

describe( 'checkPaymentMethodIsAvailable', () => {
	let checkPaymentMethodIsAvailable;
	let createRoot;
	let renderedTrees;

	const cart = {
		cartTotals: {
			total_price: '1000',
			currency_minor_unit: 2,
			currency_code: 'USD',
		},
	};

	const api = { loadStripe: jest.fn( () => Promise.resolve( {} ) ) };

	// The rendered tree is <Elements><ExpressCheckoutElement /></Elements>.
	const expressCheckoutElementProps = ( tree ) => tree.props.children.props;

	beforeEach( () => {
		// The probe cache is module state; a fresh module registry per test
		// resets it. The createRoot mock must come from that same registry.
		jest.resetModules();
		// eslint-disable-next-line global-require
		( { createRoot } = require( 'react-dom/client' ) );
		( {
			checkPaymentMethodIsAvailable,
		} = require( 'wcstripe/express-checkout/utils/check-payment-method-availability' ) );

		renderedTrees = [];
		createRoot.mockReturnValue( {
			render: ( tree ) => renderedTrees.push( tree ),
			unmount: jest.fn(),
		} );

		global.wc_stripe_express_checkout_params = {
			stripe: { is_link_enabled: true },
			has_free_trial: false,
		};
	} );

	afterEach( () => {
		createRoot.mockReset();
		delete global.wc_stripe_express_checkout_params;
		document.body.innerHTML = '';
	} );

	it( 'answers Apple Pay and Google Pay from a single shared probe', async () => {
		const applePayPromise = checkPaymentMethodIsAvailable(
			'applePay',
			api,
			cart
		);
		const googlePayPromise = checkPaymentMethodIsAvailable(
			'googlePay',
			api,
			cart
		);

		// One hidden element mounted, not one per wallet.
		expect( renderedTrees ).toHaveLength( 1 );
		expect(
			expressCheckoutElementProps( renderedTrees[ 0 ] ).options
				.paymentMethods
		).toMatchObject( { applePay: 'always', googlePay: 'always' } );

		expressCheckoutElementProps( renderedTrees[ 0 ] ).onReady( {
			availablePaymentMethods: { applePay: true, googlePay: false },
		} );

		await expect( applePayPromise ).resolves.toBe( true );
		await expect( googlePayPromise ).resolves.toBe( false );
	} );

	it( 'gives Link its own probe with the link payment method enabled', async () => {
		checkPaymentMethodIsAvailable( 'applePay', api, cart );
		const linkPromise = checkPaymentMethodIsAvailable( 'link', api, cart );

		expect( renderedTrees ).toHaveLength( 2 );
		expect(
			expressCheckoutElementProps( renderedTrees[ 1 ] ).options
				.paymentMethods
		).toMatchObject( { link: 'auto', applePay: 'never' } );

		expressCheckoutElementProps( renderedTrees[ 1 ] ).onReady( {
			availablePaymentMethods: { link: true },
		} );

		await expect( linkPromise ).resolves.toBe( true );
	} );

	it( 'resolves to false when the probe fails to load', async () => {
		const promise = checkPaymentMethodIsAvailable( 'googlePay', api, cart );

		expressCheckoutElementProps( renderedTrees[ 0 ] ).onLoadError();

		await expect( promise ).resolves.toBe( false );
	} );

	it( 'resolves to false when the ready event reports no available methods', async () => {
		const promise = checkPaymentMethodIsAvailable( 'applePay', api, cart );

		expressCheckoutElementProps( renderedTrees[ 0 ] ).onReady( {} );

		await expect( promise ).resolves.toBe( false );
	} );
} );
