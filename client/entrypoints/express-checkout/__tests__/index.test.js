/**
 * Tests for the cart/checkout bootstrap path in the Express Checkout entrypoint.
 *
 * The entrypoint is an `jQuery( ( $ ) => { … } )` IIFE with no exported surface,
 * so these tests load it for its side effects and assert on the one observable
 * signal that distinguishes the bootstrap path from the legacy AJAX path: whether
 * `expressCheckoutGetCartDetails()` (the `GET /wc/store/v1/cart` fetch) fires.
 */

const mockGetCartDetails = jest.fn();
const mockGetStripe = jest.fn();
const mockAddToCart = jest.fn();
const mockEmptyCartLegacy = jest.fn();

// Run jQuery's ready callback synchronously instead of on a `setTimeout` macrotask,
// which raced the test's flush under CI load and bled into the next test. Other
// jQuery calls delegate to the real library so `.on()`/`.trigger()` still work.
jest.mock( 'jquery', () => {
	const actualJQuery = jest.requireActual( 'jquery' );
	const syncReadyJQuery = ( arg ) =>
		typeof arg === 'function'
			? arg( syncReadyJQuery )
			: actualJQuery( arg );
	return Object.assign( syncReadyJQuery, actualJQuery );
} );

// Stub the API so we can spy on the cart-details fetch without hitting the network.
jest.mock( '../../../api', () =>
	jest.fn().mockImplementation( () => ( {
		expressCheckoutGetCartDetails: mockGetCartDetails,
		getStripe: mockGetStripe,
		expressCheckoutAddToCart: mockAddToCart,
		expressCheckoutEmptyCartLegacy: mockEmptyCartLegacy,
	} ) )
);

// Transformers are exercised elsewhere; stub them so the init() flow stays
// focused on the fetch-vs-snapshot branch and never depends on cart shape.
jest.mock( 'wcstripe/express-checkout/transformers/wc-to-stripe', () => ( {
	transformCartDataForDisplayItems: jest.fn( () => [] ),
	transformLabeledDisplayItems: jest.fn( () => [] ),
	transformPrice: jest.fn( () => 1500 ),
} ) );

// Side-effect-only compatibility shims attach jQuery handlers we don't drive here.
jest.mock(
	'wcstripe/express-checkout/compatibility/wc-order-attribution',
	() => ( {} )
);
jest.mock(
	'wcstripe/express-checkout/compatibility/classic-checkout-custom-fields',
	() => ( {} )
);
jest.mock(
	'wcstripe/express-checkout/compatibility/wc-product-page',
	() => ( {} )
);

const baseParams = () => ( {
	has_block: false,
	is_pay_for_order: false,
	is_product_page: false,
	is_cart_page: true,
	is_change_payment_method: false,
	stripe: { publishable_key: 'pk_test_123', locale: 'en' },
	ajax_url: 'https://example.test/?wc-ajax=%%endpoint%%',
	checkout: { currency_code: 'usd', needs_payer_phone: false },
} );

// Load the entrypoint into the current module registry so the test and the
// entrypoint share one jQuery instance (jQuery stores event handlers per copy,
// so triggers must come from the same instance the entrypoint bound them on).
// With the synchronous-ready mock above, requiring it runs the bootstrap inline.
const loadEntrypoint = () => {
	require( '../index.js' );
};

