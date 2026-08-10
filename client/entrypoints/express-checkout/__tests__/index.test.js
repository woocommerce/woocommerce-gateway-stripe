/**
 * Tests for the cart/checkout bootstrap path in the Express Checkout entrypoint.
 *
 * The entrypoint is an `jQuery( ( $ ) => { … } )` IIFE with no exported surface,
 * so these tests load it for its side effects and assert on the one observable
 * signal that distinguishes the bootstrap path from the legacy AJAX path: whether
 * `expressCheckoutGetCartDetails()` (the `GET /wc/store/v1/cart` fetch) fires.
 */

const mockGetCartDetails = jest.fn();
const mockGetSelectedProductData = jest.fn();
const mockGetStripe = jest.fn();
const mockAddToCart = jest.fn();
const mockEmptyCartLegacy = jest.fn();

// Drain both microtasks and jQuery Deferred's timer-scheduled callbacks.
const flushPromises = async () => {
	for ( let i = 0; i < 5; i++ ) {
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	}
};

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
		expressCheckoutGetSelectedProductData: mockGetSelectedProductData,
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
		[
			mockGetSelectedProductData,
			mockGetStripe,
			mockAddToCart,
			mockEmptyCartLegacy,
		].forEach( ( m ) => m.mockReset() );

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

	it( 'resolves the click with the newly selected variation’s line items, not the initial ones', async () => {
		global.wc_stripe_express_checkout_params = productParams();

		// Switching to blue ($20) refreshes the preview via the fast-path, which
		// updates only the element amount — the regression left the breakdown stale.
		mockGetSelectedProductData.mockResolvedValue( {
			total: { amount: 2000 },
			currency: 'usd',
			requestShipping: false,
			displayItems: [ { label: 'Blue variation', amount: 2000 } ],
		} );
		mockAddToCart.mockResolvedValue( { items_count: 1 } );
		mockEmptyCartLegacy.mockResolvedValue( {} );

		const { handlers } = stubStripeButton();

		loadEntrypoint();
		// Product-page init resolves the currency before mounting the button and
		// binding the variation handler, so let it settle before triggering.
		await flushPromises();

		// eslint-disable-next-line global-require
		require( 'jquery' )( document.body ).trigger(
			'woocommerce_variation_has_changed'
		);
		await flushPromises();

		const event = { resolve: jest.fn(), expressPaymentType: 'googlePay' };
		await handlers.click( event );

		expect( event.resolve ).toHaveBeenCalledTimes( 1 );
		expect( event.resolve.mock.calls[ 0 ][ 0 ].lineItems ).toEqual( [
			{ name: 'Blue variation', amount: 2000 },
		] );
	} );

	it( 'refreshes the click breakdown when the quantity changes', async () => {
		// The `.qty` handler is debounced (250ms), so drive timers deterministically.
		jest.useFakeTimers();
		global.wc_stripe_express_checkout_params = productParams();

		// Bumping qty to 2 doubles the red variation breakdown via the debounced
		// .qty fast-path, which writes the new total/displayItems back to the cache.
		mockGetSelectedProductData.mockResolvedValue( {
			total: { amount: 2000 },
			currency: 'usd',
			requestShipping: false,
			displayItems: [ { label: 'Red variation', amount: 2000 } ],
		} );
		mockAddToCart.mockResolvedValue( { items_count: 1 } );
		mockEmptyCartLegacy.mockResolvedValue( {} );

		const { handlers } = stubStripeButton();

		loadEntrypoint();
		// Product-page init is async (currency resolves before the .qty handler is
		// bound); flush the microtask queue under fake timers before driving input.
		await jest.advanceTimersByTimeAsync( 0 );

		// eslint-disable-next-line global-require
		const jq = require( 'jquery' );
		const qtyInput = document.querySelector( '.qty' );
		qtyInput.value = '2';
		jq( qtyInput ).trigger( 'input' );

		// Run the debounce and flush the resulting promise chain. Restore real
		// timers in `finally` so a throw here can't leak fake timers into later tests.
		try {
			await jest.advanceTimersByTimeAsync( 300 );
		} finally {
			jest.useRealTimers();
		}

		const event = { resolve: jest.fn(), expressPaymentType: 'googlePay' };
		await handlers.click( event );

		expect( event.resolve ).toHaveBeenCalledTimes( 1 );
		expect( event.resolve.mock.calls[ 0 ][ 0 ].lineItems ).toEqual( [
			{ name: 'Red variation', amount: 2000 },
		] );
	} );

	it( 'pushes the new amount to every mounted express button, not just the last one', async () => {
		global.wc_stripe_express_checkout_params = productParams();

		mockGetSelectedProductData.mockResolvedValue( {
			total: { amount: 2000 },
			currency: 'usd',
			requestShipping: false,
			displayItems: [ { label: 'Blue variation', amount: 2000 } ],
		} );
		mockAddToCart.mockResolvedValue( { items_count: 1 } );
		mockEmptyCartLegacy.mockResolvedValue( {} );

		const { elementsList } = stubStripeButton();

		loadEntrypoint();
		// Product-page init resolves the currency before mounting the buttons.
		await flushPromises();

		// Apple Pay and Google Pay each mount their own Elements group.
		expect( elementsList.length ).toBeGreaterThan( 1 );

		// eslint-disable-next-line global-require
		require( 'jquery' )( document.body ).trigger(
			'woocommerce_variation_has_changed'
		);
		await flushPromises();

		// The regression updated only the last group, leaving the others below
		// the refreshed line-item total so their wallet rejected the click.
		elementsList.forEach( ( elements ) => {
			expect( elements.update ).toHaveBeenCalledWith( { amount: 2000 } );
		} );
	} );
} );
