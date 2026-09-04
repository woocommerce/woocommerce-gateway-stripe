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
	transformCartTotalAmount: jest.fn( () => 1500 ),
} ) );

// Stub the shared event handlers so the entrypoint's own `abortPayment` can be
// captured from the params it hands to onConfirmHandler.
jest.mock( 'wcstripe/express-checkout/event-handler', () => ( {
	onAbortPaymentHandler: jest.fn(),
	onCancelHandler: jest.fn(),
	onClickHandler: jest.fn(),
	onCompletePaymentHandler: jest.fn(),
	onConfirmHandler: jest.fn(),
	shippingAddressChangeHandler: jest.fn(),
	shippingRateChangeHandler: jest.fn(),
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

	const clickEvent = () => ( {
		resolve: jest.fn(),
		reject: jest.fn(),
		expressPaymentType: 'googlePay',
	} );

	const cartResponse = ( total, extra = {} ) => ( {
		items_count: 1,
		totals: {
			total_price: String( total ),
			total_refund: '0',
			currency_minor_unit: 2,
		},
		...extra,
	} );

	const stubTransformersOnce = ( amount, displayItems = [] ) => {
		// eslint-disable-next-line global-require
		const transformers = require( 'wcstripe/express-checkout/transformers/wc-to-stripe' );
		transformers.transformCartTotalAmount.mockReturnValueOnce( amount );
		transformers.transformCartDataForDisplayItems.mockReturnValueOnce(
			displayItems
		);
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

		mockAddToCart.mockResolvedValue( cartResponse( 2000 ) );
		mockEmptyCartLegacy.mockResolvedValue( {} );
		stubTransformersOnce( 2000, [
			{ name: 'Blue variation', amount: 2000 },
		] );

		const { handlers, elementsList } = stubStripeButton();
		loadEntrypoint();

		const event = clickEvent();
		await handlers.click( event );

		expect( event.resolve ).toHaveBeenCalledTimes( 1 );
		expect( event.resolve.mock.calls[ 0 ][ 0 ].lineItems ).toEqual( [
			{ name: 'Blue variation', amount: 2000 },
		] );
		// Every wallet mounts its own Elements group; a group left at the
		// previous amount rejects the click when the line items exceed it.
		expect( elementsList.length ).toBeGreaterThan( 1 );
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

		mockAddToCart.mockResolvedValue( cartResponse( 4000 ) );
		mockEmptyCartLegacy.mockResolvedValue( {} );
		stubTransformersOnce( 4000, [
			{ name: 'Simple thing (x2)', amount: 4000 },
		] );

		const { handlers } = stubStripeButton();
		loadEntrypoint();

		const event = clickEvent();
		await handlers.click( event );

		expect( event.resolve.mock.calls[ 0 ][ 0 ].lineItems ).toEqual( [
			{ name: 'Simple thing (x2)', amount: 4000 },
		] );
	} );

	it( 'prices the sheet from a legacy add-to-cart response (bookings shape)', async () => {
		global.wc_stripe_express_checkout_params = productParams();

		// The legacy endpoint also returns cart-computed data
		// (build_display_items after calculate_totals), just in the labeled
		// shape with amounts already in Stripe minor units.
		mockAddToCart.mockResolvedValue( {
			result: 'success',
			total: { label: 'Total', amount: 3200 },
			displayItems: [ { label: 'Booking', amount: 3200 } ],
		} );
		mockEmptyCartLegacy.mockResolvedValue( {} );

		const { handlers, elementsList } = stubStripeButton();
		loadEntrypoint();

		const event = clickEvent();
		await handlers.click( event );

		expect( event.reject ).not.toHaveBeenCalled();
		expect( event.resolve ).toHaveBeenCalledTimes( 1 );
		expect( event.resolve.mock.calls[ 0 ][ 0 ].lineItems ).toEqual( [
			{ name: 'Booking', amount: 3200 },
		] );
		elementsList.forEach( ( elements ) =>
			expect( elements.update ).toHaveBeenCalledWith( { amount: 3200 } )
		);
	} );

	it.each( [
		{
			name: 'skips the address prompt when the cart says the selection is virtual',
			needsShipping: false,
		},
		{
			name: 'asks for an address when the cart says the selection is shippable',
			needsShipping: true,
		},
	] )( '$name', async ( { needsShipping } ) => {
		// The creation-time flag reflects the variable parent, so the cart's
		// verdict must override it in both directions - a wrong prompt
		// hard-fails on a cart with no shippable items.
		const params = productParams();
		params.product.requestShipping = ! needsShipping;
		if ( ! needsShipping ) {
			// Init refuses to render a shipping-required button without a rate.
			params.product.shippingOptions = {
				id: 'flat',
				label: 'Flat',
				amount: 0,
			};
		}
		global.wc_stripe_express_checkout_params = params;

		mockAddToCart.mockResolvedValue(
			cartResponse( 2000, { needs_shipping: needsShipping } )
		);
		mockEmptyCartLegacy.mockResolvedValue( {} );
		stubTransformersOnce( 2000 );

		const { handlers } = stubStripeButton();
		loadEntrypoint();

		const event = clickEvent();
		await handlers.click( event );

		const clickOptions = event.resolve.mock.calls[ 0 ][ 0 ];
		expect( clickOptions.shippingAddressRequired ).toBe( needsShipping );
		expect( 'shippingRates' in clickOptions ).toBe( needsShipping );
		// Stripe requires a rate with the address prompt; with no zone
		// default configured the pending placeholder stands in.
		expect( clickOptions.shippingRates?.length > 0 ).toBe( needsShipping );
	} );

	it( 'rejects the click and relays the server message when the add is refused', async () => {
		global.wc_stripe_express_checkout_params = productParams();

		// Store API 4xx (insufficient stock, unsupported product type, …)
		// rejects the fetch with a localized shopper-facing message.
		jest.useFakeTimers();
		mockAddToCart.mockRejectedValue( {
			code: 'woocommerce_rest_product_out_of_stock',
			message: 'There is not enough stock.',
		} );
		mockEmptyCartLegacy.mockResolvedValue( {} );
		const alertSpy = jest
			.spyOn( window, 'alert' )
			.mockImplementation( () => {} );

		const { handlers } = stubStripeButton();
		loadEntrypoint();

		try {
			const event = clickEvent();
			await handlers.click( event );

			expect( event.reject ).toHaveBeenCalledTimes( 1 );
			expect( event.resolve ).not.toHaveBeenCalled();
			await jest.advanceTimersByTimeAsync( 100 );
			expect( alertSpy ).toHaveBeenCalledWith(
				'There is not enough stock.'
			);
		} finally {
			jest.useRealTimers();
			alertSpy.mockRestore();
		}
	} );

	it.each( [
		{
			name: 'prompts for options when the selection is incomplete',
			arrange: () => {
				document.querySelector( 'input[name="variation_id"]' ).value =
					'';
			},
			message: 'select your product options',
		},
		{
			// Variation resolved (fixture variation_id=123) but the button is
			// disabled - e.g. an out-of-stock variation.
			name: 'explains a stock or quantity block when the selection is complete',
			arrange: () => {
				document
					.querySelector( '.single_add_to_cart_button' )
					.classList.add( 'disabled' );
			},
			message:
				'cannot be purchased with the selected options or quantity',
		},
	] )( 'rejects the click and $name', async ( { arrange, message } ) => {
		jest.useFakeTimers();
		global.wc_stripe_express_checkout_params = productParams();
		arrange();
		const alertSpy = jest
			.spyOn( window, 'alert' )
			.mockImplementation( () => {} );

		const { handlers } = stubStripeButton();
		loadEntrypoint();

		try {
			const event = clickEvent();
			await handlers.click( event );

			expect( event.reject ).toHaveBeenCalledTimes( 1 );
			expect( event.resolve ).not.toHaveBeenCalled();
			// The prompt is deferred so the rejected wallet UI can dismiss
			// before the blocking dialog pauses the event loop.
			expect( alertSpy ).not.toHaveBeenCalled();
			await jest.advanceTimersByTimeAsync( 100 );
			expect( alertSpy ).toHaveBeenCalledWith(
				expect.stringContaining( message )
			);
			expect( mockAddToCart ).not.toHaveBeenCalled();
		} finally {
			jest.useRealTimers();
			alertSpy.mockRestore();
		}
	} );

	it( 'rejects the click when the cart response misses the deadline; an unchanged retry resolves without re-adding, a changed selection re-adds', async () => {
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
		stubTransformersOnce( 4000, [
			{ name: 'Red variation (x2)', amount: 4000 },
		] );
		// eslint-disable-next-line global-require
		const jq = require( 'jquery' );
		const unblockSpy = jest.fn().mockReturnThis();
		jq.fn.unblock = unblockSpy;

		const { handlers, elementsList } = stubStripeButton();
		loadEntrypoint();

		try {
			const event = clickEvent();
			const clickPromise = handlers.click( event );

			await jest.advanceTimersByTimeAsync( 750 );
			// Sheet must not open from a stale preview.
			expect( event.reject ).toHaveBeenCalledTimes( 1 );
			expect( event.resolve ).not.toHaveBeenCalled();
			// Still blocked: a retry must not overlap the pending
			// empty-cart -> add mutation.
			expect( unblockSpy ).not.toHaveBeenCalled();

			resolveAddToCart( cartResponse( 4000 ) );
			await jest.advanceTimersByTimeAsync( 0 );
			await clickPromise;

			// The late response refreshes the elements for the next attempt.
			elementsList.forEach( ( elements ) =>
				expect( elements.update ).toHaveBeenCalledWith( {
					amount: 4000,
				} )
			);
			expect( unblockSpy ).toHaveBeenCalled();

			// An unchanged retry resolves from the settled cart data without
			// a second add-to-cart - otherwise a consistently slow store
			// would reject every attempt.
			const retryEvent = clickEvent();
			await handlers.click( retryEvent );
			expect( mockAddToCart ).toHaveBeenCalledTimes( 1 );
			expect( retryEvent.reject ).not.toHaveBeenCalled();
			expect( retryEvent.resolve ).toHaveBeenCalledTimes( 1 );
			expect( retryEvent.resolve.mock.calls[ 0 ][ 0 ].lineItems ).toEqual(
				[ { name: 'Red variation (x2)', amount: 4000 } ]
			);

			// A different variation invalidates the settled selection, so the
			// next click must re-add. The key follows the attribute selection
			// (what add-to-cart sends), not the resolved variation_id.
			const select = document.querySelector(
				'select[name="attribute_color"]'
			);
			const redOption = document.createElement( 'option' );
			redOption.value = 'red';
			select.appendChild( redOption );
			select.value = 'red';
			mockAddToCart.mockResolvedValue( cartResponse( 5000 ) );
			stubTransformersOnce( 5000 );

			const changedEvent = clickEvent();
			await handlers.click( changedEvent );
			expect( mockAddToCart ).toHaveBeenCalledTimes( 2 );
			expect( changedEvent.resolve ).toHaveBeenCalledTimes( 1 );
		} finally {
			jest.useRealTimers();
		}
	} );

	it( 'shows a generic message when the add fails without a server refusal', async () => {
		jest.useFakeTimers();
		global.wc_stripe_express_checkout_params = productParams();

		// A transport failure (no apiFetch code) must not surface internals
		// or selection advice.
		mockAddToCart.mockRejectedValue( new TypeError( 'Failed to fetch' ) );
		mockEmptyCartLegacy.mockResolvedValue( {} );
		const alertSpy = jest
			.spyOn( window, 'alert' )
			.mockImplementation( () => {} );

		const { handlers } = stubStripeButton();
		loadEntrypoint();

		try {
			const event = clickEvent();
			await handlers.click( event );
			expect( event.reject ).toHaveBeenCalledTimes( 1 );
			await jest.advanceTimersByTimeAsync( 100 );
			expect( alertSpy ).toHaveBeenCalledWith(
				'There was an error adding the product to the cart.'
			);
		} finally {
			jest.useRealTimers();
			alertSpy.mockRestore();
		}
	} );

	it( 'relays the server message when a slow add is refused after the deadline', async () => {
		jest.useFakeTimers();
		global.wc_stripe_express_checkout_params = productParams();

		let rejectAddToCart;
		mockAddToCart.mockImplementation(
			() =>
				new Promise( ( resolve, reject ) => {
					rejectAddToCart = reject;
				} )
		);
		mockEmptyCartLegacy.mockResolvedValue( {} );
		const alertSpy = jest
			.spyOn( window, 'alert' )
			.mockImplementation( () => {} );

		const { handlers } = stubStripeButton();
		loadEntrypoint();

		try {
			const event = clickEvent();
			const clickPromise = handlers.click( event );
			await jest.advanceTimersByTimeAsync( 750 );
			expect( event.reject ).toHaveBeenCalledTimes( 1 );

			rejectAddToCart( {
				code: 'woocommerce_rest_product_out_of_stock',
				message: 'There is not enough stock.',
			} );
			await jest.advanceTimersByTimeAsync( 100 );
			await clickPromise;
			expect( alertSpy ).toHaveBeenCalledWith(
				'There is not enough stock.'
			);
		} finally {
			jest.useRealTimers();
			alertSpy.mockRestore();
		}
	} );

	it( 'unblocks the button when the pending add never settles', async () => {
		jest.useFakeTimers();
		global.wc_stripe_express_checkout_params = productParams();

		// A request the browser hangs onto: never resolves, never rejects.
		mockAddToCart.mockImplementation( () => new Promise( () => {} ) );
		mockEmptyCartLegacy.mockResolvedValue( {} );
		// eslint-disable-next-line global-require
		const jq = require( 'jquery' );
		const unblockSpy = jest.fn().mockReturnThis();
		jq.fn.unblock = unblockSpy;

		const { handlers, elementsList } = stubStripeButton();
		loadEntrypoint();

		try {
			const event = clickEvent();
			const clickPromise = handlers.click( event );

			await jest.advanceTimersByTimeAsync( 750 );
			expect( event.reject ).toHaveBeenCalledTimes( 1 );
			expect( unblockSpy ).not.toHaveBeenCalled();

			// The bounded wait gives up and releases the button; nothing is
			// primed from the abandoned attempt.
			await jest.advanceTimersByTimeAsync( 30000 );
			await clickPromise;
			expect( unblockSpy ).toHaveBeenCalled();
			elementsList.forEach( ( elements ) =>
				expect( elements.update ).not.toHaveBeenCalled()
			);
		} finally {
			jest.useRealTimers();
		}
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

describe( 'Express Checkout order failures', () => {
	const stubStripeButton = () => {
		const handlers = {};
		const button = {
			on: ( evt, cb ) => {
				handlers[ evt ] = cb;
				return button;
			},
			mount: jest.fn(),
		};
		mockGetStripe.mockReturnValue( {
			elements: jest.fn( () => ( {
				create: jest.fn( () => button ),
				update: jest.fn(),
			} ) ),
		} );
		return handlers;
	};

	beforeEach( () => {
		jest.resetModules();
		mockGetStripe.mockReset();

		// No notices wrapper: the storefront case where the message used to vanish.
		document.body.innerHTML =
			'<div id="wc-stripe-express-checkout-element"></div>';

		global.wc_stripe_express_checkout_params = {
			...baseParams(),
			is_cart_page: false,
			stripe: {
				publishable_key: 'pk_test_123',
				locale: 'en',
				is_apple_pay_enabled: true,
			},
			cart: {
				total: 1500,
				currency: 'usd',
				requestShipping: false,
				requestPhone: false,
				displayItems: [],
			},
		};
	} );

	afterEach( () => {
		delete global.wc_stripe_express_checkout_params;
	} );

	// The order-side abort used to skip paymentFailed(), leaving the approved wallet
	// sheet open with nothing on screen. The third argument is the removed
	// `isOrderError` opt-out: passing it must change nothing.
	it( 'fails the wallet sheet and shows the message when the order errors', async () => {
		const handlers = stubStripeButton();
		loadEntrypoint();

		// Resolve the mocks from the same module registry the entrypoint loaded from;
		// `jest.resetModules()` hands each test its own copy.
		// eslint-disable-next-line global-require
		const jq = require( 'jquery' );
		const {
			onAbortPaymentHandler,
			onConfirmHandler,
			// eslint-disable-next-line global-require
		} = require( 'wcstripe/express-checkout/event-handler' );

		jq( document.body ).trigger( 'updated_checkout' );

		const event = { paymentFailed: jest.fn() };
		await handlers.confirm( event );

		const { abortPayment } = onConfirmHandler.mock.calls[ 0 ][ 0 ];
		abortPayment( event, 'Order creation error', true );

		expect( event.paymentFailed ).toHaveBeenCalledWith( {
			reason: 'fail',
		} );
		expect(
			document.querySelector( '.woocommerce-error' ).textContent
		).toBe( 'Order creation error' );

		// The message has to be in front of the shopper before the sheet closes.
		expect(
			onAbortPaymentHandler.mock.invocationCallOrder[ 0 ]
		).toBeLessThan( event.paymentFailed.mock.invocationCallOrder[ 0 ] );
	} );

	it( 'closes the retry modal when the payment errors so the notice is readable', async () => {
		const handlers = stubStripeButton();
		loadEntrypoint();

		// eslint-disable-next-line global-require
		const jq = require( 'jquery' );
		// eslint-disable-next-line global-require
		const {
			onConfirmHandler,
		} = require( 'wcstripe/express-checkout/event-handler' );

		jq( document.body ).trigger( 'updated_checkout' );

		const event = { paymentFailed: jest.fn() };
		await handlers.confirm( event );

		// A timed-out click leaves the retry modal on screen; the error path
		// must remove it, or the page notice renders behind its backdrop.
		document.body.insertAdjacentHTML(
			'beforeend',
			'<div id="wc-stripe-ece-retry-modal"></div>'
		);

		const { abortPayment } = onConfirmHandler.mock.calls[ 0 ][ 0 ];
		abortPayment( event, 'Order creation error' );

		expect(
			document.querySelector( '#wc-stripe-ece-retry-modal' )
		).toBeNull();
		expect(
			document.querySelector( '.woocommerce-error' ).textContent
		).toBe( 'Order creation error' );
	} );
} );
