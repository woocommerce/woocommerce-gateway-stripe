import * as paymentProcessing from '../payment-processing';
import * as stripeUtils from 'wcstripe/stripe-utils';

jest.mock( 'wcstripe/stripe-utils', () => ( {
	appendCheckoutSessionIdToForm: jest.fn(),
	appendPaymentIntentIdToForm: jest.fn(),
	appendPaymentMethodIdToForm: jest.fn(),
	appendSetupIntentToForm: jest.fn(),
	getAdditionalSetupIntentData: jest.fn().mockReturnValue( {} ),
	getDefaultValues: jest.fn().mockReturnValue( {} ),
	getExcludedPaymentMethodTypes: jest.fn().mockReturnValue( [] ),
	getPaymentMethodTypes: jest.fn().mockReturnValue( [ 'card' ] ),
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

	initializeUPEAppearance: jest.fn().mockReturnValue( {} ),
	isLinkEnabled: jest.fn().mockReturnValue( false ),
	resetBlockCheckoutPaymentState: jest.fn(),
	showErrorCheckout: jest.fn(),
	showErrorPaymentMethod: jest.fn(),
	unblockBlockCheckout: jest.fn(),
	validateBlikCode: jest.fn(),
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

const createMockElements = () => {
	const checkoutActions = {
		runServerUpdate: jest.fn( async ( userFunction ) => {
			await userFunction();
			return {
				type: 'success',
				session: { id: MOCK_AP_CHECKOUT_SESSION_ID },
			};
		} ),
		confirm: jest.fn( () => Promise.resolve( {} ) ),
	};
	return {
		create: jest.fn( () => createMockPaymentElement() ),
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
		initCheckout: jest.fn( () => Promise.resolve( checkoutElements ) ),
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
		createIntent: jest.fn(),
		initSetupIntent: jest.fn(),
		_stripe: stripe,
		_standardElements: standardElements,
	};
};

const createMockForm = ( { savePaymentMethodChecked = false } = {} ) => {
	const f = {};
	f.addClass = jest.fn( () => f );
	f.removeClass = jest.fn( () => f );
	f.block = jest.fn( () => f );
	f.unblock = jest.fn( () => f );
	f.trigger = jest.fn( () => f );
	f.attr = jest.fn( () => 'checkout' );
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
		beforeEach( () => {
			stripeUtils.getStripeServerData.mockReturnValue( {
				...BASE_SERVER_DATA,
				isAdaptivePricingEnabled: true,
			} );
			paymentProcessing.initializeUPEComponents();
		} );

		afterEach( () => {
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

			it( 'falls back to standard elements when client_secret or session_id is absent', async () => {
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

			it( 'falls back to standard elements when session_id is absent', async () => {
				const checkoutElements = createMockElements();
				const api = createMockApi( checkoutElements );
				api.checkoutSessionsCreateSession.mockResolvedValue( {
					data: { client_secret: MOCK_AP_CHECKOUT_CLIENT_SECRET },
				} );
				const dom = document.createElement( 'div' );
				dom.dataset.paymentMethodType = 'card';

				await paymentProcessing.mountStripePaymentElement( api, dom );

				expect( api._stripe.elements ).toHaveBeenCalled();
				expect( api._stripe.initCheckout ).not.toHaveBeenCalled();
			} );

			it( 'uses runServerUpdate to call checkoutSessionsUpdateSession after maybeUpdateAdaptivePricingCheckoutSession', async () => {
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
				).toHaveBeenCalledWith( MOCK_AP_CHECKOUT_SESSION_ID );
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

			it( 'passes savePaymentMethod true when logged in and the save card checkbox is checked', async () => {
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

				const apServerData = {
					...BASE_SERVER_DATA,
					isAdaptivePricingEnabled: true,
					isLoggedIn: true,
				};

				stripeUtils.getStripeServerData.mockReturnValue( apServerData );

				try {
					const form = createMockForm( {
						savePaymentMethodChecked: true,
					} );
					paymentProcessing.processPayment( api, form, 'card' );
					await flushPromises();

					expect( mockActions.confirm ).toHaveBeenCalledWith( {
						returnUrl: window.location.href,
						redirect: 'if_required',
						savePaymentMethod: true,
					} );
				} finally {
					stripeUtils.getStripeServerData.mockReturnValue(
						apServerData
					);
				}
			} );

			it( 'does not pass savePaymentMethod for guests even when the save card checkbox is checked', async () => {
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

				const apServerData = {
					...BASE_SERVER_DATA,
					isAdaptivePricingEnabled: true,
					isLoggedIn: false,
				};

				stripeUtils.getStripeServerData.mockReturnValue( apServerData );

				try {
					const form = createMockForm( {
						savePaymentMethodChecked: true,
					} );
					paymentProcessing.processPayment( api, form, 'card' );
					await flushPromises();

					expect( mockActions.confirm ).toHaveBeenCalledWith( {
						returnUrl: window.location.href,
						redirect: 'if_required',
					} );
				} finally {
					stripeUtils.getStripeServerData.mockReturnValue( {
						...BASE_SERVER_DATA,
						isAdaptivePricingEnabled: true,
					} );
				}
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

			it( 'shows error when confirm succeeds but session is missing', async () => {
				const mockActions = {
					confirm: jest.fn().mockResolvedValue( {
						/* no session */
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

				expect( mockActions.confirm ).toHaveBeenCalled();
				expect( stripeUtils.showErrorCheckout ).toHaveBeenCalledWith(
					'Payment could not be completed. Please try again.'
				);
				expect(
					stripeUtils.appendCheckoutSessionIdToForm
				).not.toHaveBeenCalled();
				expect( form.trigger ).not.toHaveBeenCalledWith( 'submit' );
			} );

			it( 'shows error when actions.confirm resolves to an error object', async () => {
				const mockActions = {
					confirm: jest.fn().mockResolvedValue( {
						type: 'error',
						error: { message: 'Card declined' },
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

				expect( mockActions.confirm ).toHaveBeenCalled();
				expect( stripeUtils.showErrorCheckout ).toHaveBeenCalledWith(
					'Card declined'
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

		it( 'passes accordion layout with radios:false when Optimized Checkout is enabled with default layout', async () => {
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
			expect( paymentElementOptions.layout.radios ).toBe( false );
			expect( paymentElementOptions.layout.spacedAccordionItems ).toBe(
				false
			);
		} );

		it( 'passes accordion layout with radios:false when Optimized Checkout is enabled with an explicit layout - accordion', async () => {
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
			expect( paymentElementOptions.layout.radios ).toBe( false );
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
} );
