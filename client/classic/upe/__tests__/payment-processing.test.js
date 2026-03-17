import {
	initializeUPEComponents,
	mountStripePaymentElement,
} from 'wcstripe/classic/upe/payment-processing';
import { getStripeServerData } from 'wcstripe/stripe-utils';

jest.mock( 'wcstripe/stripe-utils', () => ( {
	initializeUPEAppearance: jest.fn().mockReturnValue( {} ),
	isLinkEnabled: jest.fn().mockReturnValue( false ),
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
	getDefaultValues: jest.fn().mockReturnValue( {} ),
	getPaymentMethodTypes: jest.fn().mockReturnValue( [ 'card' ] ),
	getExcludedPaymentMethodTypes: jest.fn().mockReturnValue( [] ),
	showErrorPaymentMethod: jest.fn(),
	showErrorCheckout: jest.fn(),
	appendPaymentMethodIdToForm: jest.fn(),
	appendPaymentIntentIdToForm: jest.fn(),
	appendSetupIntentToForm: jest.fn(),
	unblockBlockCheckout: jest.fn(),
	resetBlockCheckoutPaymentState: jest.fn(),
	getAdditionalSetupIntentData: jest.fn().mockReturnValue( {} ),
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

describe( 'createStripePaymentElement layout option', () => {
	let domElement;

	beforeEach( () => {
		initializeUPEComponents();

		domElement = document.createElement( 'div' );
		domElement.dataset.paymentMethodType = 'card';
		document.body.appendChild( domElement );
	} );

	afterEach( () => {
		document.body.removeChild( domElement );
	} );

	it( 'passes layout:tabs when Optimized Checkout is disabled', async () => {
		getStripeServerData.mockReturnValue( {
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
		await mountStripePaymentElement( buildApi( stripe ), domElement );

		expect( mockElements.create ).toHaveBeenCalledTimes( 1 );
		const [ , paymentElementOptions ] = mockElements.create.mock.calls[ 0 ];
		expect( paymentElementOptions.layout ).toStrictEqual( {
			type: 'tabs',
		} );
	} );

	it( 'passes accordion layout with radios:false when Optimized Checkout is enabled with default layout', async () => {
		getStripeServerData.mockReturnValue( {
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
		await mountStripePaymentElement( buildApi( stripe ), domElement );

		expect( mockElements.create ).toHaveBeenCalledTimes( 1 );
		const [ , paymentElementOptions ] = mockElements.create.mock.calls[ 0 ];
		expect( paymentElementOptions.layout.type ).toBe( 'accordion' );
		expect( paymentElementOptions.layout.radios ).toBe( false );
		expect( paymentElementOptions.layout.spacedAccordionItems ).toBe(
			false
		);
	} );

	it( 'passes accordion layout with radios:false when Optimized Checkout is enabled with an explicit layout - accordion', async () => {
		getStripeServerData.mockReturnValue( {
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
		await mountStripePaymentElement( buildApi( stripe ), domElement );

		expect( mockElements.create ).toHaveBeenCalledTimes( 1 );
		const [ , paymentElementOptions ] = mockElements.create.mock.calls[ 0 ];
		expect( paymentElementOptions.layout.type ).toBe( 'accordion' );
		expect( paymentElementOptions.layout.radios ).toBe( false );
		expect( paymentElementOptions.layout.spacedAccordionItems ).toBe(
			false
		);
	} );

	it( 'passes custom OCLayout when Optimized Checkout is enabled with an explicit layout - tabs', async () => {
		getStripeServerData.mockReturnValue( {
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
		await mountStripePaymentElement( buildApi( stripe ), domElement );

		expect( mockElements.create ).toHaveBeenCalledTimes( 1 );
		const [ , paymentElementOptions ] = mockElements.create.mock.calls[ 0 ];
		expect( paymentElementOptions.layout.type ).toBe( 'tabs' );
		expect( paymentElementOptions.layout.radios ).toBeUndefined();
		expect(
			paymentElementOptions.layout.spacedAccordionItems
		).toBeUndefined();
	} );
} );
