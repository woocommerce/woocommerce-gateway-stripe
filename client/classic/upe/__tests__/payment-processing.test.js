import * as paymentProcessing from '../payment-processing';
import * as stripeUtils from 'wcstripe/stripe-utils';

jest.mock( 'wcstripe/stripe-utils', () => ( {
	appendPaymentMethodIdToForm: jest.fn(),
	appendPaymentIntentIdToForm: jest.fn(),
	appendCheckoutSessionIdToForm: jest.fn(),
	getPaymentMethodTypes: jest.fn( () => [ 'card' ] ),
	initializeUPEAppearance: jest.fn( () => ( {} ) ),
	isLinkEnabled: jest.fn( () => false ),
	getDefaultValues: jest.fn( () => ( {} ) ),
	getUpeSettings: jest.fn( () => ( {} ) ),
	showErrorCheckout: jest.fn(),
	showErrorPaymentMethod: jest.fn(),
	appendSetupIntentToForm: jest.fn(),
	unblockBlockCheckout: jest.fn(),
	resetBlockCheckoutPaymentState: jest.fn(),
	getAdditionalSetupIntentData: jest.fn(),
	validateBlikCode: jest.fn(),
	getExcludedPaymentMethodTypes: jest.fn( () => [] ),
	// Read from window at call time so reloading with different globals works.
	getStripeServerData: () => global.wc_stripe_upe_params,
} ) );

jest.mock( 'wcstripe/styles/upe', () => ( {
	getFontRulesFromPage: jest.fn( () => [] ),
} ) );

jest.mock(
	'wcstripe/optimized-checkout/handle-display-of-payment-instructions',
	() => ( { handleDisplayOfPaymentInstructions: jest.fn() } )
);

jest.mock(
	'wcstripe/optimized-checkout/handle-display-of-saving-checkbox',
	() => ( { handleDisplayOfSavingCheckbox: jest.fn() } )
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
	for ( let i = 0; i < 10; i++ ) {
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
	isOCEnabled: false,
	isPaymentNeeded: true,
	isChangingPayment: false,
};

const createMockPaymentElement = () => ( {
	mount: jest.fn(),
	on: jest.fn(),
	update: jest.fn(),
} );

const createMockElements = () => ( {
	create: jest.fn( () => createMockPaymentElement() ),
	submit: jest.fn( () => Promise.resolve( {} ) ),
	loadActions: jest.fn( () => Promise.resolve( { type: 'success' } ) ),
	createPaymentElement: jest.fn( () => createMockPaymentElement() ),
	createCurrencySelectorElement: jest.fn( () => ( { mount: jest.fn() } ) ),
} );

const createMockApi = ( checkoutElements ) => {
	const standardElements = createMockElements();
	const stripe = {
		elements: jest.fn( () => standardElements ),
		initCheckout: jest.fn( () => Promise.resolve( checkoutElements ) ),
		createPaymentMethod: jest.fn( () =>
			Promise.resolve( { paymentMethod: { id: 'pm_test_123' } } )
		),
	};
	return {
		getStripe: jest.fn( () => stripe ),
		checkoutSessionsCreateSession: jest.fn( () =>
			Promise.resolve( { data: { client_secret: 'cs_test_abc' } } )
		),
		createIntent: jest.fn(),
		initSetupIntent: jest.fn(),
		_stripe: stripe,
		_standardElements: standardElements,
	};
};

const createMockForm = () => {
	const f = {};
	f.addClass = jest.fn( () => f );
	f.removeClass = jest.fn( () => f );
	f.block = jest.fn( () => f );
	f.unblock = jest.fn( () => f );
	f.trigger = jest.fn( () => f );
	f.attr = jest.fn( () => 'checkout' );
	return f;
};

describe( 'payment-processing', () => {
	afterEach( () => {
		jest.resetModules();
	} );

	describe( 'adaptive pricing disabled (isAdaptivePricingEnabled = false)', () => {
		let originalServerData;

		beforeEach( () => {
			originalServerData = global.wc_stripe_upe_params;
			global.wc_stripe_upe_params = {
				...BASE_SERVER_DATA,
				isAdaptivePricingEnabled: false,
			};
			paymentProcessing.initializeUPEComponents();
		} );

		afterEach( () => {
			global.wc_stripe_upe_params = originalServerData;
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

				expect( api._stripe.initCheckout ).not.toHaveBeenCalled();
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
		let originalServerData;

		beforeEach( () => {
			originalServerData = global.wc_stripe_upe_params;
			global.wc_stripe_upe_params = {
				...BASE_SERVER_DATA,
				isAdaptivePricingEnabled: true,
			};
			paymentProcessing.initializeUPEComponents();
		} );

		afterEach( () => {
			global.wc_stripe_upe_params = originalServerData;
			jest.clearAllMocks();
		} );

		describe( 'createStripePaymentElement (via mountStripePaymentElement)', () => {
			it( 'calls initCheckout with the client_secret from the session', async () => {
				const checkoutElements = createMockElements();
				checkoutElements.loadActions.mockResolvedValue( {
					type: 'success',
				} );
				const api = createMockApi( checkoutElements );
				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';

				await paymentProcessing.mountStripePaymentElement( api, dom );

				expect( api.checkoutSessionsCreateSession ).toHaveBeenCalled();
				expect( api._stripe.initCheckout ).toHaveBeenCalledWith(
					expect.objectContaining( { clientSecret: 'cs_test_abc' } )
				);
				expect( api._stripe.elements ).not.toHaveBeenCalled();
			} );

			it( 'uses createPaymentElement (not create) when using initCheckout', async () => {
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

			it( 'falls back to standard elements when session creation fails', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				api.checkoutSessionsCreateSession.mockRejectedValue(
					new Error( 'Network error' )
				);
				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';

				await paymentProcessing.mountStripePaymentElement( api, dom );

				expect( api._stripe.elements ).toHaveBeenCalled();
				expect( api._stripe.initCheckout ).not.toHaveBeenCalled();
			} );

			it( 'falls back to standard elements when client_secret is absent', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				api.checkoutSessionsCreateSession.mockResolvedValue( {
					data: {},
				} );
				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';

				await paymentProcessing.mountStripePaymentElement( api, dom );

				expect( api._stripe.elements ).toHaveBeenCalled();
				expect( api._stripe.initCheckout ).not.toHaveBeenCalled();
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

			it( 'calls loadActions → confirm → appends session ID → submits form', async () => {
				const mockActions = {
					confirm: jest.fn().mockResolvedValue( {
						session: { id: 'cs_session_xyz' },
					} ),
				};
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );

				await mountAndConfigureForProcess( api, checkoutElements, {
					type: 'success',
					actions: mockActions,
				} );

				const form = createMockForm();
				paymentProcessing.processPayment( api, form, 'card' );
				await flushPromises();

				expect( mockActions.confirm ).toHaveBeenCalledWith( {
					returnUrl: window.location.href,
					redirect: 'if_required',
				} );
				expect(
					stripeUtils.appendCheckoutSessionIdToForm
				).toHaveBeenCalledWith( form, 'cs_session_xyz' );
				expect( form.trigger ).toHaveBeenCalledWith( 'submit' );
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

			it( 'does not call validateElements or appendPaymentMethodIdToForm', async () => {
				const mockActions = {
					confirm: jest.fn().mockResolvedValue( {
						session: { id: 'cs_session_xyz' },
					} ),
				};
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );

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
	} );
} );
