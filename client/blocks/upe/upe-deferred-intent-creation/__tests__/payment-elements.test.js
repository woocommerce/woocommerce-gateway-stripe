import { cloneElement } from 'react';
import { render } from '@testing-library/react';
import { getDeferredIntentCreationUPEFields } from 'wcstripe/blocks/upe/upe-deferred-intent-creation/payment-elements';
import { CheckoutContainer } from 'wcstripe/blocks/checkout-sessions/checkout-container';
import PaymentProcessor from 'wcstripe/blocks/upe/upe-deferred-intent-creation/payment-processor';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

jest.mock(
	'@woocommerce/blocks-checkout',
	() => ( {
		StoreNotice: jest.fn( ( { children } ) => <div>{ children }</div> ),
	} ),
	{ virtual: true }
);

jest.mock( '@stripe/react-stripe-js', () => ( {
	Elements: jest.fn( ( { children } ) => <div>{ children }</div> ),
} ) );

// Identify the standard (non-AP) elements path by its inner PaymentProcessor.
jest.mock(
	'wcstripe/blocks/upe/upe-deferred-intent-creation/payment-processor',
	() => jest.fn( () => <div>elements-container</div> )
);

jest.mock( 'wcstripe/blocks/checkout-sessions/checkout-container', () => ( {
	CheckoutContainer: jest.fn( () => <div>checkout-container</div> ),
} ) );

jest.mock( 'wcstripe/blocks/utils', () => ( {
	getBlocksConfiguration: jest.fn(),
	shouldSetupOffSessionPayment: jest.fn( () => false ),
} ) );

jest.mock( 'wcstripe/stripe-utils', () => ( {
	getPaymentMethodTypes: jest.fn( () => [] ),
	getExcludedPaymentMethodTypes: jest.fn( () => [] ),
} ) );

jest.mock( 'wcstripe/stripe-utils/upe-appearance', () => ( {
	initializeUPEAppearance: jest.fn( () => ( {} ) ),
} ) );

jest.mock( 'wcstripe/styles/upe', () => ( {
	getFontRulesFromPage: jest.fn( () => [] ),
} ) );

const LoadingMask = ( { children } ) => <div>{ children }</div>;

const renderFields = ( api ) => {
	// The Blocks framework injects `components` at render time; supply it here.
	const fields = getDeferredIntentCreationUPEFields(
		'card',
		[],
		api,
		'',
		'',
		false,
		true // supportsDeferredIntent — avoids the intent-creation request
	);
	return render( cloneElement( fields, { components: { LoadingMask } } ) );
};

const buildApi = ( stripe ) => ( {
	getStripe: jest.fn( () => stripe ),
	createIntent: jest.fn(),
	initSetupIntent: jest.fn(),
} );

// clover: working initCheckout, no replacement method.
const CLOVER_STRIPE = { elements: jest.fn(), initCheckout: jest.fn() };
// dahlia+: initCheckout() remains as a throwing stub and the renamed method exists.
const DAHLIA_STRIPE = {
	elements: jest.fn(),
	initCheckout: jest.fn(),
	initCheckoutElementsSdk: jest.fn(),
};

describe( 'PaymentElements adaptive pricing selection', () => {
	beforeEach( () => {
		getBlocksConfiguration.mockReturnValue( {
			isAdaptivePricingEnabled: true,
			paymentMethodsConfig: { card: { isReusable: false } },
			cartTotal: 1000,
			currency: 'USD',
			shouldShowOptimizedCheckout: false,
			isAdmin: false,
		} );
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'uses the Adaptive Pricing container when Stripe.js supports initCheckout', () => {
		renderFields( buildApi( CLOVER_STRIPE ) );

		expect( CheckoutContainer ).toHaveBeenCalled();
		expect( PaymentProcessor ).not.toHaveBeenCalled();
	} );

	it( 'falls back to the standard elements flow when Stripe.js exposes initCheckoutElementsSdk (dahlia+)', () => {
		renderFields( buildApi( DAHLIA_STRIPE ) );

		expect( CheckoutContainer ).not.toHaveBeenCalled();
		expect( PaymentProcessor ).toHaveBeenCalled();
	} );

	it( 'falls back to the standard elements flow when initCheckout is absent (older Stripe.js)', () => {
		renderFields( buildApi( { elements: jest.fn() } ) );

		expect( CheckoutContainer ).not.toHaveBeenCalled();
		expect( PaymentProcessor ).toHaveBeenCalled();
	} );
} );
