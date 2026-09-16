import * as paymentProcessing from '../payment-processing';
import * as stripeUtils from 'wcstripe/stripe-utils';

const { hasEmptyRequiredFields } = paymentProcessing;

jest.mock( 'wcstripe/stripe-utils', () => ( {
	appendCheckoutSessionIdToForm: jest.fn(),
	appendPaymentIntentIdToForm: jest.fn(),
	appendPaymentMethodIdToForm: jest.fn(),
	appendSetupIntentToForm: jest.fn(),
	getAdditionalSetupIntentData: jest.fn().mockReturnValue( {} ),
	getBillingDetailsForDeferredFlow: jest.fn().mockReturnValue( null ),
	getDefaultValues: jest.fn().mockReturnValue( {} ),
	getExcludedPaymentMethodTypes: jest.fn().mockReturnValue( [] ),
	getExcludedPaymentMethodTypesForBillingCountry: jest
		.fn()
		.mockReturnValue( [] ),
	getCurrentBillingCountry: jest.fn().mockReturnValue( '' ),
	getPaymentMethodTypes: jest.fn().mockReturnValue( [ 'card' ] ),
	getUserDataForCheckoutSession: jest.fn().mockReturnValue( {} ),
	normalizeReturnUrl: jest.requireActual(
		'wcstripe/stripe-utils/normalize-return-url'
	).normalizeReturnUrl,
	getStripeServerData: jest.fn().mockReturnValue( {
		paymentMethodsConfig: {
			card: { supportsDeferredIntent: true },
		},
		isAdaptivePricingEnabled: false,
		cartTotal: 1000,
		currency: 'USD',
		isPaymentNeeded: true,
		shouldShowOptimizedCheckout: false,
	} ),
	getUpeSettings: jest.fn().mockReturnValue( {} ),
	getStaleCheckoutTotalMessage: jest
		.fn()
		.mockReturnValue(
			"We couldn't update your order total. Please refresh the page and try again."
		),
	clearStaleCheckoutTotalNotice: jest.fn(),

	isLinkEnabled: jest.fn().mockReturnValue( false ),
	resetBlockCheckoutPaymentState: jest.fn(),
	showErrorCheckout: jest.fn(),
	showErrorPaymentMethod: jest.fn(),
	unblockBlockCheckout: jest.fn(),
	validateBlikCode: jest.fn(),
} ) );

jest.mock( 'wcstripe/stripe-utils/upe-appearance', () => ( {
	initializeUPEAppearance: jest.fn().mockReturnValue( {} ),
	invalidateAppearanceCache: jest.fn(),
} ) );

jest.mock( 'wcstripe/styles/upe', () => ( {
	getFontRulesFromPage: jest.fn().mockReturnValue( [] ),
} ) );

jest.mock(
	'wcstripe/optimized-checkout/handle-display-of-payment-instructions',
	() => ( {
		handleDisplayOfPaymentInstructions: jest.fn(),
	} )
);

jest.mock(
	'wcstripe/optimized-checkout/handle-display-of-saving-checkbox',
	() => ( {
		handleDisplayOfSavingCheckbox: jest.fn(),
	} )
);
// Retrieve the stable mock functions created inside the jest.mock factory
// (jest.mock is hoisted above const declarations, so they cannot be referenced
// inside the factory directly without hitting the TDZ).
const mockJQueryAjax = jest.requireMock( 'jquery' ).ajax;
const mockJQueryTrigger = jest.fn();

// Mock the 'jquery' module so the imported jQuery in payment-processing.js
// uses the same mock as assertions below. global.jQuery is also set for any
// code that accesses window.jQuery directly.
jest.mock( 'jquery', () => {
	// Record block/unblock per selector on globalThis so counts survive
	// jest.resetModules() (which otherwise desyncs the mock instance the code
	// under test captured at import from the one a test would grab here).
	const jq = jest.fn( ( selector ) => {
		const chain = {
			on: jest.fn(),
			trigger: jest.fn(),
			addClass: jest.fn( () => chain ),
			removeClass: jest.fn( () => chain ),
			block: jest.fn( () => {
				if ( typeof selector === 'string' ) {
					global.__wcStripeBlockCounts ??= {};
					global.__wcStripeBlockCounts[ selector ] =
						( global.__wcStripeBlockCounts[ selector ] ?? 0 ) + 1;
				}
				return chain;
			} ),
			unblock: jest.fn( () => {
				if ( typeof selector === 'string' ) {
					global.__wcStripeUnblockCounts ??= {};
					global.__wcStripeUnblockCounts[ selector ] =
						( global.__wcStripeUnblockCounts[ selector ] ?? 0 ) + 1;
				}
				return chain;
			} ),
		};
		return chain;
	} );
	jq.ajax = jest.fn();
	return jq;
} );

global.jQuery = Object.assign(
	jest.fn( () => ( { trigger: mockJQueryTrigger } ) ),
	{
		ajax: mockJQueryAjax,
	}
);

// Silence console.error for tests that intentionally trigger error paths.
beforeEach( () => {
	jest.spyOn( console, 'error' ).mockImplementation( () => {} );
} );

afterEach( () => {
	// eslint-disable-next-line no-console
	console.error.mockRestore();
} );

// Flush the fire-and-forget async IIFE inside processPayment.
const flushPromises = async () => {
	for ( let i = 0; i < 20; i++ ) {
		// eslint-disable-next-line no-await-in-loop
		await Promise.resolve();
	}
};

const BASE_SERVER_DATA = {
	paymentMethodsConfig: {
		card: { supportsDeferredIntent: true, title: 'Card' },
	},
	currency: 'usd',
	cartTotal: 1000,
	isCheckout: true,
	shouldShowOptimizedCheckout: false,
	isPaymentNeeded: true,
	isChangingPayment: false,
};

const createMockPaymentElement = () => ( {
	mount: jest.fn(),
	on: jest.fn(),
	update: jest.fn(),
} );

const MOCK_AP_CHECKOUT_CLIENT_SECRET = 'cs_test_ap_client_secret';
const MOCK_AP_CHECKOUT_SESSION_ID = 'cs_test_abc';

const setNativeCheckoutSessionData = ( overrides = {} ) => {
	let dataElement = document.getElementById(
		'wc-stripe-checkout-session-data'
	);
	if ( ! dataElement ) {
		dataElement = document.createElement( 'div' );
		dataElement.id = 'wc-stripe-checkout-session-data';
		document.body.appendChild( dataElement );
	}

	dataElement.dataset.checkoutSession = JSON.stringify( {
		session_id: MOCK_AP_CHECKOUT_SESSION_ID,
		client_secret: MOCK_AP_CHECKOUT_CLIENT_SECRET,
		revision: 'rev_1',
		status: 'success',
		message: '',
		...overrides,
	} );
};

const createMockElements = () => {
	const checkoutActions = {
		runServerUpdate: jest.fn( async ( userFunction ) => {
			await userFunction();
			return {
				type: 'success',
				session: { id: MOCK_AP_CHECKOUT_SESSION_ID },
			};
		} ),
		getSession: jest.fn( () => Promise.resolve( {} ) ),
		confirm: jest.fn( () => Promise.resolve( {} ) ),
	};
	return {
		create: jest.fn( () => createMockPaymentElement() ),
		update: jest.fn(),
		submit: jest.fn( () => Promise.resolve( {} ) ),
		loadActions: jest.fn( () =>
			Promise.resolve( { type: 'success', actions: checkoutActions } )
		),
		checkoutActions,
		createPaymentElement: jest.fn( () => createMockPaymentElement() ),
		createCurrencySelectorElement: jest.fn( () => ( {
			mount: jest.fn(),
		} ) ),
	};
};

const createMockApi = ( checkoutElements ) => {
	const standardElements = createMockElements();
	const stripe = {
		elements: jest.fn( () => standardElements ),
		initCheckoutElementsSdk: jest.fn( () =>
			Promise.resolve( checkoutElements )
		),
		createPaymentMethod: jest.fn( () =>
			Promise.resolve( { paymentMethod: { id: 'pm_test_123' } } )
		),
	};
	return {
		getStripe: jest.fn( () => stripe ),
		checkoutSessionsCreateSession: jest.fn( () =>
			Promise.resolve( {
				data: {
					client_secret: MOCK_AP_CHECKOUT_CLIENT_SECRET,
					session_id: MOCK_AP_CHECKOUT_SESSION_ID,
				},
			} )
		),
		checkoutSessionsUpdateSession: jest.fn( () =>
			Promise.resolve( { success: true } )
		),
		getAjaxUrl: jest.fn( () => '/?wc-ajax=checkout' ),
		createIntent: jest.fn(),
		initSetupIntent: jest.fn(),
		_stripe: stripe,
		_standardElements: standardElements,
	};
};

const createMockForm = ( {
	savePaymentMethodChecked = false,
	name = 'checkout',
} = {} ) => {
	const f = {};
	f.addClass = jest.fn( () => f );
	f.removeClass = jest.fn( () => f );
	f.block = jest.fn( () => f );
	f.unblock = jest.fn( () => f );
	f.trigger = jest.fn( () => f );
	f.attr = jest.fn( () => name );
	f.serialize = jest.fn( () => 'billing_first_name=John' );
	f.append = jest.fn();
	f.find = jest.fn( () => ( {
		is: jest.fn( () => savePaymentMethodChecked ),
	} ) );
	return f;
};

