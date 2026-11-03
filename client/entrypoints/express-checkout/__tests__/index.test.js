/**
 * Tests for the cart/checkout bootstrap path in the Express Checkout entrypoint.
 *
 * The entrypoint is an `jQuery( ( $ ) => { … } )` IIFE with no exported surface,
 * so these tests load it for its side effects and assert on the one observable
 * signal that distinguishes the bootstrap path from the legacy AJAX path: whether
 * `expressCheckoutGetCartDetails()` (the `GET /wc/store/v1/cart` fetch) fires.
 */

const mockGetCartDetails = jest.fn();
let mockDelayReady = false;
const mockReadyCallbacks = [];
const mockGetSelectedProductData = jest.fn();
const mockGetStripe = jest.fn();
const mockAddToCart = jest.fn();
const mockEmptyCartLegacy = jest.fn();
const mockTransformLabeledDisplayItems = jest.fn( () => [] );

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
	const syncReadyJQuery = ( arg, ...args ) => {
		if ( typeof arg === 'function' ) {
			if ( mockDelayReady ) {
				mockReadyCallbacks.push( () => arg( syncReadyJQuery ) );
				return;
			}
			return arg( syncReadyJQuery );
		}
		return actualJQuery( arg, ...args );
	};
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
	transformLabeledDisplayItems: mockTransformLabeledDisplayItems,
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

