import { cloneElement } from 'react';
import { render, waitFor } from '@testing-library/react';
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

const renderFields = (
	api,
	{ paymentMethodId = 'card', supportsDeferredIntent = true } = {}
) => {
	// The Blocks framework injects `components` at render time; supply it here.
	const fields = getDeferredIntentCreationUPEFields(
		paymentMethodId,
		[],
		api,
		'',
		'',
		false,
		supportsDeferredIntent
	);
	return render( cloneElement( fields, { components: { LoadingMask } } ) );
};

const buildApi = ( stripe ) => ( {
	getStripe: jest.fn( () => stripe ),
	createIntent: jest.fn(),
	initSetupIntent: jest.fn(),
} );

const STRIPE = { elements: jest.fn(), initCheckoutElementsSdk: jest.fn() };

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

	it( 'falls back to the standard elements flow when a legacy Stripe.js lacks initCheckoutElementsSdk', () => {
		getBlocksConfiguration.mockReturnValue( {
			isAdaptivePricingEnabled: true,
			paymentMethodsConfig: { card: { isReusable: false } },
			cartTotal: 1000,
			currency: 'USD',
			shouldShowOptimizedCheckout: false,
			isAdmin: false,
		} );

		// A legacy v3 Stripe.js (loaded by another plugin or a manual snippet)
		// won window.Stripe; it exposes elements() but not initCheckoutElementsSdk.
		renderFields( buildApi( { elements: jest.fn() } ) );

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

describe( 'PaymentElements non-deferred intent creation', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	it.each( [ 'blik', 'acss_debit' ] )(
		'forwards the order ID and key when creating a %s PaymentIntent',
		async ( paymentMethodId ) => {
			getBlocksConfiguration.mockReturnValue( {
				isAdaptivePricingEnabled: false,
				isPaymentNeeded: true,
				orderId: 123,
				orderKey: 'wc_order_test_key',
				paymentMethodsConfig: {
					[ paymentMethodId ]: {
						isReusable: false,
						title: paymentMethodId,
					},
				},
				cartTotal: 1000,
				currency: 'USD',
				shouldShowOptimizedCheckout: false,
				isAdmin: false,
			} );

			const api = buildApi( STRIPE );
			api.createIntent.mockResolvedValue( {
				id: 'pi_test',
				client_secret: 'pi_test_secret',
			} );

			renderFields( api, {
				paymentMethodId,
				supportsDeferredIntent: false,
			} );

			await waitFor( () => {
				expect( api.createIntent ).toHaveBeenCalledWith(
					123,
					paymentMethodId,
					'wc_order_test_key'
				);
			} );
			expect( api.initSetupIntent ).not.toHaveBeenCalled();
		}
	);
} );