const buildMockStripe = () => {
	const mockCreate = jest
		.fn()
		.mockReturnValue( { mount: jest.fn(), on: jest.fn() } );
	const mockElements = { create: mockCreate };
	return {
		stripe: { elements: jest.fn().mockReturnValue( mockElements ) },
		mockElements,
	};
};

const buildApi = ( stripe ) => ( {
	getStripe: jest.fn().mockReturnValue( stripe ),
} );

describe( 'payment-processing', () => {
	afterEach( () => {
		jest.resetModules();
	} );

	describe( 'adaptive pricing disabled (isAdaptivePricingEnabled = false)', () => {
		beforeEach( () => {
			stripeUtils.getStripeServerData.mockReturnValue( {
				...BASE_SERVER_DATA,
				isAdaptivePricingEnabled: false,
			} );
			paymentProcessing.initializeUPEComponents();
		} );

		afterEach( () => {
			jest.clearAllMocks();
		} );

		describe( 'mountStripePaymentElement', () => {
			it( 'does not call loadActions when adaptive pricing is disabled', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';

				const component =
					await paymentProcessing.mountStripePaymentElement(
						api,
						dom
					);

				expect(
					api._stripe.initCheckoutElementsSdk
				).not.toHaveBeenCalled();
				expect( checkoutElements.loadActions ).not.toHaveBeenCalled();
				expect( component.hasLoadError ).toBe( false );
			} );
		} );

		describe( 'processPayment', () => {
			it( 'validates elements, creates a payment method, and submits form', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );

				// Ensure the same elements object is used by both mountStripePaymentElement
				// and processPayment (so submit() is on the same instance).
				api._stripe.elements.mockReturnValue( api._standardElements );

				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';
				await paymentProcessing.mountStripePaymentElement( api, dom );

				const form = createMockForm();
				paymentProcessing.processPayment( api, form, 'card' );
				await flushPromises();

				expect( api._standardElements.submit ).toHaveBeenCalled();
				expect(
					stripeUtils.appendPaymentMethodIdToForm
				).toHaveBeenCalledWith( form, 'pm_test_123' );
				expect( form.trigger ).toHaveBeenCalledWith( 'submit' );
			} );

			it( 'waits for an in-flight re-mount before validating and submitting', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				api._stripe.elements.mockReturnValue( api._standardElements );

				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';
				await paymentProcessing.mountStripePaymentElement( api, dom );

				// Submit while an `updated_checkout` re-mount is still in flight.
				let resolveMount;
				paymentProcessing.trackMountInProgress(
					new Promise( ( resolve ) => {
						resolveMount = resolve;
					} )
				);

				const form = createMockForm();
				paymentProcessing.processPayment( api, form, 'card' );
				await flushPromises();

				// Submission is held until the re-mount settles.
				expect( api._standardElements.submit ).not.toHaveBeenCalled();
				expect(
					stripeUtils.appendPaymentMethodIdToForm
				).not.toHaveBeenCalled();
				expect( form.trigger ).not.toHaveBeenCalledWith( 'submit' );

				// Re-mount completes; submission proceeds normally.
				resolveMount();
				await flushPromises();

				expect( api._standardElements.submit ).toHaveBeenCalled();
				expect(
					stripeUtils.appendPaymentMethodIdToForm
				).toHaveBeenCalledWith( form, 'pm_test_123' );
				expect( form.trigger ).toHaveBeenCalledWith( 'submit' );
			} );

			it( 'holds submission until all overlapping re-mounts settle, even out of order', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				api._stripe.elements.mockReturnValue( api._standardElements );

				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';
				await paymentProcessing.mountStripePaymentElement( api, dom );

				// Two overlapping `updated_checkout` re-mounts in flight.
				let resolveOlder;
				let resolveNewer;
				paymentProcessing.trackMountInProgress(
					new Promise( ( resolve ) => {
						resolveOlder = resolve;
					} )
				);
				paymentProcessing.trackMountInProgress(
					new Promise( ( resolve ) => {
						resolveNewer = resolve;
					} )
				);

				const form = createMockForm();
				paymentProcessing.processPayment( api, form, 'card' );
				await flushPromises();

				// Newer chain resolves first — submission must still be held.
				resolveNewer();
				await flushPromises();
				expect( api._standardElements.submit ).not.toHaveBeenCalled();
				expect(
					stripeUtils.appendPaymentMethodIdToForm
				).not.toHaveBeenCalled();
				expect( form.trigger ).not.toHaveBeenCalledWith( 'submit' );

				// Older re-mount finishes — now submission proceeds.
				resolveOlder();
				await flushPromises();
				expect( api._standardElements.submit ).toHaveBeenCalled();
				expect(
					stripeUtils.appendPaymentMethodIdToForm
				).toHaveBeenCalledWith( form, 'pm_test_123' );
				expect( form.trigger ).toHaveBeenCalledWith( 'submit' );
			} );

			it( 'on a deferred flow (non-checkout form), creates the payment method with billing_details from getBillingDetailsForDeferredFlow', async () => {
				const billingDetails = {
					name: 'John Doe',
					email: 'john@example.com',
					phone: '+1234567890',
					address: {
						country: 'US',
						line1: '123 Main St',
						city: 'New York',
						state: 'NY',
						postal_code: '10001',
					},
				};
				stripeUtils.getBillingDetailsForDeferredFlow.mockReturnValue(
					billingDetails
				);

				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				api._stripe.elements.mockReturnValue( api._standardElements );

				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';
				await paymentProcessing.mountStripePaymentElement( api, dom );

				// The pay-for-order form is #order_review, not named 'checkout'.
				const form = createMockForm( { name: 'order_review' } );
				paymentProcessing.processPayment( api, form, 'card' );
				await flushPromises();

				expect( api._stripe.createPaymentMethod ).toHaveBeenCalledWith(
					{
						elements: api._standardElements,
						params: { billing_details: billingDetails },
					}
				);
			} );

			it( 'on a deferred flow with no usable billing data, omits billing_details', async () => {
				stripeUtils.getBillingDetailsForDeferredFlow.mockReturnValue(
					null
				);

				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				api._stripe.elements.mockReturnValue( api._standardElements );

				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';
				await paymentProcessing.mountStripePaymentElement( api, dom );

				const form = createMockForm( { name: 'order_review' } );
				paymentProcessing.processPayment( api, form, 'card' );
				await flushPromises();

				expect( api._stripe.createPaymentMethod ).toHaveBeenCalledWith(
					{
						elements: api._standardElements,
						params: {},
					}
				);
			} );

			it( 'on the checkout form, ignores getBillingDetailsForDeferredFlow', async () => {
				stripeUtils.getBillingDetailsForDeferredFlow.mockReturnValue( {
					email: 'deferred@example.com',
				} );

				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				api._stripe.elements.mockReturnValue( api._standardElements );

				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';
				await paymentProcessing.mountStripePaymentElement( api, dom );

				const form = createMockForm( { name: 'checkout' } );
				paymentProcessing.processPayment( api, form, 'card' );
				await flushPromises();

				const callArg =
					api._stripe.createPaymentMethod.mock.calls[ 0 ][ 0 ];
				// On checkout, billing_details are read from the DOM, not the
				// deferred-flow helper.
				expect( callArg.params.billing_details ).toBeDefined();
				expect( callArg.params.billing_details.email ).not.toBe(
					'deferred@example.com'
				);
			} );

			it( 'shows an error and does not submit when hasLoadError is true', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';

				await paymentProcessing.mountStripePaymentElement( api, dom );

				// Trigger hasLoadError via the loaderror event on the UPE element.
				const createdEl =
					api._standardElements.create.mock.results[ 0 ]?.value;
				const loaderrorCall = createdEl?.on.mock.calls.find(
					( [ event ] ) => event === 'loaderror'
				);
				loaderrorCall?.[ 1 ]( {
					error: { message: 'Element failed to load' },
				} );

				const form = createMockForm();
				paymentProcessing.processPayment( api, form, 'card' );
				await flushPromises();

				expect( stripeUtils.showErrorCheckout ).toHaveBeenCalled();
				expect( form.trigger ).not.toHaveBeenCalledWith( 'submit' );
			} );
		} );
	} );

	describe( 'adaptive pricing enabled (isAdaptivePricingEnabled = true)', () => {
		beforeEach( () => {
			setNativeCheckoutSessionData();
			stripeUtils.getStripeServerData.mockReturnValue( {
				...BASE_SERVER_DATA,
				isAdaptivePricingEnabled: true,
			} );
			paymentProcessing.initializeUPEComponents();
		} );

		afterEach( () => {
			document
				.getElementById( 'wc-stripe-checkout-session-data' )
				?.remove();
			jest.clearAllMocks();
		} );

		describe( 'createStripePaymentElement (via mountStripePaymentElement)', () => {
			afterEach( () => {
				stripeUtils.getUpeSettings.mockReturnValue( {} );
			} );
			it( 'calls initCheckoutElementsSdk with the client_secret from the session', async () => {
				const checkoutElements = createMockElements();
				checkoutElements.loadActions.mockResolvedValue( {
					type: 'success',
				} );
				const api = createMockApi( checkoutElements );
				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';

				await paymentProcessing.mountStripePaymentElement( api, dom );

				expect(
					api.checkoutSessionsCreateSession
				).not.toHaveBeenCalled();
				expect(
					api._stripe.initCheckoutElementsSdk
				).toHaveBeenCalledWith(
					expect.objectContaining( {
						clientSecret: MOCK_AP_CHECKOUT_CLIENT_SECRET,
						elementsOptions: expect.objectContaining( {
							savedPaymentMethod: {
								enableRedisplay: 'never',
								enableSave: 'never',
							},
						} ),
					} )
				);
				expect( api._stripe.elements ).not.toHaveBeenCalled();
			} );

			it( 'uses createPaymentElement (not create) when using initCheckoutElementsSdk', async () => {
				const checkoutElements = createMockElements();
				checkoutElements.loadActions.mockResolvedValue( {
					type: 'success',
				} );
				const api = createMockApi( checkoutElements );
				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';

				await paymentProcessing.mountStripePaymentElement( api, dom );

				expect(
					checkoutElements.createPaymentElement
				).toHaveBeenCalled();
				expect( checkoutElements.create ).not.toHaveBeenCalled();
			} );

			it( 'merges getUpeSettings into createPaymentElement for adaptive pricing (avoid duplicate billing with confirm)', async () => {
				const billingFields = {
					billingDetails: {
						name: 'never',
						email: 'never',
						phone: 'auto',
						address: {
							country: 'never',
							line1: 'never',
							line2: 'never',
							city: 'never',
							state: 'never',
							postalCode: 'never',
						},
					},
				};
				stripeUtils.getUpeSettings.mockReturnValue( {
					fields: billingFields,
				} );
				const checkoutElements = createMockElements();
				checkoutElements.loadActions.mockResolvedValue( {
					type: 'success',
				} );
				const api = createMockApi( checkoutElements );
				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';

				await paymentProcessing.mountStripePaymentElement( api, dom );

				expect(
					checkoutElements.createPaymentElement
				).toHaveBeenCalledWith(
					expect.objectContaining( {
						fields: billingFields,
					} )
				);
			} );

			it( 'skips the checkout session and uses standard elements when Stripe.js lacks initCheckoutElementsSdk', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				// A legacy v3 Stripe.js (from another plugin or a manual snippet)
				// won window.Stripe and never exposes initCheckoutElementsSdk.
				delete api._stripe.initCheckoutElementsSdk;
				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';

				await paymentProcessing.mountStripePaymentElement( api, dom );

				expect(
					api.checkoutSessionsCreateSession
				).not.toHaveBeenCalled();
				expect( api._stripe.elements ).toHaveBeenCalled();
			} );

			it( 'falls back to standard elements when session creation fails', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				setNativeCheckoutSessionData( {
					client_secret: '',
					status: 'error',
				} );
				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';

				await paymentProcessing.mountStripePaymentElement( api, dom );

				expect( api._stripe.elements ).toHaveBeenCalled();
				expect(
					api._stripe.initCheckoutElementsSdk
				).not.toHaveBeenCalled();
			} );

			it( 'falls back to standard elements when client_secret or session_id is absent', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				setNativeCheckoutSessionData( {
					client_secret: '',
					session_id: '',
				} );
				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';

				await paymentProcessing.mountStripePaymentElement( api, dom );

				expect( api._stripe.elements ).toHaveBeenCalled();
				expect(
					api._stripe.initCheckoutElementsSdk
				).not.toHaveBeenCalled();
			} );

			it( 'falls back to standard elements when session_id is absent', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				setNativeCheckoutSessionData( {
					session_id: '',
				} );
				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';

				await paymentProcessing.mountStripePaymentElement( api, dom );

				expect( api._stripe.elements ).toHaveBeenCalled();
				expect(
					api._stripe.initCheckoutElementsSdk
				).not.toHaveBeenCalled();
			} );

			it( 'uses runServerUpdate without calling the custom update endpoint', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';

				await paymentProcessing.mountStripePaymentElement( api, dom );
				await paymentProcessing.maybeUpdateAdaptivePricingCheckoutSession(
					api
				);

				expect(
					checkoutElements.checkoutActions.runServerUpdate
				).toHaveBeenCalled();
				expect(
					api.checkoutSessionsUpdateSession
				).not.toHaveBeenCalled();
			} );

			it( 'does not call checkoutSessionsUpdateSession when adaptive pricing is disabled', async () => {
				stripeUtils.getStripeServerData.mockReturnValue( {
					...BASE_SERVER_DATA,
					isAdaptivePricingEnabled: false,
				} );
				paymentProcessing.initializeUPEComponents();
				const api = createMockApi( createMockElements() );

				await paymentProcessing.maybeUpdateAdaptivePricingCheckoutSession(
					api
				);

				expect(
					api.checkoutSessionsUpdateSession
				).not.toHaveBeenCalled();
			} );
		} );

		describe( 'mountStripePaymentElement loadActions check', () => {
			it( 'calls loadActions and keeps hasLoadError false on success', async () => {
				const checkoutElements = createMockElements();
				checkoutElements.loadActions.mockResolvedValue( {
					type: 'success',
				} );
				const api = createMockApi( checkoutElements );
				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';

				const component =
					await paymentProcessing.mountStripePaymentElement(
						api,
						dom
					);

				expect( checkoutElements.loadActions ).toHaveBeenCalled();
				expect( component.hasLoadError ).toBe( false );
				expect(
					stripeUtils.showErrorPaymentMethod
				).not.toHaveBeenCalled();
			} );

			it( 'sets hasLoadError and shows error when loadActions returns type error', async () => {
				const checkoutElements = createMockElements();
				checkoutElements.loadActions.mockResolvedValue( {
					type: 'error',
					error: { message: 'AP load failed' },
				} );
				const api = createMockApi( checkoutElements );
				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';

				const component =
					await paymentProcessing.mountStripePaymentElement(
						api,
						dom
					);

				expect( component.hasLoadError ).toBe( true );
				expect(
					stripeUtils.showErrorPaymentMethod
				).toHaveBeenCalledWith( 'AP load failed', dom );
			} );

			it( 'skips loadActions when fallback standard elements lack the method', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				// Force fallback to standard elements.
				api.checkoutSessionsCreateSession.mockRejectedValue(
					new Error( 'Session failed' )
				);
				// Standard elements without loadActions.
				const bare = createMockElements();
				delete bare.loadActions;
				api._stripe.elements.mockReturnValue( bare );

				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';

				const component =
					await paymentProcessing.mountStripePaymentElement(
						api,
						dom
					);

				expect( component.hasLoadError ).toBe( false );
				expect(
					stripeUtils.showErrorPaymentMethod
				).not.toHaveBeenCalled();
			} );
		} );

		describe( 'processPayment', () => {
			let originalLocation;

			beforeEach( () => {
				originalLocation = window.location;
				delete window.location;
				window.location = {
					href: '',
					origin: 'https://shop.com',
					assign: jest.fn(),
				};
			} );

			afterEach( () => {
				window.location = originalLocation;
			} );

			/**
			 * Mount the payment element, setting up loadActions to return success
			 * during mount, then configure it for the subsequent processPayment call.
			 * @param {Object} api
			 * @param {Object} checkoutElements
			 * @param {Object} loadActionsForProcess
			 */
			const mountAndConfigureForProcess = async (
				api,
				checkoutElements,
				loadActionsForProcess
			) => {
				checkoutElements.loadActions.mockResolvedValueOnce( {
					type: 'success',
				} );
				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';
				await paymentProcessing.mountStripePaymentElement( api, dom );
				checkoutElements.loadActions.mockResolvedValue(
					loadActionsForProcess
				);
			};

			it( 'submits form via AJAX, then confirms with order-received URL', async () => {
				const orderReceivedUrl =
					'https://shop.com/checkout/order-received/123/';
				const mockActions = {
					getSession: jest.fn().mockResolvedValue( {} ),
					confirm: jest.fn().mockResolvedValue( {
						session: { id: 'cs_session_xyz' },
					} ),
				};
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );

				// Mock the WC checkout AJAX response.
				mockJQueryAjax.mockResolvedValue( {
					result: 'success',
					redirect: orderReceivedUrl,
				} );

				await mountAndConfigureForProcess( api, checkoutElements, {
					type: 'success',
					actions: mockActions,
				} );

				const form = createMockForm();
				paymentProcessing.processPayment( api, form, 'card' );
				await flushPromises();

				// Session ID from mount phase is appended to form.
				expect(
					stripeUtils.appendCheckoutSessionIdToForm
				).toHaveBeenCalledWith( form, MOCK_AP_CHECKOUT_SESSION_ID );
				// Form is submitted via AJAX to create the order.
				expect( mockJQueryAjax ).toHaveBeenCalledWith(
					expect.objectContaining( {
						type: 'POST',
						url: api.getAjaxUrl(),
					} )
				);
				// Confirm is called with the order-received URL.
				expect( mockActions.confirm ).toHaveBeenCalledWith( {
					returnUrl: orderReceivedUrl,
					redirect: 'if_required',
				} );
				// After confirm resolves, navigates to the order-received page.
				expect( window.location.href ).toBe( orderReceivedUrl );
			} );

			it( 'confirms with an absolute returnUrl when the server returns a relative redirect', async () => {
				const relativeRedirect =
					'/checkout/order-received/123/?key=abc';
				const mockActions = {
					getSession: jest.fn().mockResolvedValue( {} ),
					confirm: jest.fn().mockResolvedValue( {
						session: { id: 'cs_session_xyz' },
					} ),
				};
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );

				mockJQueryAjax.mockResolvedValue( {
					result: 'success',
					redirect: relativeRedirect,
				} );

				await mountAndConfigureForProcess( api, checkoutElements, {
					type: 'success',
					actions: mockActions,
				} );

				const form = createMockForm();
				paymentProcessing.processPayment( api, form, 'card' );
				await flushPromises();

				expect( mockActions.confirm ).toHaveBeenCalledWith( {
					returnUrl:
						'https://shop.com/checkout/order-received/123/?key=abc',
					redirect: 'if_required',
				} );
			} );

			it( 'passes savePaymentMethod true when the session supports saving and the save card checkbox is checked', async () => {
				const orderReceivedUrl =
					'https://shop.com/checkout/order-received/123/';
				const mockActions = {
					getSession: jest.fn().mockResolvedValue( {} ),
					confirm: jest.fn().mockResolvedValue( {
						session: { id: 'cs_session_xyz' },
					} ),
				};
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );

				mockJQueryAjax.mockResolvedValue( {
					result: 'success',
					redirect: orderReceivedUrl,
				} );

				await mountAndConfigureForProcess( api, checkoutElements, {
					type: 'success',
					actions: mockActions,
				} );

				setNativeCheckoutSessionData( {
					save_payment_method_enabled: true,
				} );

				const form = createMockForm( {
					savePaymentMethodChecked: true,
				} );
				paymentProcessing.processPayment( api, form, 'card' );
				await flushPromises();

				expect( mockActions.confirm ).toHaveBeenCalledWith( {
					returnUrl: orderReceivedUrl,
					redirect: 'if_required',
					savePaymentMethod: true,
				} );
			} );

			it( 'does not pass savePaymentMethod when the session does not support saving, even when logged in with the save card checkbox checked', async () => {
				const orderReceivedUrl =
					'https://shop.com/checkout/order-received/123/';
				const mockActions = {
					getSession: jest.fn().mockResolvedValue( {} ),
					confirm: jest.fn().mockResolvedValue( {
						session: { id: 'cs_session_xyz' },
					} ),
				};
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );

				mockJQueryAjax.mockResolvedValue( {
					result: 'success',
					redirect: orderReceivedUrl,
				} );

				await mountAndConfigureForProcess( api, checkoutElements, {
					type: 'success',
					actions: mockActions,
				} );

				// A guest-created session reused after login.
				stripeUtils.getStripeServerData.mockReturnValue( {
					...BASE_SERVER_DATA,
					isAdaptivePricingEnabled: true,
					isLoggedIn: true,
				} );
				setNativeCheckoutSessionData( {
					save_payment_method_enabled: false,
				} );

				const form = createMockForm( {
					savePaymentMethodChecked: true,
				} );
				paymentProcessing.processPayment( api, form, 'card' );
				await flushPromises();

				expect( mockActions.confirm ).toHaveBeenCalledWith( {
					returnUrl: orderReceivedUrl,
					redirect: 'if_required',
				} );
			} );

			it( 'shows error and skips confirm when checkout AJAX fails', async () => {
				const mockActions = { confirm: jest.fn() };
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );

				mockJQueryAjax.mockRejectedValue(
					new Error( 'Checkout failed' )
				);

				await mountAndConfigureForProcess( api, checkoutElements, {
					type: 'success',
					actions: mockActions,
				} );

				const form = createMockForm();
				paymentProcessing.processPayment( api, form, 'card' );
				await flushPromises();

				expect( stripeUtils.showErrorCheckout ).toHaveBeenCalled();
				expect( mockActions.confirm ).not.toHaveBeenCalled();
			} );

			it( 'unblocks form and shows messages when checkout AJAX returns a failure result', async () => {
				const mockActions = {
					confirm: jest.fn(),
					getSession: jest.fn().mockResolvedValue( {} ),
				};
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );

				const errorHtml =
					'<ul class="woocommerce-error" role="alert"><li>Invalid coupon</li></ul>';
				mockJQueryAjax.mockResolvedValue( {
					result: 'failure',
					messages: errorHtml,
				} );

				await mountAndConfigureForProcess( api, checkoutElements, {
					type: 'success',
					actions: mockActions,
				} );

				const form = createMockForm();
				paymentProcessing.processPayment( api, form, 'card' );
				await flushPromises();

				expect( form.removeClass ).toHaveBeenCalledWith( 'processing' );
				expect( form.unblock ).toHaveBeenCalled();
				expect( stripeUtils.showErrorCheckout ).toHaveBeenCalledWith(
					errorHtml
				);
				expect( mockActions.confirm ).not.toHaveBeenCalled();
			} );

			it( 'shows error and does not submit when loadActions returns an error', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );

				await mountAndConfigureForProcess( api, checkoutElements, {
					type: 'error',
					error: { message: 'AP payment error' },
				} );

				const form = createMockForm();
				paymentProcessing.processPayment( api, form, 'card' );
				await flushPromises();

				expect( stripeUtils.showErrorCheckout ).toHaveBeenCalledWith(
					'AP payment error'
				);
				expect(
					stripeUtils.appendCheckoutSessionIdToForm
				).not.toHaveBeenCalled();
				expect( form.trigger ).not.toHaveBeenCalledWith( 'submit' );
			} );

			it( 'falls back to standard payment flow when session ID is missing from mount', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				setNativeCheckoutSessionData( { session_id: '' } );
				// Fallback elements should NOT have loadActions (like real stripe.elements()).
				const bareElements = createMockElements();
				delete bareElements.loadActions;
				api._stripe.elements.mockReturnValue( bareElements );

				await mountAndConfigureForProcess( api, checkoutElements, {
					type: 'success',
					actions: { confirm: jest.fn() },
				} );

				const form = createMockForm();
				paymentProcessing.processPayment( api, form, 'card' );
				await flushPromises();

				// Should take the standard path, not the checkout-sessions path.
				expect( bareElements.submit ).toHaveBeenCalled();
				expect(
					stripeUtils.appendPaymentMethodIdToForm
				).toHaveBeenCalled();
				expect(
					stripeUtils.appendCheckoutSessionIdToForm
				).not.toHaveBeenCalled();
			} );

			it( 'shows error if checkoutSessionId is null but loadActions exists (defensive)', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				setNativeCheckoutSessionData( { session_id: '' } );

				await mountAndConfigureForProcess( api, checkoutElements, {
					type: 'success',
					actions: { confirm: jest.fn() },
				} );

				const form = createMockForm();
				paymentProcessing.processPayment( api, form, 'card' );
				await flushPromises();

				expect( stripeUtils.showErrorCheckout ).toHaveBeenCalledWith(
					'Payment could not be completed. Please try again.'
				);
				expect(
					stripeUtils.appendCheckoutSessionIdToForm
				).not.toHaveBeenCalled();
			} );

			it( 'shows error when actions.confirm resolves to an error object', async () => {
				const mockActions = {
					getSession: jest.fn().mockResolvedValue( {} ),
					confirm: jest.fn().mockResolvedValue( {
						type: 'error',
						error: { message: 'Card declined' },
					} ),
				};
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );

				mockJQueryAjax.mockResolvedValue( {
					result: 'success',
					redirect: 'https://shop.com/order-received/1/',
				} );

				await mountAndConfigureForProcess( api, checkoutElements, {
					type: 'success',
					actions: mockActions,
				} );

				const form = createMockForm();
				paymentProcessing.processPayment( api, form, 'card' );
				await flushPromises();

				expect( mockActions.confirm ).toHaveBeenCalled();
				expect( stripeUtils.showErrorCheckout ).toHaveBeenCalledWith(
					'Card declined'
				);
			} );

			it( 'does not call validateElements or appendPaymentMethodIdToForm', async () => {
				const mockActions = {
					getSession: jest.fn().mockResolvedValue( {} ),
					confirm: jest.fn().mockResolvedValue( {
						session: { id: 'cs_session_xyz' },
					} ),
				};
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );

				mockJQueryAjax.mockResolvedValue( {
					result: 'success',
					redirect: 'https://shop.com/order-received/1/',
				} );

				await mountAndConfigureForProcess( api, checkoutElements, {
					type: 'success',
					actions: mockActions,
				} );

				const form = createMockForm();
				paymentProcessing.processPayment( api, form, 'card' );
				await flushPromises();

				// validateElements calls elements.submit() – must NOT happen.
				expect( checkoutElements.submit ).not.toHaveBeenCalled();
				// Legacy flow must not run.
				expect(
					stripeUtils.appendPaymentMethodIdToForm
				).not.toHaveBeenCalled();
			} );

			it( 'shows generic error when hasLoadError is true (set during mount)', async () => {
				const checkoutElements = createMockElements();
				// Mount: loadActions error → sets hasLoadError = true.
				checkoutElements.loadActions.mockResolvedValue( {
					type: 'error',
					error: { message: 'Mount-time error' },
				} );
				const api = createMockApi( checkoutElements );
				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';

				await paymentProcessing.mountStripePaymentElement( api, dom );
				stripeUtils.showErrorCheckout.mockClear();

				const form = createMockForm();
				paymentProcessing.processPayment( api, form, 'card' );
				await flushPromises();

				expect( stripeUtils.showErrorCheckout ).toHaveBeenCalledWith(
					'Invalid or missing payment details. Please ensure the provided payment method is correctly entered.'
				);
				expect( form.trigger ).not.toHaveBeenCalledWith( 'submit' );
			} );
		} );

		describe( 'checkout session resync failure handling', () => {
			const STALE_TOTAL_MESSAGE =
				"We couldn't update your order total. Please refresh the page and try again.";

			const mountElement = async ( api, checkoutElements ) => {
				checkoutElements.loadActions.mockResolvedValueOnce( {
					type: 'success',
				} );
				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';
				await paymentProcessing.mountStripePaymentElement( api, dom );
			};

			it.each( [
				[
					'runServerUpdate returns an error result',
					( checkoutElements ) => {
						checkoutElements.checkoutActions.runServerUpdate.mockResolvedValueOnce(
							{ type: 'error', error: { message: 'boom' } }
						);
					},
				],
				[
					'runServerUpdate throws',
					( checkoutElements ) => {
						checkoutElements.checkoutActions.runServerUpdate.mockRejectedValueOnce(
							new Error( 'boom' )
						);
					},
				],
				[
					'the element cannot expose runServerUpdate',
					( checkoutElements ) => {
						delete checkoutElements.loadActions;
					},
				],
			] )(
				'shows a graceful notice when %s',
				async ( _label, makeFail ) => {
					const checkoutElements = createMockElements();
					const api = createMockApi( checkoutElements );
					await mountElement( api, checkoutElements );

					makeFail( checkoutElements, api );
					stripeUtils.showErrorCheckout.mockClear();

					await paymentProcessing.maybeUpdateAdaptivePricingCheckoutSession(
						api
					);

					expect(
						stripeUtils.showErrorCheckout
					).toHaveBeenCalledWith( STALE_TOTAL_MESSAGE );
				}
			);

			it( 'stays silent when no element holds a checkout session', async () => {
				// Nothing mounted through initCheckoutElementsSdk, so the store is on standard
				// elements and has no session that could go stale.
				setNativeCheckoutSessionData( {
					session_id: '',
					client_secret: '',
					revision: '',
					status: 'uninitialized',
				} );
				stripeUtils.showErrorCheckout.mockClear();

				await paymentProcessing.maybeUpdateAdaptivePricingCheckoutSession();

				expect( stripeUtils.showErrorCheckout ).not.toHaveBeenCalled();
			} );

			it( 'shows a notice when the server reports a failed synchronization', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				await mountElement( api, checkoutElements );

				setNativeCheckoutSessionData( { status: 'error' } );
				stripeUtils.showErrorCheckout.mockClear();

				await paymentProcessing.maybeUpdateAdaptivePricingCheckoutSession();

				expect( stripeUtils.showErrorCheckout ).toHaveBeenCalledWith(
					STALE_TOTAL_MESSAGE
				);
				expect(
					checkoutElements.checkoutActions.runServerUpdate
				).not.toHaveBeenCalled();
			} );

			it( 'shows a notice when the server replaced the session the element is bound to', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				await mountElement( api, checkoutElements );

				setNativeCheckoutSessionData( {
					session_id: 'cs_test_replacement',
				} );
				stripeUtils.showErrorCheckout.mockClear();

				await paymentProcessing.maybeUpdateAdaptivePricingCheckoutSession();

				expect( stripeUtils.showErrorCheckout ).toHaveBeenCalledWith(
					STALE_TOTAL_MESSAGE
				);
				expect(
					checkoutElements.checkoutActions.runServerUpdate
				).not.toHaveBeenCalled();
			} );

			it( 'ignores a stale failing resync that settles after a newer successful one', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				await mountElement( api, checkoutElements );
				stripeUtils.showErrorCheckout.mockClear();

				// Hold the older resync open so it can only fail after the
				// newer one has already succeeded.
				let failOlder;
				checkoutElements.checkoutActions.runServerUpdate
					.mockImplementationOnce(
						() =>
							new Promise( ( resolve ) => {
								failOlder = () =>
									resolve( {
										type: 'error',
										error: { message: 'stale' },
									} );
							} )
					)
					.mockImplementationOnce( async ( userFunction ) => {
						await userFunction();
						return {
							type: 'success',
							session: { id: MOCK_AP_CHECKOUT_SESSION_ID },
						};
					} );

				const olderResync =
					paymentProcessing.maybeUpdateAdaptivePricingCheckoutSession(
						api
					);
				await flushPromises();

				await paymentProcessing.maybeUpdateAdaptivePricingCheckoutSession(
					api
				);

				failOlder();
				await olderResync;

				// The superseded failure must not resurrect the stale-total
				// block after the newer resync cleared it.
				expect( stripeUtils.showErrorCheckout ).not.toHaveBeenCalled();
			} );

			it( 'clears the payment-area block when the superseding resync never blocks of its own', async () => {
				const SELECTOR = 'form.checkout #payment';
				const blockCount = () =>
					global.__wcStripeBlockCounts?.[ SELECTOR ] ?? 0;
				const unblockCount = () =>
					global.__wcStripeUnblockCounts?.[ SELECTOR ] ?? 0;
				const blocksBefore = blockCount();
				const unblocksBefore = unblockCount();

				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				await mountElement( api, checkoutElements );

				// Make the next resync enter the block branch regardless of the
				// mount's one-shot loadActions.
				checkoutElements.loadActions.mockResolvedValue( {
					type: 'success',
					actions: checkoutElements.checkoutActions,
				} );

				// Hold the older resync open so its finally runs after the newer
				// generation takes over.
				let resolveOlder;
				checkoutElements.checkoutActions.runServerUpdate.mockImplementationOnce(
					() =>
						new Promise( ( resolve ) => {
							resolveOlder = () =>
								resolve( {
									type: 'success',
									session: {
										id: MOCK_AP_CHECKOUT_SESSION_ID,
									},
								} );
						} )
				);

				// Gen A blocks the payment area, then parks mid-update.
				const olderResync =
					paymentProcessing.maybeUpdateAdaptivePricingCheckoutSession(
						api
					);
				await flushPromises();
				expect( blockCount() ).toBe( blocksBefore + 1 );

				// Element tears down before the newer resync, so it skips the
				// block/guarded-unblock branch entirely (no loadActions).
				delete checkoutElements.loadActions;

				// Gen B (now current) completes without blocking.
				await paymentProcessing.maybeUpdateAdaptivePricingCheckoutSession(
					api
				);

				// Gen A settles last; superseded, so it skips its guarded unblock.
				resolveOlder();
				await olderResync;

				// Gen B's final cleanup must have lifted the block Gen A stranded.
				expect( unblockCount() ).toBeGreaterThan( unblocksBefore );
			} );

			it( 'does not show a notice when the resync succeeds', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				await mountElement( api, checkoutElements );
				stripeUtils.showErrorCheckout.mockClear();

				await paymentProcessing.maybeUpdateAdaptivePricingCheckoutSession(
					api
				);

				expect( stripeUtils.showErrorCheckout ).not.toHaveBeenCalled();
			} );

			it( 'retracts the stale-total notice once a later resync succeeds', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				await mountElement( api, checkoutElements );

				// First resync fails and leaves a notice on the page.
				checkoutElements.checkoutActions.runServerUpdate.mockResolvedValueOnce(
					{ type: 'error', error: { message: 'boom' } }
				);
				await paymentProcessing.maybeUpdateAdaptivePricingCheckoutSession(
					api
				);
				stripeUtils.clearStaleCheckoutTotalNotice.mockClear();

				// A later clean resync must clear that lingering notice.
				await paymentProcessing.maybeUpdateAdaptivePricingCheckoutSession(
					api
				);

				expect(
					stripeUtils.clearStaleCheckoutTotalNotice
				).toHaveBeenCalled();
			} );

			it( 'blocks payment while the session is stale, then allows it after a clean resync', async () => {
				const originalLocation = window.location;
				delete window.location;
				window.location = { href: '', assign: jest.fn() };

				const mockActions = {
					getSession: jest.fn().mockResolvedValue( {} ),
					confirm: jest
						.fn()
						.mockResolvedValue( { session: { id: 'cs_xyz' } } ),
				};
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				mockJQueryAjax.mockResolvedValue( {
					result: 'success',
					redirect: 'https://shop.com/order-received/1/',
				} );
				await mountElement( api, checkoutElements );

				// Fail the resync so the session holds stale line items.
				checkoutElements.checkoutActions.runServerUpdate.mockResolvedValueOnce(
					{ type: 'error', error: { message: 'boom' } }
				);
				await paymentProcessing.maybeUpdateAdaptivePricingCheckoutSession(
					api
				);

				// Submission is blocked: no order is created and the buyer is warned.
				checkoutElements.loadActions.mockResolvedValue( {
					type: 'success',
					actions: mockActions,
				} );
				stripeUtils.showErrorCheckout.mockClear();
				paymentProcessing.processPayment(
					api,
					createMockForm(),
					'card'
				);
				await flushPromises();

				expect( stripeUtils.showErrorCheckout ).toHaveBeenCalledWith(
					STALE_TOTAL_MESSAGE
				);
				expect(
					stripeUtils.appendCheckoutSessionIdToForm
				).not.toHaveBeenCalled();
				expect( mockJQueryAjax ).not.toHaveBeenCalled();

				// A clean resync lifts the block and the order is created.
				checkoutElements.loadActions.mockResolvedValue( {
					type: 'success',
					actions: checkoutElements.checkoutActions,
				} );
				await paymentProcessing.maybeUpdateAdaptivePricingCheckoutSession(
					api
				);
				paymentProcessing.processPayment(
					api,
					createMockForm(),
					'card'
				);
				await flushPromises();

				expect(
					stripeUtils.appendCheckoutSessionIdToForm
				).toHaveBeenCalledWith(
					expect.anything(),
					MOCK_AP_CHECKOUT_SESSION_ID
				);

				window.location = originalLocation;
			} );
		} );
	} );

	describe( 'createStripePaymentElement layout option', () => {
		let domElement;

		beforeEach( () => {
			paymentProcessing.initializeUPEComponents();

			domElement = document.createElement( 'div' );
			domElement.dataset.paymentMethodType = 'card';
			document.body.appendChild( domElement );
		} );

		afterEach( () => {
			document.body.removeChild( domElement );
		} );

		it( 'passes layout:tabs when Optimized Checkout is disabled', async () => {
			stripeUtils.getStripeServerData.mockReturnValue( {
				paymentMethodsConfig: {
					card: { supportsDeferredIntent: true },
				},
				isAdaptivePricingEnabled: false,
				cartTotal: 1000,
				currency: 'usd',
				isPaymentNeeded: true,
				shouldShowOptimizedCheckout: false,
			} );

			const { stripe, mockElements } = buildMockStripe();
			await paymentProcessing.mountStripePaymentElement(
				buildApi( stripe ),
				domElement
			);

			expect( mockElements.create ).toHaveBeenCalledTimes( 1 );
			const [ , paymentElementOptions ] =
				mockElements.create.mock.calls[ 0 ];
			expect( paymentElementOptions.layout ).toStrictEqual( {
				type: 'tabs',
			} );
		} );

		it( "passes accordion layout with radios:'never' when Optimized Checkout is enabled with default layout", async () => {
			stripeUtils.getStripeServerData.mockReturnValue( {
				paymentMethodsConfig: {
					card: { supportsDeferredIntent: true },
				},
				isAdaptivePricingEnabled: false,
				cartTotal: 1000,
				currency: 'usd',
				isPaymentNeeded: true,
				shouldShowOptimizedCheckout: true,
				OCLayout: undefined,
			} );

			const { stripe, mockElements } = buildMockStripe();
			await paymentProcessing.mountStripePaymentElement(
				buildApi( stripe ),
				domElement
			);

			expect( mockElements.create ).toHaveBeenCalledTimes( 1 );
			const [ , paymentElementOptions ] =
				mockElements.create.mock.calls[ 0 ];
			expect( paymentElementOptions.layout.type ).toBe( 'accordion' );
			expect( paymentElementOptions.layout.radios ).toBe( 'never' );
			expect( paymentElementOptions.layout.spacedAccordionItems ).toBe(
				false
			);
		} );

		it( "passes accordion layout with radios:'never' when Optimized Checkout is enabled with an explicit layout - accordion", async () => {
			stripeUtils.getStripeServerData.mockReturnValue( {
				paymentMethodsConfig: {
					card: { supportsDeferredIntent: true },
				},
				isAdaptivePricingEnabled: false,
				cartTotal: 1000,
				currency: 'usd',
				isPaymentNeeded: true,
				shouldShowOptimizedCheckout: true,
				OCLayout: 'accordion',
			} );

			const { stripe, mockElements } = buildMockStripe();
			await paymentProcessing.mountStripePaymentElement(
				buildApi( stripe ),
				domElement
			);

			expect( mockElements.create ).toHaveBeenCalledTimes( 1 );
			const [ , paymentElementOptions ] =
				mockElements.create.mock.calls[ 0 ];
			expect( paymentElementOptions.layout.type ).toBe( 'accordion' );
			expect( paymentElementOptions.layout.radios ).toBe( 'never' );
			expect( paymentElementOptions.layout.spacedAccordionItems ).toBe(
				false
			);
		} );

		it( 'passes custom OCLayout when Optimized Checkout is enabled with an explicit layout - tabs', async () => {
			stripeUtils.getStripeServerData.mockReturnValue( {
				paymentMethodsConfig: {
					card: { supportsDeferredIntent: true },
				},
				isAdaptivePricingEnabled: false,
				cartTotal: 1000,
				currency: 'usd',
				isPaymentNeeded: true,
				shouldShowOptimizedCheckout: true,
				OCLayout: 'tabs',
			} );

			const { stripe, mockElements } = buildMockStripe();
			await paymentProcessing.mountStripePaymentElement(
				buildApi( stripe ),
				domElement
			);

			expect( mockElements.create ).toHaveBeenCalledTimes( 1 );
			const [ , paymentElementOptions ] =
				mockElements.create.mock.calls[ 0 ];
			expect( paymentElementOptions.layout.type ).toBe( 'tabs' );
			expect( paymentElementOptions.layout.radios ).toBeUndefined();
			expect(
				paymentElementOptions.layout.spacedAccordionItems
			).toBeUndefined();
		} );
	} );

	describe( 'hasEmptyRequiredFields', () => {
		let form;

		beforeEach( () => {
			document.body.innerHTML = '';
			form = document.createElement( 'form' );
			form.className = 'checkout';
			document.body.appendChild( form );
		} );

		const getRequiredFieldWrappers = () =>
			form.querySelectorAll( '.validate-required' );

		it( 'returns false when there are no required fields', () => {
			expect( hasEmptyRequiredFields( getRequiredFieldWrappers() ) ).toBe(
				false
			);
		} );

		it( 'returns true when a required text input is empty', () => {
			form.innerHTML =
				'<p class="validate-required">' +
				'<input type="text" class="input-text" value="" />' +
				'</p>';
			expect( hasEmptyRequiredFields( getRequiredFieldWrappers() ) ).toBe(
				true
			);
		} );

		it( 'returns true when a required text input has only whitespace', () => {
			form.innerHTML =
				'<p class="validate-required">' +
				'<input type="text" class="input-text" value="   " />' +
				'</p>';
			expect( hasEmptyRequiredFields( getRequiredFieldWrappers() ) ).toBe(
				true
			);
		} );

		it( 'returns false when all required text inputs have values', () => {
			form.innerHTML =
				'<p class="validate-required">' +
				'<input type="text" class="input-text" value="John" />' +
				'</p>' +
				'<p class="validate-required">' +
				'<input type="text" class="input-text" value="john@example.com" />' +
				'</p>';
			expect( hasEmptyRequiredFields( getRequiredFieldWrappers() ) ).toBe(
				false
			);
		} );

		it( 'returns true when a required select has no value', () => {
			form.innerHTML =
				'<p class="validate-required">' +
				'<select><option value="">Select...</option><option value="US">US</option></select>' +
				'</p>';
			expect( hasEmptyRequiredFields( getRequiredFieldWrappers() ) ).toBe(
				true
			);
		} );

		it( 'returns false when a required select has a value', () => {
			form.innerHTML =
				'<p class="validate-required">' +
				'<select><option value="">Select...</option><option value="US" selected>US</option></select>' +
				'</p>';
			expect( hasEmptyRequiredFields( getRequiredFieldWrappers() ) ).toBe(
				false
			);
		} );

		// WooCommerce posts a multiselect as implode( ', ', $value ), so both of the
		// cases below still arrive as '' and are rejected server-side. Blocking them
		// costs nothing; the third case posts ', US' and is accepted, so blocking it
		// would fail the checkout outright.
		it( 'returns true when a required multiple select has nothing selected', () => {
			form.innerHTML =
				'<p class="validate-required">' +
				'<select multiple>' +
				'<option value="">Select...</option>' +
				'<option value="US">US</option>' +
				'</select>' +
				'</p>';
			expect( hasEmptyRequiredFields( getRequiredFieldWrappers() ) ).toBe(
				true
			);
		} );

		it( 'returns true when a required multiple select has only an empty option selected', () => {
			form.innerHTML =
				'<p class="validate-required">' +
				'<select multiple>' +
				'<option value="" selected>Select...</option>' +
				'<option value="US">US</option>' +
				'</select>' +
				'</p>';
			expect( hasEmptyRequiredFields( getRequiredFieldWrappers() ) ).toBe(
				true
			);
		} );

		it( 'returns false when a required multiple select has a value selected after an empty option', () => {
			form.innerHTML =
				'<p class="validate-required">' +
				'<select multiple>' +
				'<option value="" selected>Select...</option>' +
				'<option value="US" selected>US</option>' +
				'</select>' +
				'</p>';
			expect( hasEmptyRequiredFields( getRequiredFieldWrappers() ) ).toBe(
				false
			);
		} );

		it( 'returns true when a required checkbox is unchecked', () => {
			form.innerHTML =
				'<p class="validate-required">' +
				'<input type="checkbox" />' +
				'</p>';
			expect( hasEmptyRequiredFields( getRequiredFieldWrappers() ) ).toBe(
				true
			);
		} );

		it( 'returns false when a required checkbox is checked', () => {
			form.innerHTML =
				'<p class="validate-required">' +
				'<input type="checkbox" checked />' +
				'</p>';
			expect( hasEmptyRequiredFields( getRequiredFieldWrappers() ) ).toBe(
				false
			);
		} );

		it( 'returns false when one checkbox in a required group is checked', () => {
			form.innerHTML =
				'<p class="validate-required">' +
				'<input type="checkbox" />' +
				'<input type="checkbox" checked />' +
				'</p>';
			expect( hasEmptyRequiredFields( getRequiredFieldWrappers() ) ).toBe(
				false
			);
		} );

		it( 'returns true when no checkbox in a required group is checked', () => {
			form.innerHTML =
				'<p class="validate-required">' +
				'<input type="checkbox" />' +
				'<input type="checkbox" />' +
				'</p>';
			expect( hasEmptyRequiredFields( getRequiredFieldWrappers() ) ).toBe(
				true
			);
		} );

		// Radios stay outside the selector: blocking on an unselected group would stop
		// checkout on stores whose radio fields have no server-side validation.
		it( 'returns false for a required wrapper holding only unselected radios', () => {
			form.innerHTML =
				'<p class="validate-required">' +
				'<input type="radio" name="option" />' +
				'<input type="radio" name="option" />' +
				'</p>';
			expect( hasEmptyRequiredFields( getRequiredFieldWrappers() ) ).toBe(
				false
			);
		} );

		it( 'returns false when a required radio group has a selection', () => {
			form.innerHTML =
				'<p class="validate-required">' +
				'<input type="radio" name="option" />' +
				'<input type="radio" name="option" checked />' +
				'</p>';
			expect( hasEmptyRequiredFields( getRequiredFieldWrappers() ) ).toBe(
				false
			);
		} );

		it( 'returns false when a checked radio accompanies an empty placeholder select', () => {
			form.innerHTML =
				'<p class="validate-required">' +
				'<select><option value="" selected>Select...</option></select>' +
				'<input type="radio" name="option" value="yes" checked />' +
				'</p>';
			expect( hasEmptyRequiredFields( getRequiredFieldWrappers() ) ).toBe(
				false
			);
		} );

		it( 'returns true when unchecked radios accompany an empty placeholder select', () => {
			form.innerHTML =
				'<p class="validate-required">' +
				'<select><option value="" selected>Select...</option></select>' +
				'<input type="radio" name="option" value="yes" />' +
				'</p>';
			expect( hasEmptyRequiredFields( getRequiredFieldWrappers() ) ).toBe(
				true
			);
		} );

		// The checkbox group check only applies when the group leads the wrapper, so a
		// filled text field ahead of an unchecked checkbox still passes, as before.
		it( 'returns false when a filled text input precedes an unchecked checkbox', () => {
			form.innerHTML =
				'<p class="validate-required">' +
				'<input type="text" class="input-text" value="John" />' +
				'<input type="checkbox" />' +
				'</p>';
			expect( hasEmptyRequiredFields( getRequiredFieldWrappers() ) ).toBe(
				false
			);
		} );

		it( 'returns true if any one of multiple fields is empty', () => {
			form.innerHTML =
				'<p class="validate-required">' +
				'<input type="text" class="input-text" value="John" />' +
				'</p>' +
				'<p class="validate-required">' +
				'<input type="text" class="input-text" value="" />' +
				'</p>';
			expect( hasEmptyRequiredFields( getRequiredFieldWrappers() ) ).toBe(
				true
			);
		} );

		it( 'skips validate-required wrappers with no matching input', () => {
			form.innerHTML =
				'<p class="validate-required">' +
				'<span>No input here</span>' +
				'</p>';
			expect( hasEmptyRequiredFields( getRequiredFieldWrappers() ) ).toBe(
				false
			);
		} );
	} );
} );