// Stripe button stub that captures the bound event handlers so tests can
// invoke them directly. The variation-breakdown describe keeps its own richer
// stub, which also collects every created Elements group.
const stubStripeButtonHandlers = () => {
	const handlers = {};
	const button = {
		on: ( eventName, callback ) => {
			handlers[ eventName ] = callback;
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

describe( 'Express Checkout cart/checkout bootstrap', () => {
	beforeEach( () => {
		// Reset module state so the consume-once `cartBootstrapConsumed` flag
		// starts fresh for every test.
		jest.resetModules();
		mockGetCartDetails.mockReset();
		mockTransformLabeledDisplayItems.mockClear();
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
		delete global.wcStripeExpressCheckoutPayForOrderParams;
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
		expect( mockTransformLabeledDisplayItems ).toHaveBeenCalledWith(
			[],
			1500
		);
	} );

	it( 'passes the order total when transforming Pay for Order items', () => {
		global.wc_stripe_express_checkout_params = {
			...baseParams(),
			is_pay_for_order: true,
			is_cart_page: false,
		};
		global.wcStripeExpressCheckoutPayForOrderParams = {
			total: { amount: 1500 },
			currency: 'usd',
			displayItems: [ { label: 'Subtotal', amount: 1500 } ],
			order: 123,
			orderDetails: {
				orderKey: 'wc_order_test',
				billingEmail: 'customer@example.com',
			},
		};

		loadEntrypoint();

		expect( mockTransformLabeledDisplayItems ).toHaveBeenCalledWith(
			[ { label: 'Subtotal', amount: 1500 } ],
			1500
		);
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
			i18n: {
				go_to_checkout:
					'Please go to the <a href="https://example.com/checkout/">checkout page</a>.',
			},
		};
	} );

	afterEach( () => {
		delete global.wc_stripe_express_checkout_params;
	} );

	// The order-side abort used to skip paymentFailed(), leaving the approved wallet
	// sheet open with nothing on screen. The third argument is the removed
	// `isOrderError` opt-out: passing it must change nothing.
	it.each( [
		[ 'plain message', 'Order creation error', true ],
		[
			'checkout link',
			'Size <XL> is a required field.',
			{ linkToCheckout: true },
		],
	] )(
		'fails the wallet sheet and shows the %s when the order errors',
		async ( _name, message, options ) => {
			const handlers = stubStripeButtonHandlers();
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
			abortPayment( event, message, options );

			expect( event.paymentFailed ).toHaveBeenCalledWith( {
				reason: 'fail',
			} );
			const notice = document.querySelector( '.woocommerce-error' );
			expect(
				notice.querySelector( 'a' )?.getAttribute( 'href' ) ?? null
			).toBe(
				options.linkToCheckout ? 'https://example.com/checkout/' : null
			);
			// A tag-like label stays visible as text, and the sentence sits on its
			// own line -- textContent reads them as one run because <br> has no text.
			expect( notice.innerHTML ).toBe(
				options.linkToCheckout
					? 'Size &lt;XL&gt; is a required field.<br>Please go to the <a href="https://example.com/checkout/">checkout page</a>.'
					: message
			);

			// The message has to be in front of the shopper before the sheet closes.
			expect(
				onAbortPaymentHandler.mock.invocationCallOrder[ 0 ]
			).toBeLessThan( event.paymentFailed.mock.invocationCallOrder[ 0 ] );
		}
	);
} );

describe( 'Express Checkout pay-for-order phone collection', () => {
	beforeEach( () => {
		jest.resetModules();
		mockGetStripe.mockReset();

		document.body.innerHTML =
			'<div id="wc-stripe-express-checkout-element"></div>';

		// The order-pay page carries two localized payloads whose exact names
		// (and inconsistent casing) come from the PHP side: the shared ECE
		// config `wc_stripe_express_checkout_params`, set per test below, and
		// this pay-for-order-only payload from
		// `localize_pay_for_order_page_scripts()`.
		global.wcStripeExpressCheckoutPayForOrderParams = {
			order: '660',
			orderDetails: {
				orderKey: 'wc_order_test',
				billingEmail: 'jane.doe@example.com',
				shippingAddress: {},
			},
			displayItems: [],
			currency: 'usd',
			total: { amount: 4000 },
		};
	} );

	afterEach( () => {
		delete global.wc_stripe_express_checkout_params;
		delete global.wcStripeExpressCheckoutPayForOrderParams;
	} );

	const payForOrderParams = ( needsPayerPhone ) => ( {
		...baseParams(),
		is_cart_page: false,
		is_pay_for_order: true,
		stripe: {
			publishable_key: 'pk_test_123',
			locale: 'en',
			is_apple_pay_enabled: true,
		},
		checkout: { currency_code: 'usd', needs_payer_phone: needsPayerPhone },
	} );

	// The order-pay Store API route replaces the order's billing address with the
	// wallet payload before validating it, so if the sheet never collects a phone
	// the payment 400s whenever the checkout phone field is required.
	it.each`
		fieldState      | needsPayerPhone
		${ 'required' } | ${ true }
		${ 'optional' } | ${ false }
	`(
		'mirrors phoneNumberRequired ($needsPayerPhone) to the wallet when the checkout phone field is $fieldState',
		async ( { needsPayerPhone } ) => {
			global.wc_stripe_express_checkout_params =
				payForOrderParams( needsPayerPhone );

			const handlers = stubStripeButtonHandlers();
			loadEntrypoint();

			const event = {
				resolve: jest.fn(),
				expressPaymentType: 'applePay',
			};
			await handlers.click( event );

			expect( event.resolve ).toHaveBeenCalledTimes( 1 );
			expect(
				event.resolve.mock.calls[ 0 ][ 0 ].phoneNumberRequired
			).toBe( needsPayerPhone );
		}
	);
} );

describe( 'Classic early start with checkout reconstruction', () => {
	let jq;
	let groups;
	let params;
	const settle = () => jest.advanceTimersByTimeAsync( 0 );
	const trigger = ( name ) => jq( document.body ).trigger( name );
	const addContainer = () => {
		document.body.insertAdjacentHTML(
			'beforeend',
			'<div id="wc-stripe-express-checkout-element" style="display:none"></div>' +
				'<div id="wc-stripe-express-checkout-button-separator" style="display:none"></div>'
		);
	};
	const cart = ( amount = '2000' ) => ( {
		items: [],
		totals: {
			total_price: amount,
			total_shipping: '0',
			currency_code: 'USD',
			currency_minor_unit: 2,
		},
		needs_shipping: false,
	} );
	const pending = () => {
		let resolve;
		const promise = new Promise( ( res ) => {
			resolve = res;
		} );
		return { promise, resolve };
	};
	beforeEach( () => {
		jest.resetModules();
		jest.useFakeTimers();
		mockGetCartDetails.mockReset().mockResolvedValue( cart() );
		mockGetStripe.mockReset();
		jq = require( 'jquery' );
		groups = [];
		mockGetStripe.mockReturnValue( {
			elements: jest.fn( ( options ) => {
				const handlers = {};
				const id = groups.length;
				const button = {
					on: jest.fn( ( name, callback ) => {
						handlers[ name ] = [
							...( handlers[ name ] ?? [] ),
							callback,
						];
						return button;
					} ),
					emit: ( name, data ) =>
						Promise.all(
							( handlers[ name ] ?? [] ).map( ( fn ) =>
								fn( data )
							)
						),
					mount: jest.fn( ( selector ) => {
						document.querySelector(
							selector
						).innerHTML = `<iframe data-wallet-generation="${ id }"></iframe>`;
					} ),
					destroy: jest.fn(),
				};
				const group = {
					options,
					button,
					create: () => button,
					update: jest.fn(),
				};
				groups.push( group );
				return group;
			} ),
		} );
		const transformers = require( 'wcstripe/express-checkout/transformers/wc-to-stripe' );
		const actual = jest.requireActual(
			'wcstripe/express-checkout/transformers/wc-to-stripe'
		);
		Object.keys( transformers ).forEach( ( name ) =>
			transformers[ name ].mockImplementation( actual[ name ] )
		);
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
				requestShipping: false,
				requestPhone: false,
				displayItems: [],
			},
		};
		global.wc_stripe_express_checkout_params = params;
		global.wc_stripe_upe_params = { isOCEnabled: false };
		global.Stripe = jest.fn();
		document.body.innerHTML = '';
		addContainer();
	} );
	afterEach( () => {
		jest.useRealTimers();
		mockDelayReady = false;
		mockReadyCallbacks.length = 0;
		delete global.wc_stripe_express_checkout_params;
		delete global.wc_stripe_upe_params;
		delete global.Stripe;
	} );
	it( 'mounts before document readiness without a second initialization', () => {
		mockDelayReady = true;
		loadEntrypoint();
		expect( document.querySelectorAll( 'iframe' ) ).toHaveLength( 1 );
		mockReadyCallbacks.forEach( ( fn ) => fn() );
		expect( groups ).toHaveLength( 1 );
	} );
	it( 'waits for markup inserted after document readiness', async () => {
		mockDelayReady = true;
		document.body.innerHTML = '';
		loadEntrypoint();
		mockReadyCallbacks.forEach( ( fn ) => fn() );
		expect( groups ).toHaveLength( 0 );
		addContainer();
		await settle();
		expect( document.querySelectorAll( 'iframe' ) ).toHaveLength( 1 );
	} );
	it( 'does not react to unrelated mutations or keep watching after mounting', async () => {
		document.body.innerHTML = '';
		loadEntrypoint();
		document.body.appendChild( document.createElement( 'div' ) );
		await settle();
		expect( groups ).toHaveLength( 0 );
		addContainer();
		await settle();
		expect( groups ).toHaveLength( 1 );
		document
			.querySelector( '#wc-stripe-express-checkout-element' )
			.remove();
		addContainer();
		await settle();
		expect( groups ).toHaveLength( 1 );
	} );
	it( 'rebuilds new Elements and button markup after updated_checkout', async () => {
		loadEntrypoint();
		const original = document.querySelector( 'iframe' );
		trigger( 'updated_checkout' );
		await settle();
		expect( groups ).toHaveLength( 2 );
		expect( groups[ 1 ].options.amount ).toBe( 2000 );
		expect( document.querySelector( 'iframe' ) ).not.toBe( original );
		expect( groups[ 0 ].update ).not.toHaveBeenCalled();
	} );
	it( 'waits for a pending review and fetches fresh totals for late markup', async () => {
		document.body.innerHTML = '';
		loadEntrypoint();
		trigger( 'update_checkout' );
		addContainer();
		await settle();
		expect( groups ).toHaveLength( 0 );
		trigger( 'updated_checkout' );
		await settle();
		expect( groups ).toHaveLength( 1 );
		expect( groups[ 0 ].options.amount ).toBe( 2000 );
	} );
	it( 'does not open or confirm a wallet while totals are stale', async () => {
		loadEntrypoint();
		trigger( 'update_checkout' );
		const click = { resolve: jest.fn(), expressPaymentType: 'googlePay' };
		await groups[ 0 ].button.emit( 'click', click );
		expect( click.resolve ).not.toHaveBeenCalled();
		const confirm = { paymentFailed: jest.fn() };
		await groups[ 0 ].button.emit( 'confirm', confirm );
		expect( confirm.paymentFailed ).toHaveBeenCalledWith( {
			reason: 'fail',
		} );
	} );
	it( 'ignores an older cart response after a newer checkout update', async () => {
		const first = pending();
		const second = pending();
		mockGetCartDetails
			.mockReturnValueOnce( first.promise )
			.mockReturnValueOnce( second.promise );
		loadEntrypoint();
		trigger( 'updated_checkout' );
		trigger( 'updated_checkout' );
		second.resolve( cart( '3000' ) );
		await settle();
		first.resolve( cart( '2000' ) );
		await settle();
		expect( groups ).toHaveLength( 2 );
		expect( groups[ 1 ].options.amount ).toBe( 3000 );
	} );
	it( 'rebuilds with fresh totals after stale confirmation closes an open wallet', async () => {
		loadEntrypoint();
		await groups[ 0 ].button.emit( 'click', {
			resolve: jest.fn(),
			expressPaymentType: 'googlePay',
		} );
		trigger( 'update_checkout' );
		trigger( 'updated_checkout' );
		await settle();
		expect( groups ).toHaveLength( 1 );
		await groups[ 0 ].button.emit( 'confirm', {
			paymentFailed: jest.fn(),
		} );
		await settle();
		expect( groups ).toHaveLength( 2 );
		expect( groups[ 1 ].options.amount ).toBe( 2000 );
	} );

	it( 'ignores clicks from a replaced wallet after fresh buttons are ready', async () => {
		loadEntrypoint();
		trigger( 'updated_checkout' );
		await settle();
		const click = { resolve: jest.fn(), expressPaymentType: 'googlePay' };
		await groups[ 0 ].button.emit( 'click', click );
		expect( click.resolve ).not.toHaveBeenCalled();
	} );

	test( 'uses fresh cart data when totals changed before late markup appeared', async () => {
		document.body.innerHTML = '';
		loadEntrypoint();
		trigger( 'updated_cart_totals' );
		addContainer();
		await settle();
		expect( groups ).toHaveLength( 1 );
		expect( groups[ 0 ].options.amount ).toBe( 2000 );
	} );

	it( 'keeps the legacy update gate when gateway configuration is still missing at readiness', async () => {
		delete global.wc_stripe_upe_params;
		loadEntrypoint();
		expect( groups ).toHaveLength( 0 );
		global.wc_stripe_upe_params = { isOCEnabled: false };
		trigger( 'updated_checkout' );
		await settle();
		expect( groups ).toHaveLength( 1 );
	} );

	test( 'leaves late cart snapshots on non-Classic pages with their legacy behavior', async () => {
		params.is_checkout_page = false;
		params.is_cart_page = true;
		params.cart = null;
		loadEntrypoint();
		await settle();
		params.cart = {
			total: 1500,
			currency: 'usd',
			requestShipping: false,
			displayItems: [],
		};
		trigger( 'updated_cart_totals' );
		await settle();
		expect( mockGetCartDetails ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'keeps the optimized checkout path on its existing ready and update events', async () => {
		mockDelayReady = true;
		global.wc_stripe_upe_params.isOCEnabled = true;
		loadEntrypoint();
		expect( groups ).toHaveLength( 0 );
		mockReadyCallbacks.forEach( ( fn ) => fn() );
		expect( groups ).toHaveLength( 0 );
		trigger( 'updated_checkout' );
		await settle();
		expect( groups ).toHaveLength( 1 );
	} );
} );
