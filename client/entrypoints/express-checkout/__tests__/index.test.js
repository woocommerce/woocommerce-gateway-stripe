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
} );

describe( 'Express Checkout group reuse on cart/checkout updates', () => {
	// One stub per Elements group so tests can tell reuse from rebuild.
	let elementsGroups;
	let buttons;

	const stubStripe = () => {
		elementsGroups = [];
		buttons = [];
		mockGetStripe.mockReturnValue( {
			elements: jest.fn( () => {
				const handlers = {};
				const button = {
					handlers,
					on: jest.fn( ( eventName, callback ) => {
						handlers[ eventName ] = callback;
						return button;
					} ),
					mount: jest.fn(),
					unmount: jest.fn(),
					destroy: jest.fn(),
				};
				buttons.push( button );
				const group = {
					create: jest.fn( () => button ),
					update: jest.fn(),
				};
				elementsGroups.push( group );
				return group;
			} ),
		} );
	};

	const cartParams = () => ( {
		...baseParams(),
		stripe: {
			publishable_key: 'pk_test_123',
			locale: 'en',
			is_google_pay_enabled: true,
		},
		cart: {
			total: 1500,
			currency: 'usd',
			requestShipping: false,
			requestPhone: false,
			displayItems: [],
		},
	} );

	// Step past the debounce window so a follow-up trigger isn't swallowed.
	const waitOutDebounce = () =>
		new Promise( ( resolve ) => setTimeout( resolve, 350 ) );

	beforeEach( () => {
		jest.resetModules();
		mockGetStripe.mockReset();
		mockGetCartDetails.mockReset();
		mockGetCartDetails.mockResolvedValue( {
			totals: { total_price: '1500', total_refund: '0' },
			needs_shipping: false,
		} );
		stubStripe();
		// The click handler calls the global jQuery.blockUI, absent in jsdom.
		global.jQuery = require( 'jquery' );
		global.jQuery.blockUI = jest.fn();
		global.jQuery.unblockUI = jest.fn();
		document.body.innerHTML =
			'<div id="wc-stripe-express-checkout-element"></div>';
	} );

	afterEach( () => {
		delete global.wc_stripe_express_checkout_params;
	} );

	it( 'refreshes the mounted group in place when only the amount changed', async () => {
		global.wc_stripe_express_checkout_params = cartParams();
		loadEntrypoint();
		expect( elementsGroups ).toHaveLength( 1 );

		require( 'jquery' )( document.body ).trigger( 'updated_cart_totals' );
		await flushPromises();

		// Same structure: no new group/session, no teardown, amount pushed
		// in place.
		expect( elementsGroups ).toHaveLength( 1 );
		expect( buttons[ 0 ].destroy ).not.toHaveBeenCalled();
		expect( elementsGroups[ 0 ].update ).toHaveBeenCalledWith( {
			amount: 1500,
		} );
	} );

	it( 'rebuilds the group when the shipping requirement changes', async () => {
		global.wc_stripe_express_checkout_params = cartParams();
		loadEntrypoint();
		expect( elementsGroups ).toHaveLength( 1 );

		mockGetCartDetails.mockResolvedValue( {
			totals: { total_price: '1500', total_refund: '0' },
			needs_shipping: true,
		} );
		require( 'jquery' )( document.body ).trigger( 'updated_cart_totals' );
		await flushPromises();

		// Structural change: the old button is torn down and a new group built.
		expect( buttons[ 0 ].destroy ).toHaveBeenCalled();
		expect( elementsGroups ).toHaveLength( 2 );
	} );

	it( 'remounts the existing button when WooCommerce discarded the markup', async () => {
		global.wc_stripe_express_checkout_params = cartParams();
		loadEntrypoint();

		// Simulate WC re-rendering the totals and discarding the markup.
		document.getElementById(
			'wc-stripe-express-checkout-element'
		).innerHTML = '';
		require( 'jquery' )( document.body ).trigger( 'updated_cart_totals' );
		await flushPromises();

		expect( elementsGroups ).toHaveLength( 1 );
		expect( buttons[ 0 ].unmount ).toHaveBeenCalled();
		expect( buttons[ 0 ].mount ).toHaveBeenCalledTimes( 2 );
		expect(
			document.getElementById(
				'wc-stripe-express-checkout-element-googlePay'
			)
		).not.toBeNull();
	} );

	it( 'defers the refresh while the wallet sheet is open and applies it on cancel', async () => {
		global.wc_stripe_express_checkout_params = cartParams();
		loadEntrypoint();

		// Resolving the click marks a payment in flight (sheet open).
		await buttons[ 0 ].handlers.click( { resolve: jest.fn() } );

		require( 'jquery' )( document.body ).trigger( 'updated_cart_totals' );
		await flushPromises();
		expect( mockGetCartDetails ).not.toHaveBeenCalled();

		// Closing the sheet applies the deferred refresh.
		buttons[ 0 ].handlers.cancel();
		await waitOutDebounce();
		await flushPromises();
		expect( mockGetCartDetails ).toHaveBeenCalled();
	} );
} );