// Closes the updated_checkout re-mount race: waits for any in-flight (re)mount
// and mounts a torn-down element before the payment method is created.
describe( 'ensureUPEElementMounted', () => {
	beforeEach( () => {
		stripeUtils.getStripeServerData.mockReturnValue( {
			...BASE_SERVER_DATA,
			isAdaptivePricingEnabled: false,
		} );
		paymentProcessing.initializeUPEComponents();
		document.body.innerHTML = '';
	} );

	afterEach( () => {
		jest.clearAllMocks();
		document.body.innerHTML = '';
	} );

	const addOnPageElement = ( { mounted } = { mounted: false } ) => {
		const el = document.createElement( 'div' );
		el.className = 'wc-stripe-upe-element';
		el.dataset.paymentMethodType = 'card';
		if ( mounted ) {
			// A mounted element has the Stripe iframe as a child.
			el.appendChild( document.createElement( 'iframe' ) );
		}
		document.body.appendChild( el );
		return el;
	};

	it( 'resolves without mounting when there is no payment element on the page', async () => {
		const api = createMockApi( createMockElements() );

		await expect(
			paymentProcessing.ensureUPEElementMounted( api, 'card' )
		).resolves.toBeUndefined();
		expect( api._standardElements.create ).not.toHaveBeenCalled();
	} );

	it( 'resolves without error for an unknown payment method', async () => {
		const api = createMockApi( createMockElements() );
		addOnPageElement( { mounted: true } );

		await expect(
			paymentProcessing.ensureUPEElementMounted(
				api,
				'not-a-stripe-method'
			)
		).resolves.toBeUndefined();
	} );

	it( 'awaits an in-flight (re)mount before resolving', async () => {
		const api = createMockApi( createMockElements() );
		// Establish the component (its mount settles immediately).
		const dom = document.createElement( 'div' );
		dom.dataset.paymentMethodType = 'card';
		await paymentProcessing.mountStripePaymentElement( api, dom );

		// Already mounted, so ensure only waits on the in-flight tracker.
		addOnPageElement( { mounted: true } );

		let resolveMount;
		paymentProcessing.trackMountInProgress(
			new Promise( ( resolve ) => {
				resolveMount = resolve;
			} )
		);

		let resolved = false;
		const ensurePromise = paymentProcessing
			.ensureUPEElementMounted( api, 'card' )
			.then( () => {
				resolved = true;
			} );

		// Mount still pending → ensure must not have resolved yet.
		await Promise.resolve();
		expect( resolved ).toBe( false );

		resolveMount();
		await ensurePromise;
		expect( resolved ).toBe( true );
	} );

	it( 'mounts the element when it was torn down but not yet re-mounted', async () => {
		const api = createMockApi( createMockElements() );
		// First mount creates and caches the Stripe element on the component.
		const detachedDom = document.createElement( 'div' );
		detachedDom.dataset.paymentMethodType = 'card';
		await paymentProcessing.mountStripePaymentElement( api, detachedDom );

		const createdEl = api._standardElements.create.mock.results[ 0 ].value;
		createdEl.mount.mockClear();

		// An empty (torn-down) element appears, as after a payment-box re-render.
		const onPageEl = addOnPageElement( { mounted: false } );

		await paymentProcessing.ensureUPEElementMounted( api, 'card' );

		expect( createdEl.mount ).toHaveBeenCalledWith( onPageEl );
	} );

	it( 're-queries the DOM after awaiting so it mounts into the live node, not a detached one', async () => {
		const api = createMockApi( createMockElements() );
		// First mount caches the Stripe element on the component.
		const detachedDom = document.createElement( 'div' );
		detachedDom.dataset.paymentMethodType = 'card';
		await paymentProcessing.mountStripePaymentElement( api, detachedDom );

		const createdEl = api._standardElements.create.mock.results[ 0 ].value;
		createdEl.mount.mockClear();

		// A torn-down element is on the page when the submit starts, and a
		// re-mount is in flight.
		const staleEl = addOnPageElement( { mounted: false } );
		let resolveMount;
		paymentProcessing.trackMountInProgress(
			new Promise( ( resolve ) => {
				resolveMount = resolve;
			} )
		);

		const ensurePromise = paymentProcessing.ensureUPEElementMounted(
			api,
			'card'
		);

		// While ensure awaits the in-flight mount, updated_checkout swaps the
		// payment box for a fresh (still empty) node, detaching the stale one.
		staleEl.remove();
		const freshEl = addOnPageElement( { mounted: false } );
		resolveMount();

		await ensurePromise;

		// Mounts into the live, in-document node — not the detached stale node.
		// (Identity, not structural equality: the two empty containers are
		// otherwise indistinguishable.)
		const mountedInto = createdEl.mount.mock.calls[ 0 ][ 0 ];
		expect( mountedInto ).toBe( freshEl );
		expect( mountedInto ).not.toBe( staleEl );
		expect( mountedInto.isConnected ).toBe( true );
	} );

	it( 'does not re-mount an element that is already mounted', async () => {
		const api = createMockApi( createMockElements() );
		const detachedDom = document.createElement( 'div' );
		detachedDom.dataset.paymentMethodType = 'card';
		await paymentProcessing.mountStripePaymentElement( api, detachedDom );

		const createdEl = api._standardElements.create.mock.results[ 0 ].value;
		createdEl.mount.mockClear();

		addOnPageElement( { mounted: true } );

		await paymentProcessing.ensureUPEElementMounted( api, 'card' );

		expect( createdEl.mount ).not.toHaveBeenCalled();
	} );

	it( 'dedupes concurrent mounts of the same element to a single mount() call', async () => {
		const api = createMockApi( createMockElements() );
		const onPageEl = addOnPageElement( { mounted: false } );

		// Both checkout paths (ensureUPEElementMounted and the updated_checkout
		// handler) can race to mount the same torn-down element. Fire two mounts
		// for the same node concurrently.
		const [ first, second ] = await Promise.all( [
			paymentProcessing.mountStripePaymentElement( api, onPageEl ),
			paymentProcessing.mountStripePaymentElement( api, onPageEl ),
		] );

		const createdEl = api._standardElements.create.mock.results[ 0 ].value;
		expect( createdEl.mount ).toHaveBeenCalledTimes( 1 );
		expect( first ).toBe( second );
	} );

	it( 'processPayment waits for a pending re-mount before creating the payment method', async () => {
		const api = createMockApi( createMockElements() );
		api._stripe.elements.mockReturnValue( api._standardElements );

		const dom = document.createElement( 'div' );
		dom.dataset.paymentMethodType = 'card';
		await paymentProcessing.mountStripePaymentElement( api, dom );

		// A mounted element is on the page, and a remount is still in flight.
		addOnPageElement( { mounted: true } );
		let resolveMount;
		paymentProcessing.trackMountInProgress(
			new Promise( ( resolve ) => {
				resolveMount = resolve;
			} )
		);

		const form = createMockForm();
		paymentProcessing.processPayment( api, form, 'card' );
		await flushPromises();

		// Mount still pending → must not have created/submitted anything yet.
		expect( api._standardElements.submit ).not.toHaveBeenCalled();
		expect(
			stripeUtils.appendPaymentMethodIdToForm
		).not.toHaveBeenCalled();

		resolveMount();
		await flushPromises();

		// Once the mount settles, the submit path proceeds.
		expect( api._standardElements.submit ).toHaveBeenCalled();
		expect( stripeUtils.appendPaymentMethodIdToForm ).toHaveBeenCalledWith(
			form,
			'pm_test_123'
		);
	} );

	it( 'clears a prior hasLoadError when a new mount starts', async () => {
		const api = createMockApi( createMockElements() );
		const dom = document.createElement( 'div' );
		dom.dataset.paymentMethodType = 'card';
		const component = await paymentProcessing.mountStripePaymentElement(
			api,
			dom
		);

		// Simulate a transient load failure left over from an earlier mount.
		component.hasLoadError = true;

		const dom2 = document.createElement( 'div' );
		dom2.dataset.paymentMethodType = 'card';
		await paymentProcessing.mountStripePaymentElement( api, dom2 );

		expect( component.hasLoadError ).toBe( false );
	} );

	it( 'wires the element listeners once across remounts', async () => {
		const api = createMockApi( createMockElements() );
		const dom1 = document.createElement( 'div' );
		dom1.dataset.paymentMethodType = 'card';
		await paymentProcessing.mountStripePaymentElement( api, dom1 );

		const createdEl = api._standardElements.create.mock.results[ 0 ].value;
		const loaderrorCount = () =>
			createdEl.on.mock.calls.filter(
				( [ event ] ) => event === 'loaderror'
			).length;
		expect( loaderrorCount() ).toBe( 1 );

		// Remount onto a new node, as updated_checkout would.
		const dom2 = document.createElement( 'div' );
		dom2.dataset.paymentMethodType = 'card';
		await paymentProcessing.mountStripePaymentElement( api, dom2 );

		// Element re-mounted, but listeners not re-attached on the cached element.
		expect( createdEl.mount ).toHaveBeenCalledTimes( 2 );
		expect( loaderrorCount() ).toBe( 1 );
	} );

	it( 'fences a superseded mount so its late loadActions cannot set hasLoadError', async () => {
		stripeUtils.getStripeServerData.mockReturnValue( {
			...BASE_SERVER_DATA,
			isAdaptivePricingEnabled: true,
		} );
		const checkoutElements = createMockElements();
		const api = createMockApi( checkoutElements );

		// First (stale) mount's loadActions resolves to an error only after a
		// newer mount supersedes it; the newer mount succeeds.
		let resolveStaleLoad;
		checkoutElements.loadActions
			.mockReturnValueOnce(
				new Promise( ( resolve ) => {
					resolveStaleLoad = resolve;
				} )
			)
			.mockResolvedValue( {
				type: 'success',
				actions: checkoutElements.checkoutActions,
			} );

		const dom1 = document.createElement( 'div' );
		dom1.dataset.paymentMethodType = 'card';
		const dom2 = document.createElement( 'div' );
		dom2.dataset.paymentMethodType = 'card';

		// Mount A reaches its loadActions await…
		const pA = paymentProcessing.mountStripePaymentElement( api, dom1 );
		await flushPromises();

		// …then mount B supersedes it and completes successfully.
		const pB = paymentProcessing.mountStripePaymentElement( api, dom2 );
		await flushPromises();

		// A's loadActions now resolves with an error, but A is stale.
		resolveStaleLoad( { type: 'error', error: { message: 'stale' } } );
		const [ , component ] = await Promise.all( [ pA, pB ] );

		expect( component.hasLoadError ).toBe( false );
	} );

	describe( 'confirmVoucherPayment', () => {
		let originalLocation;

		beforeEach( () => {
			originalLocation = window.location;
			delete window.location;
			window.location = {
				href: '',
				origin: 'https://shop.com',
				pathname: '/checkout',
				search: '',
				assign: jest.fn(),
			};
			jest.spyOn( window.history, 'replaceState' ).mockImplementation(
				() => {}
			);
		} );

		afterEach( () => {
			window.location = originalLocation;
			jest.clearAllMocks();
		} );

		// Hash format written by process_payment_with_deferred_intent():
		// #wc-stripe-voucher-<order_id>:<type>:<client_secret>:<encoded_redirect_url>
		const setVoucherHash = ( encodedRedirectUrl ) => {
			window.location.href =
				'https://shop.com/checkout#wc-stripe-voucher-123:boleto:cs_secret:' +
				encodedRedirectUrl;
		};

		const buildVoucherApi = () => ( {
			getStripe: jest.fn( () => ( {
				confirmBoletoPayment: jest.fn().mockResolvedValue( {} ),
			} ) ),
		} );

		it( 'navigates to a same-origin post-payment URL after confirming the voucher', async () => {
			setVoucherHash(
				encodeURIComponent( 'https://shop.com/order-received/123/' )
			);
			stripeUtils.getStripeServerData.mockReturnValue( {
				orderReceivedURL: 'https://shop.com/checkout/order-received',
			} );

			await paymentProcessing.confirmVoucherPayment(
				buildVoucherApi(),
				createMockForm()
			);

			expect( window.location.href ).toBe(
				'https://shop.com/order-received/123/'
			);
		} );

		it( 'falls back to the order-received page when the post-payment URL is cross-origin', async () => {
			setVoucherHash(
				encodeURIComponent( 'https://evil.example/steal' )
			);
			stripeUtils.getStripeServerData.mockReturnValue( {
				orderReceivedURL: 'https://shop.com/checkout/order-received',
			} );

			await paymentProcessing.confirmVoucherPayment(
				buildVoucherApi(),
				createMockForm()
			);

			expect( window.location.href ).toBe(
				'https://shop.com/checkout/order-received/123/'
			);
		} );
	} );
} );

