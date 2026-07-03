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
const STRIPE = { elements: jest.fn(), initCheckout: jest.fn() };
// dahlia+: initCheckout() is a throwing stub and the replacement method exists.
const DAHLIA_STRIPE = {
	elements: jest.fn(),
	initCheckout: jest.fn(),
	initCheckoutElementsSdk: jest.fn(),
};

describe( 'PaymentElements adaptive pricing selection', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'uses the Adaptive Pricing container when Adaptive Pricing is enabled', () => {
		getBlocksConfiguration.mockReturnValue( {
			isAdaptivePricingEnabled: true,
			paymentMethodsConfig: { card: { isReusable: false } },
			cartTotal: 1000,
			currency: 'USD',
			shouldShowOptimizedCheckout: false,
			isAdmin: false,
		} );

		renderFields( buildApi( STRIPE ) );

		expect( CheckoutContainer ).toHaveBeenCalled();
		expect( PaymentProcessor ).not.toHaveBeenCalled();
	} );

	// Regression guard for the OCS render drop (#5618): on dahlia+ Stripe.js the
	// CheckoutProvider's synchronous initCheckout() throw would leave the Payment
	// Element unrendered, so Adaptive Pricing must fall back to standard Elements.
	it( 'falls back to standard elements on dahlia+ even when Adaptive Pricing is enabled', () => {
		getBlocksConfiguration.mockReturnValue( {
			isAdaptivePricingEnabled: true,
			paymentMethodsConfig: { card: { isReusable: false } },
			cartTotal: 1000,
			currency: 'USD',
			shouldShowOptimizedCheckout: false,
			isAdmin: false,
		} );

		renderFields( buildApi( DAHLIA_STRIPE ) );

		expect( CheckoutContainer ).not.toHaveBeenCalled();
		expect( PaymentProcessor ).toHaveBeenCalled();
	} );

	it( 'uses the standard elements flow when Adaptive Pricing is disabled', () => {
		getBlocksConfiguration.mockReturnValue( {
			isAdaptivePricingEnabled: false,
			paymentMethodsConfig: { card: { isReusable: false } },
			cartTotal: 1000,
			currency: 'USD',
			shouldShowOptimizedCheckout: false,
			isAdmin: false,
		} );

		renderFields( buildApi( STRIPE ) );

		expect( CheckoutContainer ).not.toHaveBeenCalled();
		expect( PaymentProcessor ).toHaveBeenCalled();
	} );
} );