describe( 'Classic checkout early initialization', () => {
	let stripe;
	let groups;
	let params;
	let jq;

	const cartResponse = ( {
		total = '2500',
		currency = 'USD',
		shipping = true,
	} = {} ) => ( {
		items: [
			{
				name: 'Updated product',
				quantity: 1,
				totals: { line_subtotal: '2000', currency_minor_unit: 2 },
			},
		],
		totals: {
			total_price: total,
			total_shipping: shipping ? '500' : '0',
			currency_code: currency,
			currency_minor_unit: 2,
		},
		needs_shipping: shipping,
	} );

	const deferred = () => {
		let resolve;
		let reject;
		const promise = new Promise( ( res, rej ) => {
			resolve = res;
			reject = rej;
		} );
		return { promise, resolve, reject };
	};

	const settle = () => jest.advanceTimersByTimeAsync( 0 );
	const trigger = ( event ) => jq( document.body ).trigger( event );
	const ready = ( group, available = true ) =>
		group.button.emit( 'ready', {
			availablePaymentMethods: available ? { googlePay: true } : null,
		} );

	beforeEach( () => {
		jest.resetModules();
		jest.useFakeTimers();
		mockGetCartDetails.mockReset().mockResolvedValue( cartResponse() );
		mockGetStripe.mockReset();
		jq = require( 'jquery' );
		global.jQuery = jq;
		jq.blockUI = jest.fn();
		jq.unblockUI = jest.fn();

		const transformers = require( 'wcstripe/express-checkout/transformers/wc-to-stripe' );
		const actualTransformers = jest.requireActual(
			'wcstripe/express-checkout/transformers/wc-to-stripe'
		);
		Object.keys( transformers ).forEach( ( name ) => {
			transformers[ name ].mockImplementation(
				actualTransformers[ name ]
			);
		} );

		groups = [];
		stripe = {
			elements: jest.fn( ( options ) => {
				const handlers = {};
				const button = {
					on: jest.fn( ( event, handler ) => {
						handlers[ event ] = [
							...( handlers[ event ] ?? [] ),
							handler,
						];
						return button;
					} ),
					emit: ( event, data ) =>
						Promise.all(
							handlers[ event ].map( ( handler ) =>
								handler( data )
							)
						),
					mount: jest.fn( ( selector ) => {
						document.querySelector( selector ).innerHTML =
							'<iframe></iframe>';
					} ),
					unmount: jest.fn(),
					destroy: jest.fn(),
				};
				const group = {
					button,
					options: { ...options },
					create: jest.fn( () => button ),
					update: jest.fn( ( updates ) =>
						Object.assign( group.options, updates )
					),
					submit: jest.fn().mockResolvedValue( {} ),
				};
				groups.push( group );
				return group;
			} ),
		};
		mockGetStripe.mockReturnValue( stripe );
		params = {
			...baseParams(),
			is_cart_page: false,
			is_checkout_page: true,
			stripe: {
				publishable_key: 'pk_test_123',
				locale: 'en',
				is_google_pay_enabled: true,
			},
			cart: {
				total: 1500,
				currency: 'usd',
				requestShipping: true,
				requestPhone: false,
				displayItems: [ { label: 'Initial product', amount: 1500 } ],
			},
		};
		global.wc_stripe_express_checkout_params = params;
		document.body.innerHTML = `
			<div id="wc-stripe-express-checkout-element" style="display:none"></div>
			<div id="wc-stripe-express-checkout-button-separator" style="display:none"></div>`;
	} );

	afterEach( () => {
		jest.useRealTimers();
		delete global.wc_stripe_express_checkout_params;
	} );

	it( 'creates separate wallet groups from the snapshot before updated_checkout', async () => {
		params.stripe.is_apple_pay_enabled = true;
		loadEntrypoint();
		expect( groups ).toHaveLength( 2 );
		expect( mockGetCartDetails ).not.toHaveBeenCalled();
		await ready( groups[ 0 ] );
		expect(
			jq( '#wc-stripe-express-checkout-element' ).css( 'display' )
		).not.toBe( 'none' );
		expect( groups[ 0 ].options ).toMatchObject( {
			amount: 1500,
			currency: 'usd',
		} );
	} );

	it.each( [ null, {}, { total: -1, currency: 'usd' }, { total: 1500 } ] )(
		'fetches initial data without waiting for updated_checkout when the snapshot is invalid: %j',
		async ( cart ) => {
			params.cart = cart;
			loadEntrypoint();
			expect( mockGetCartDetails ).toHaveBeenCalledTimes( 1 );
			expect( groups ).toHaveLength( 0 );
			await settle();
			expect( groups[ 0 ].options.amount ).toBe( 2500 );
		}
	);

	it( 'reuses every group on the first update and refreshes click-time totals and shipping', async () => {
		params.stripe.is_apple_pay_enabled = true;
		loadEntrypoint();
		trigger( 'updated_checkout' );
		await settle();
		expect( mockGetCartDetails ).toHaveBeenCalledTimes( 1 );
		expect( groups ).toHaveLength( 2 );
		for ( const group of groups ) {
			expect( group.options.amount ).toBe( 2500 );
			expect( group.button.mount ).toHaveBeenCalledTimes( 1 );
			expect( group.button.destroy ).not.toHaveBeenCalled();
			const event = { resolve: jest.fn() };
			await group.button.emit( 'click', event );
			expect( event.resolve ).toHaveBeenCalledWith(
				expect.objectContaining( {
					lineItems: [
						{ name: 'Updated product', amount: 2000 },
						{
							key: 'total_shipping',
							name: 'Shipping',
							amount: 500,
						},
					],
					shippingRates: [
						{
							id: 'rate-shipping',
							displayName: 'Shipping',
							amount: 500,
						},
					],
					shippingAddressRequired: true,
				} )
			);
			await group.button.emit( 'cancel' );
		}
	} );

	it.each( [ true, false ] )(
		'discards an older initialization response (old response first: %s)',
		async ( olderFirst ) => {
			params.cart = null;
			const initial = deferred();
			const updated = deferred();
			mockGetCartDetails
				.mockReturnValueOnce( initial.promise )
				.mockReturnValueOnce( updated.promise );
			loadEntrypoint();
			trigger( 'updated_checkout' );
			if ( olderFirst ) {
				initial.resolve(
					cartResponse( {
						total: '1000',
						currency: 'EUR',
						shipping: false,
					} )
				);
				await settle();
			}
			expect( groups ).toHaveLength( 0 );
			updated.resolve( cartResponse() );
			await settle();
			initial.resolve(
				cartResponse( {
					total: '1000',
					currency: 'EUR',
					shipping: false,
				} )
			);
			await settle();
			expect( groups ).toHaveLength( 1 );
			expect( groups[ 0 ].options ).toMatchObject( {
				amount: 2500,
				currency: 'usd',
			} );
			expect( groups[ 0 ].button.destroy ).not.toHaveBeenCalled();
		}
	);

	it( 'invalidates a pending response as soon as another checkout recalculation starts', async () => {
		const previous = deferred();
		mockGetCartDetails.mockReturnValueOnce( previous.promise );
		loadEntrypoint();
		trigger( 'updated_checkout' );
		trigger( 'update_checkout' );
		previous.resolve( cartResponse() );
		await settle();
		expect( groups[ 0 ].update ).not.toHaveBeenCalled();
		trigger( 'updated_checkout' );
		await jest.advanceTimersByTimeAsync( 300 );
		expect( groups[ 0 ].options.amount ).toBe( 2500 );
	} );

	it( 'invalidates a response during the debounce window before the next fetch starts', async () => {
		const previous = deferred();
		mockGetCartDetails.mockReturnValueOnce( previous.promise );
		loadEntrypoint();
		trigger( 'updated_checkout' );
		trigger( 'updated_checkout' );
		previous.resolve( cartResponse( { total: '1000' } ) );
		await settle();
		expect( mockGetCartDetails ).toHaveBeenCalledTimes( 1 );
		expect( groups[ 0 ].update ).not.toHaveBeenCalled();
		await jest.advanceTimersByTimeAsync( 300 );
		expect( mockGetCartDetails ).toHaveBeenCalledTimes( 2 );
		expect( groups[ 0 ].options.amount ).toBe( 2500 );
	} );

	it.each( [ 'currency', 'shipping' ] )(
		'rebuilds cleanly when the first update changes %s',
		async ( change ) => {
			loadEntrypoint();
			mockGetCartDetails.mockResolvedValue(
				cartResponse( {
					currency: change === 'currency' ? 'EUR' : 'USD',
					shipping: change !== 'shipping',
				} )
			);
			trigger( 'updated_checkout' );
			await settle();
			expect( groups ).toHaveLength( 2 );
			expect( groups[ 0 ].button.destroy ).toHaveBeenCalledTimes( 1 );
			expect(
				document.querySelectorAll(
					'#wc-stripe-express-checkout-element > div'
				)
			).toHaveLength( 1 );
			expect( groups[ 1 ].options.currency ).toBe(
				change === 'currency' ? 'eur' : 'usd'
			);
		}
	);

	it( 'keeps an unavailable wallet and the separator hidden after the first update', async () => {
		loadEntrypoint();
		await ready( groups[ 0 ], false );
		trigger( 'updated_checkout' );
		await settle();
		expect( groups ).toHaveLength( 1 );
		expect( groups[ 0 ].button.mount ).toHaveBeenCalledTimes( 1 );
		expect(
			jq( '#wc-stripe-express-checkout-button-separator' ).css(
				'display'
			)
		).toBe( 'none' );
	} );

	it( 'does not initialize a zero-total cart without a free trial', () => {
		params.cart.total = 0;
		loadEntrypoint();
		expect( groups ).toHaveLength( 0 );
	} );

	it( 'initializes a zero-total free trial in subscription mode', async () => {
		params.cart.total = 0;
		params.has_free_trial = true;
		loadEntrypoint();
		expect( groups ).toHaveLength( 1 );
		expect( groups[ 0 ].options ).toMatchObject( {
			amount: 0,
			mode: 'subscription',
		} );
		await ready( groups[ 0 ] );
		expect(
			jq( '#wc-stripe-express-checkout-element' ).css( 'display' )
		).not.toBe( 'none' );
	} );

	it( 'does not let a late ready event reveal a zero-total cart and reuses it when payable again', async () => {
		loadEntrypoint();
		mockGetCartDetails.mockResolvedValueOnce(
			cartResponse( { total: '0' } )
		);
		trigger( 'updated_checkout' );
		await settle();
		await ready( groups[ 0 ] );
		expect(
			jq( '#wc-stripe-express-checkout-element' ).css( 'display' )
		).toBe( 'none' );
		expect(
			jq( '#wc-stripe-express-checkout-button-separator' ).css(
				'display'
			)
		).toBe( 'none' );
		trigger( 'updated_checkout' );
		await jest.advanceTimersByTimeAsync( 300 );
		expect( groups ).toHaveLength( 1 );
		expect( groups[ 0 ].options.amount ).toBe( 2500 );
		expect(
			jq( '#wc-stripe-express-checkout-element' ).css( 'display' )
		).not.toBe( 'none' );
	} );

	it( 'does not resolve a click until checkout recalculation and its cart refresh finish', async () => {
		loadEntrypoint();
		const update = deferred();
		mockGetCartDetails.mockReturnValueOnce( update.promise );
		const event = { resolve: jest.fn() };
		trigger( 'update_checkout' );
		await groups[ 0 ].button.emit( 'click', event );
		expect( event.resolve ).not.toHaveBeenCalled();
		trigger( 'updated_checkout' );
		await groups[ 0 ].button.emit( 'click', event );
		expect( event.resolve ).not.toHaveBeenCalled();
		update.resolve( cartResponse() );
		await settle();
		await groups[ 0 ].button.emit( 'click', event );
		expect( event.resolve ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'defers the first update while a wallet is open and refreshes on cancel', async () => {
		loadEntrypoint();
		await groups[ 0 ].button.emit( 'click', { resolve: jest.fn() } );
		trigger( 'updated_checkout' );
		await settle();
		expect( mockGetCartDetails ).not.toHaveBeenCalled();
		expect( groups[ 0 ].button.destroy ).not.toHaveBeenCalled();
		await groups[ 0 ].button.emit( 'cancel' );
		await settle();
		expect( groups ).toHaveLength( 1 );
		expect( groups[ 0 ].options.amount ).toBe( 2500 );
	} );

	it( 'fails confirmation when checkout changed under the open wallet, then refreshes for retry', async () => {
		loadEntrypoint();
		await groups[ 0 ].button.emit( 'click', { resolve: jest.fn() } );
		trigger( 'updated_checkout' );
		const event = { paymentFailed: jest.fn() };
		await groups[ 0 ].button.emit( 'confirm', event );
		expect( event.paymentFailed ).toHaveBeenCalledWith( {
			reason: 'fail',
		} );
		expect(
			require( 'wcstripe/express-checkout/event-handler' )
				.onConfirmHandler
		).not.toHaveBeenCalled();
		await settle();
		expect( groups[ 0 ].options.amount ).toBe( 2500 );
	} );

	it( 'keeps a failed refresh unpayable and recovers on the next update', async () => {
		loadEntrypoint();
		mockGetCartDetails.mockRejectedValueOnce(
			new Error( 'Network error' )
		);
		trigger( 'updated_checkout' );
		await settle();
		const event = { resolve: jest.fn() };
		await groups[ 0 ].button.emit( 'click', event );
		expect( event.resolve ).not.toHaveBeenCalled();
		trigger( 'updated_checkout' );
		await jest.advanceTimersByTimeAsync( 300 );
		await groups[ 0 ].button.emit( 'click', event );
		expect( event.resolve ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'submits the reused Elements and current wallet payment data after the first update', async () => {
		const handlers = require( 'wcstripe/express-checkout/event-handler' );
		const actualHandlers = jest.requireActual(
			'wcstripe/express-checkout/event-handler'
		);
		handlers.onConfirmHandler.mockImplementation( ( options ) =>
			actualHandlers.onConfirmHandler( {
				...options,
				completePayment: jest.fn(),
			} )
		);
		loadEntrypoint();
		const api = require( '../../../api' ).mock.results[ 0 ].value;
		api.expressCheckoutNormalizeAddress = jest.fn().mockResolvedValue( {} );
		api.expressCheckoutECECreateOrder = jest.fn().mockResolvedValue( {
			payment_result: {
				payment_status: 'success',
				redirect_url: '/order-received/',
			},
		} );
		api.confirmIntent = jest.fn().mockReturnValue( true );
		stripe.createPaymentMethod = jest
			.fn()
			.mockResolvedValue( { paymentMethod: { id: 'pm_current' } } );

		trigger( 'updated_checkout' );
		await settle();
		await groups[ 0 ].button.emit( 'click', { resolve: jest.fn() } );
		const event = {
			expressPaymentType: 'google_pay',
			billingDetails: {
				name: 'Current Shopper',
				email: 'current@example.test',
				address: {
					country: 'US',
					postal_code: '10001',
					line1: 'Current billing address',
				},
			},
			shippingAddress: {
				name: 'Current Shopper',
				address: {
					country: 'US',
					postal_code: '10002',
					line1: 'Current shipping address',
				},
			},
			paymentFailed: jest.fn(),
		};
		await groups[ 0 ].button.emit( 'confirm', event );
		expect( groups ).toHaveLength( 1 );
		expect( groups[ 0 ].options ).toMatchObject( {
			amount: 2500,
			currency: 'usd',
		} );
		expect( groups[ 0 ].submit ).toHaveBeenCalledTimes( 1 );
		expect( stripe.createPaymentMethod ).toHaveBeenCalledWith( {
			elements: groups[ 0 ],
		} );
		expect( api.expressCheckoutECECreateOrder ).toHaveBeenCalledWith(
			expect.objectContaining( {
				billing_address: expect.objectContaining( {
					email: 'current@example.test',
					address_1: 'Current billing address',
				} ),
				shipping_address: expect.objectContaining( {
					address_1: 'Current shipping address',
					postcode: '10002',
				} ),
				payment_data: expect.arrayContaining( [
					{ key: 'wc-stripe-payment-method', value: 'pm_current' },
					{ key: 'express_payment_type', value: 'google_pay' },
				] ),
			} )
		);
		expect( event.paymentFailed ).not.toHaveBeenCalled();
	} );

	it( 'does not apply an outstanding cart response to an open wallet', async () => {
		params.is_checkout_page = false;
		params.is_cart_page = true;
		const previous = deferred();
		mockGetCartDetails.mockReturnValueOnce( previous.promise );
		loadEntrypoint();
		trigger( 'updated_cart_totals' );
		await groups[ 0 ].button.emit( 'click', { resolve: jest.fn() } );
		previous.resolve(
			cartResponse( { currency: 'EUR', shipping: false } )
		);
		await settle();
		expect( groups ).toHaveLength( 1 );
		expect( groups[ 0 ].update ).not.toHaveBeenCalled();
		expect( groups[ 0 ].button.destroy ).not.toHaveBeenCalled();
		await groups[ 0 ].button.emit( 'cancel' );
		await settle();
		expect( groups[ 0 ].options.amount ).toBe( 2500 );
	} );

	it( 'does not initialize the classic entrypoint on Blocks checkout', () => {
		params.has_block = true;
		loadEntrypoint();
		expect( groups ).toHaveLength( 0 );
		expect( mockGetCartDetails ).not.toHaveBeenCalled();
	} );
} );