describe( 'maybeUpdateOptimizedCheckoutExclusions', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		stripeUtils.getStripeServerData.mockReturnValue( {
			...BASE_SERVER_DATA,
			shouldShowOptimizedCheckout: true,
		} );
		stripeUtils.getCurrentBillingCountry.mockReturnValue( 'US' );
		stripeUtils.getExcludedPaymentMethodTypesForBillingCountry.mockReturnValue(
			[]
		);
		paymentProcessing.initializeUPEComponents();
	} );

	const mountOCElement = async () => {
		const api = createMockApi( createMockElements() );
		const dom = document.createElement( 'div' );
		dom.dataset.paymentMethodType = 'card';
		await paymentProcessing.mountStripePaymentElement( api, dom );
		return api._standardElements;
	};

	it( 'updates the element with the exclusions recomputed for the current country', async () => {
		const elements = await mountOCElement();
		stripeUtils.getCurrentBillingCountry.mockReturnValue( 'NL' );
		stripeUtils.getExcludedPaymentMethodTypesForBillingCountry.mockReturnValue(
			[ 'affirm' ]
		);

		paymentProcessing.maybeUpdateOptimizedCheckoutExclusions();

		expect(
			stripeUtils.getExcludedPaymentMethodTypesForBillingCountry
		).toHaveBeenCalledWith( 'NL' );
		expect( elements.update ).toHaveBeenCalledWith( {
			excludedPaymentMethodTypes: [ 'affirm' ],
		} );
	} );

	it( 'skips the Stripe update when the exclusions are unchanged', async () => {
		const elements = await mountOCElement();
		stripeUtils.getExcludedPaymentMethodTypesForBillingCountry.mockReturnValue(
			[ 'affirm' ]
		);

		paymentProcessing.maybeUpdateOptimizedCheckoutExclusions();
		paymentProcessing.maybeUpdateOptimizedCheckoutExclusions();

		expect( elements.update ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'does not re-send the exclusions already applied at element creation', async () => {
		stripeUtils.getExcludedPaymentMethodTypesForBillingCountry.mockReturnValue(
			[ 'affirm' ]
		);
		const elements = await mountOCElement();

		paymentProcessing.maybeUpdateOptimizedCheckoutExclusions();

		expect( elements.update ).not.toHaveBeenCalled();
	} );

	it( 'pushes new exclusions when the billing country changes', async () => {
		const elements = await mountOCElement();
		stripeUtils.getExcludedPaymentMethodTypesForBillingCountry.mockReturnValue(
			[ 'ideal' ]
		);
		paymentProcessing.maybeUpdateOptimizedCheckoutExclusions();

		// A hidden method must re-surface when the new country allows it.
		stripeUtils.getExcludedPaymentMethodTypesForBillingCountry.mockReturnValue(
			[]
		);
		paymentProcessing.maybeUpdateOptimizedCheckoutExclusions();

		expect( elements.update ).toHaveBeenCalledTimes( 2 );
		expect( elements.update ).toHaveBeenLastCalledWith( {
			excludedPaymentMethodTypes: [],
		} );
	} );

	it( 'is a no-op when Optimized Checkout is disabled', () => {
		stripeUtils.getStripeServerData.mockReturnValue( {
			...BASE_SERVER_DATA,
			shouldShowOptimizedCheckout: false,
		} );

		paymentProcessing.maybeUpdateOptimizedCheckoutExclusions();

		expect(
			stripeUtils.getExcludedPaymentMethodTypesForBillingCountry
		).not.toHaveBeenCalled();
	} );

	it( 'is a no-op when the elements instance has no update()', async () => {
		const elements = await mountOCElement();
		delete elements.update;
		stripeUtils.getExcludedPaymentMethodTypesForBillingCountry.mockClear();

		expect( () =>
			paymentProcessing.maybeUpdateOptimizedCheckoutExclusions()
		).not.toThrow();
		expect(
			stripeUtils.getExcludedPaymentMethodTypesForBillingCountry
		).not.toHaveBeenCalled();
	} );
} );