describe( 'Express Checkout cart/checkout bootstrap', () => {
	beforeEach( () => {
		// Reset module state so the consume-once `cartBootstrapConsumed` flag
		// starts fresh for every test.
		jest.resetModules();
		mockGetCartDetails.mockReset();
		mockGetCartDetails.mockResolvedValue( {
			totals: { total_price: '1500', total_refund: '0' },
			needs_shipping: false,
		} );
		document.body.innerHTML =
			'<div id="wc-stripe-express-checkout-element"></div>';
	} );

	afterEach( () => {
		// Each test reloads the entrypoint under a fresh module registry
		// (`jest.resetModules`), so a later test's trigger can only reach its own
		// jQuery copy's handlers — no need to detach the previous test's bindings.
		delete global.wc_stripe_express_checkout_params;
	} );

	it( 'renders from the localized snapshot and skips the cart-details fetch on first paint', () => {
		global.wc_stripe_express_checkout_params = {
			...baseParams(),
			cart: {
				total: 1500,
				currency: 'usd',
				requestShipping: false,
				requestPhone: false,
				displayItems: [],
			},
		};

		loadEntrypoint();

		expect( mockGetCartDetails ).not.toHaveBeenCalled();
	} );

	it( 'falls back to the cart-details fetch on re-init once the snapshot is consumed', () => {
		global.wc_stripe_express_checkout_params = {
			...baseParams(),
			cart: {
				total: 1500,
				currency: 'usd',
				requestShipping: false,
				requestPhone: false,
				displayItems: [],
			},
		};

		loadEntrypoint();
		expect( mockGetCartDetails ).not.toHaveBeenCalled();

		// A live cart mutation re-runs init(); the consume-once flag now routes it
		// through the AJAX path instead of reusing the stale snapshot.
		// eslint-disable-next-line global-require
		require( 'jquery' )( document.body ).trigger( 'updated_cart_totals' );

		expect( mockGetCartDetails ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'fetches cart details on first paint when no snapshot is localized (develop parity)', () => {
		global.wc_stripe_express_checkout_params = {
			...baseParams(),
			cart: null,
		};

		loadEntrypoint();

		expect( mockGetCartDetails ).toHaveBeenCalledTimes( 1 );
	} );
} );

describe( 'Express Checkout product page variation breakdown', () => {
	const productParams = () => ( {
		...baseParams(),
		is_product_page: true,
		is_cart_page: false,
		stripe: {
			publishable_key: 'pk_test_123',
			locale: 'en',
			is_express_checkout_enabled: true,
			is_apple_pay_enabled: true,
			is_google_pay_enabled: true,
		},
		product: {
			total: { amount: 1000 },
			currency: 'usd',
			requestShipping: false,
			requestPhone: false,
			// Initial paint: red variation ($10), the default selection.
			displayItems: [ { label: 'Red variation', amount: 1000 } ],
		},
	} );

	// Stripe button stub that captures the bound event handlers so the test can
	// invoke the click handler directly. Each express type (Apple Pay, Google
	// Pay, …) gets its own Elements group, so `elementsList` collects them all to
	// assert the amount is pushed to every group.
	const stubStripeButton = () => {
		const handlers = {};
		const elementsList = [];
		const button = {
			on: ( evt, cb ) => {
				handlers[ evt ] = cb;
				return button;
			},
			mount: jest.fn(),
		};
		mockGetStripe.mockReturnValue( {
			elements: jest.fn( () => {
				const elements = {
					create: jest.fn( () => button ),
					update: jest.fn(),
				};
				elementsList.push( elements );
				return elements;
			} ),
		} );
		return { handlers, elementsList };
	};

	beforeEach( () => {
		jest.resetModules();
		[ mockGetStripe, mockAddToCart, mockEmptyCartLegacy ].forEach( ( m ) =>
			m.mockReset()
		);

		// The BlockUI plugin (`.block()`/`.unblock()`) isn't loaded in jsdom.
		// eslint-disable-next-line global-require
		const jq = require( 'jquery' );
		jq.fn.block = function () {
			return this;
		};
		jq.fn.unblock = function () {
			return this;
		};

		document.body.innerHTML = `
			<div id="wc-stripe-express-checkout-element"></div>
			<form class="variations_form cart">
				<table class="variations"><tbody><tr><td class="value">
					<select name="attribute_color" data-attribute_name="attribute_color">
						<option value="blue" selected>blue</option>
					</select>
				</td></tr></tbody></table>
				<div class="single_variation_wrap">
					<div class="quantity"><input type="number" class="qty" name="quantity" value="1" /></div>
					<button type="submit" class="single_add_to_cart_button" value="1048">Add</button>
					<input type="hidden" name="product_id" value="1048" />
					<input type="hidden" name="variation_id" class="variation_id" value="123" />
				</div>
			</form>`;
	} );

	afterEach( () => {
		delete global.wc_stripe_express_checkout_params;
	} );

	it( 'resolves the click from the cart response, not the initial preview', async () => {
		global.wc_stripe_express_checkout_params = productParams();

		mockAddToCart.mockResolvedValue( {
			items_count: 1,
			totals: {
				total_price: '2000',
				total_refund: '0',
				currency_minor_unit: 2,
			},
		} );
		mockEmptyCartLegacy.mockResolvedValue( {} );
		// eslint-disable-next-line global-require
		const transformers = require( 'wcstripe/express-checkout/transformers/wc-to-stripe' );
		transformers.transformPrice.mockReturnValueOnce( 2000 );
		transformers.transformCartDataForDisplayItems.mockReturnValueOnce( [
			{ name: 'Blue variation', amount: 2000 },
		] );

		const { handlers, elementsList } = stubStripeButton();
		loadEntrypoint();

		const event = { resolve: jest.fn(), expressPaymentType: 'googlePay' };
		await handlers.click( event );

		expect( event.resolve ).toHaveBeenCalledTimes( 1 );
		expect( event.resolve.mock.calls[ 0 ][ 0 ].lineItems ).toEqual( [
			{ name: 'Blue variation', amount: 2000 },
		] );
		elementsList.forEach( ( elements ) =>
			expect( elements.update ).toHaveBeenCalledWith( { amount: 2000 } )
		);
	} );

	it( "resolves a simple product's click from the cart response, quantity included", async () => {
		global.wc_stripe_express_checkout_params = productParams();

		// Simple product: no variation markup, quantity 2. The cart-derived
		// items carry the quantity; creation-time options would not.
		document.body.innerHTML = `
			<div id="wc-stripe-express-checkout-element"></div>
			<div class="quantity"><input type="number" class="qty" name="quantity" value="2" /></div>
			<button class="single_add_to_cart_button" value="99">Add</button>`;

		mockAddToCart.mockResolvedValue( {
			items_count: 1,
			totals: {
				total_price: '4000',
				total_refund: '0',
				currency_minor_unit: 2,
			},
		} );
		mockEmptyCartLegacy.mockResolvedValue( {} );
		// eslint-disable-next-line global-require
		const transformers = require( 'wcstripe/express-checkout/transformers/wc-to-stripe' );
		transformers.transformPrice.mockReturnValueOnce( 4000 );
		transformers.transformCartDataForDisplayItems.mockReturnValueOnce( [
			{ name: 'Simple thing (x2)', amount: 4000 },
		] );

		const { handlers } = stubStripeButton();
		loadEntrypoint();

		const event = { resolve: jest.fn(), expressPaymentType: 'googlePay' };
		await handlers.click( event );

		expect( event.resolve.mock.calls[ 0 ][ 0 ].lineItems ).toEqual( [
			{ name: 'Simple thing (x2)', amount: 4000 },
		] );
	} );

	it( 'does not ask for a shipping address when the cart says the selection is virtual', async () => {
		// A variable parent reports needing shipping even when every variation
		// is virtual, so the creation-time flag says true; prompting would then
		// hard-fail on a cart with no shippable items.
		const params = productParams();
		params.product.requestShipping = true;
		// Init refuses to render a shipping-required button without a rate.
		params.product.shippingOptions = {
			id: 'flat',
			label: 'Flat',
			amount: 0,
		};
		global.wc_stripe_express_checkout_params = params;

		mockAddToCart.mockResolvedValue( {
			items_count: 1,
			needs_shipping: false,
			totals: {
				total_price: '2000',
				total_refund: '0',
				currency_minor_unit: 2,
			},
		} );
		mockEmptyCartLegacy.mockResolvedValue( {} );
		// eslint-disable-next-line global-require
		const transformers = require( 'wcstripe/express-checkout/transformers/wc-to-stripe' );
		transformers.transformPrice.mockReturnValueOnce( 2000 );
		transformers.transformCartDataForDisplayItems.mockReturnValueOnce( [] );

		const { handlers } = stubStripeButton();
		loadEntrypoint();

		const event = { resolve: jest.fn(), expressPaymentType: 'googlePay' };
		await handlers.click( event );

		const clickOptions = event.resolve.mock.calls[ 0 ][ 0 ];
		expect( clickOptions.shippingAddressRequired ).toBe( false );
		expect( clickOptions ).not.toHaveProperty( 'shippingRates' );
	} );

	it( 'asks for a shipping address when the cart says the selection is shippable', async () => {
		global.wc_stripe_express_checkout_params = productParams();

		mockAddToCart.mockResolvedValue( {
			items_count: 1,
			needs_shipping: true,
			totals: {
				total_price: '2000',
				total_refund: '0',
				currency_minor_unit: 2,
			},
		} );
		mockEmptyCartLegacy.mockResolvedValue( {} );
		// eslint-disable-next-line global-require
		const transformers = require( 'wcstripe/express-checkout/transformers/wc-to-stripe' );
		transformers.transformPrice.mockReturnValueOnce( 2000 );
		transformers.transformCartDataForDisplayItems.mockReturnValueOnce( [] );

		const { handlers } = stubStripeButton();
		loadEntrypoint();

		const event = { resolve: jest.fn(), expressPaymentType: 'googlePay' };
		await handlers.click( event );

		const clickOptions = event.resolve.mock.calls[ 0 ][ 0 ];
		expect( clickOptions.shippingAddressRequired ).toBe( true );
		expect( clickOptions ).toHaveProperty( 'shippingRates' );
	} );

	it( 'rejects the click and prompts when the selection is incomplete', async () => {
		global.wc_stripe_express_checkout_params = productParams();

		document.querySelector( 'input[name="variation_id"]' ).value = '';
		const alertSpy = jest
			.spyOn( window, 'alert' )
			.mockImplementation( () => {} );

		const { handlers } = stubStripeButton();
		loadEntrypoint();

		try {
			const event = {
				resolve: jest.fn(),
				reject: jest.fn(),
				expressPaymentType: 'googlePay',
			};
			await handlers.click( event );

			expect( event.reject ).toHaveBeenCalledTimes( 1 );
			expect( event.resolve ).not.toHaveBeenCalled();
			// The prompt is deferred so the rejected wallet UI can dismiss
			// before the blocking dialog pauses the event loop.
			expect( alertSpy ).not.toHaveBeenCalled();
			await new Promise( ( resolve ) => setTimeout( resolve, 150 ) );
			expect( alertSpy ).toHaveBeenCalledWith(
				expect.stringContaining( 'select your product options' )
			);
			expect( mockAddToCart ).not.toHaveBeenCalled();
		} finally {
			alertSpy.mockRestore();
		}
	} );

	it( 'rejects the click when the cart response misses the deadline and primes the next attempt', async () => {
		jest.useFakeTimers();
		global.wc_stripe_express_checkout_params = productParams();

		let resolveAddToCart;
		mockAddToCart.mockImplementation(
			() =>
				new Promise( ( resolve ) => {
					resolveAddToCart = resolve;
				} )
		);
		mockEmptyCartLegacy.mockResolvedValue( {} );
		// eslint-disable-next-line global-require
		const transformers = require( 'wcstripe/express-checkout/transformers/wc-to-stripe' );
		transformers.transformPrice.mockReturnValueOnce( 4000 );
		transformers.transformCartDataForDisplayItems.mockReturnValueOnce( [
			{ name: 'Red variation (x2)', amount: 4000 },
		] );
		// eslint-disable-next-line global-require
		const jq = require( 'jquery' );
		const unblockSpy = jest.fn().mockReturnThis();
		jq.fn.unblock = unblockSpy;

		const { handlers, elementsList } = stubStripeButton();
		loadEntrypoint();

		try {
			const event = {
				resolve: jest.fn(),
				reject: jest.fn(),
				expressPaymentType: 'googlePay',
			};
			const clickPromise = handlers.click( event );

			await jest.advanceTimersByTimeAsync( 750 );
			// Sheet must not open from a stale preview.
			expect( event.reject ).toHaveBeenCalledTimes( 1 );
			expect( event.resolve ).not.toHaveBeenCalled();
			// Still blocked: a retry must not overlap the pending
			// empty-cart -> add mutation.
			expect( unblockSpy ).not.toHaveBeenCalled();

			resolveAddToCart( {
				items_count: 1,
				totals: {
					total_price: '4000',
					total_refund: '0',
					currency_minor_unit: 2,
				},
			} );
			await jest.advanceTimersByTimeAsync( 0 );
			await clickPromise;

			// The late response primes the elements for the next attempt.
			elementsList.forEach( ( elements ) =>
				expect( elements.update ).toHaveBeenCalledWith( {
					amount: 4000,
				} )
			);
			expect( unblockSpy ).toHaveBeenCalled();
		} finally {
			jest.useRealTimers();
		}
	} );

	it( 'explains a stock or quantity block instead of asking for options when the selection is complete', async () => {
		global.wc_stripe_express_checkout_params = productParams();

		// Variation resolved (fixture variation_id=123) but the button is
		// disabled - e.g. an out-of-stock variation.
		document
			.querySelector( '.single_add_to_cart_button' )
			.classList.add( 'disabled' );
		const alertSpy = jest
			.spyOn( window, 'alert' )
			.mockImplementation( () => {} );

		const { handlers } = stubStripeButton();
		loadEntrypoint();

		try {
			const event = {
				resolve: jest.fn(),
				reject: jest.fn(),
				expressPaymentType: 'googlePay',
			};
			await handlers.click( event );
			await new Promise( ( resolve ) => setTimeout( resolve, 150 ) );
			expect( alertSpy ).toHaveBeenCalledWith(
				expect.stringContaining(
					'cannot be purchased with the selected options or quantity'
				)
			);
			expect( event.reject ).toHaveBeenCalledTimes( 1 );
			expect( event.resolve ).not.toHaveBeenCalled();
			expect( mockAddToCart ).not.toHaveBeenCalled();
		} finally {
			alertSpy.mockRestore();
		}
	} );

	it( 'pushes the cart amount to every mounted express button, not just the last one', async () => {
		global.wc_stripe_express_checkout_params = productParams();

		mockAddToCart.mockResolvedValue( {
			items_count: 1,
			totals: {
				total_price: '2000',
				total_refund: '0',
				currency_minor_unit: 2,
			},
		} );
		mockEmptyCartLegacy.mockResolvedValue( {} );
		// eslint-disable-next-line global-require
		const transformers = require( 'wcstripe/express-checkout/transformers/wc-to-stripe' );
		transformers.transformPrice.mockReturnValueOnce( 2000 );
		transformers.transformCartDataForDisplayItems.mockReturnValueOnce( [
			{ name: 'Blue variation', amount: 2000 },
		] );

		const { handlers, elementsList } = stubStripeButton();
		loadEntrypoint();

		// Apple Pay and Google Pay each mount their own Elements group.
		expect( elementsList.length ).toBeGreaterThan( 1 );

		await handlers.click( {
			resolve: jest.fn(),
			expressPaymentType: 'googlePay',
		} );

		// Updating only one group leaves the others at the previous amount,
		// and the wallet then rejects the click because the refreshed line
		// items exceed that stale amount.
		elementsList.forEach( ( elements ) => {
			expect( elements.update ).toHaveBeenCalledWith( { amount: 2000 } );
		} );
	} );
} );

describe( 'Express Checkout per-method location gating', () => {
	// Each created express button mounts into its own
	// `#wc-stripe-express-checkout-element-<type>` container, so the container ids
	// are the observable record of which wallets were actually created.
	const mountedTypes = () =>
		Array.from(
			document.querySelectorAll(
				'#wc-stripe-express-checkout-element > div'
			)
		).map( ( el ) =>
			el.id.replace( 'wc-stripe-express-checkout-element-', '' )
		);

	const stubStripe = () => {
		const button = {
			on: () => button,
			mount: jest.fn(),
		};
		mockGetStripe.mockReturnValue( {
			elements: jest.fn( () => ( {
				create: jest.fn( () => button ),
				update: jest.fn(),
			} ) ),
		} );
	};

	const cartParamsWithFlags = ( stripeFlags ) => ( {
		...baseParams(),
		stripe: {
			publishable_key: 'pk_test_123',
			locale: 'en',
			...stripeFlags,
		},
		cart: {
			total: 1500,
			currency: 'usd',
			requestShipping: false,
			requestPhone: false,
			displayItems: [],
		},
	} );

	beforeEach( () => {
		jest.resetModules();
		mockGetStripe.mockReset();
		stubStripe();
		document.body.innerHTML =
			'<div id="wc-stripe-express-checkout-element"></div>';
	} );

	afterEach( () => {
		delete global.wc_stripe_express_checkout_params;
	} );

	it( 'does not create Apple/Google Pay buttons when only another wallet covers this page', () => {
		global.wc_stripe_express_checkout_params = cartParamsWithFlags( {
			// The aggregate is true because Amazon Pay covers this location —
			// that alone must not surface Apple/Google Pay (STRIPE-1363).
			is_express_checkout_enabled: true,
			is_apple_pay_enabled: false,
			is_google_pay_enabled: false,
			is_amazon_pay_enabled: true,
		} );

		loadEntrypoint();

		expect( mountedTypes() ).toEqual( [ 'amazonPay' ] );
	} );

	it( 'creates Apple/Google Pay buttons when their own locations cover this page', () => {
		global.wc_stripe_express_checkout_params = cartParamsWithFlags( {
			is_express_checkout_enabled: true,
			is_apple_pay_enabled: true,
			is_google_pay_enabled: true,
			is_amazon_pay_enabled: false,
		} );

		loadEntrypoint();

		expect( mountedTypes() ).toEqual( [ 'applePay', 'googlePay' ] );
	} );

	it( 'gates each wallet on its own flag, ready for a future settings split', () => {
		global.wc_stripe_express_checkout_params = cartParamsWithFlags( {
			is_express_checkout_enabled: true,
			is_apple_pay_enabled: false,
			is_google_pay_enabled: true,
			is_amazon_pay_enabled: false,
		} );

		loadEntrypoint();

		expect( mountedTypes() ).toEqual( [ 'googlePay' ] );
	} );
} );
