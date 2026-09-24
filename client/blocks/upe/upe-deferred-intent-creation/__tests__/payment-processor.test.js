import { render } from '@testing-library/react';
import { useElements, useStripe } from '@stripe/react-stripe-js';
import PaymentProcessor from 'wcstripe/blocks/upe/upe-deferred-intent-creation/payment-processor';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import { getExcludedPaymentMethodTypesForBillingCountry } from 'wcstripe/stripe-utils';

jest.mock(
	'@woocommerce/blocks-registry',
	() => ( {
		getPaymentMethods: jest.fn( () => ( {} ) ),
	} ),
	{ virtual: true }
);

jest.mock(
	'@woocommerce/blocks-checkout',
	() => ( {
		ValidatedTextInput: jest.fn( () => <input /> ),
	} ),
	{ virtual: true }
);

jest.mock( '@stripe/react-stripe-js', () => ( {
	PaymentElement: jest.fn( () => <div>payment-element</div> ),
	useStripe: jest.fn(),
	useElements: jest.fn(),
} ) );

jest.mock( 'wcstripe/blocks/upe/hooks', () => ( {
	usePaymentCompleteHandler: jest.fn(),
	usePaymentFailHandler: jest.fn(),
} ) );

jest.mock( 'wcstripe/blocks/utils', () => ( {
	getBlocksConfiguration: jest.fn(),
	getStripeElementOptions: jest.fn( () => ( {} ) ),
	getStripeImageUrl: jest.fn( () => '' ),
} ) );

jest.mock( 'wcstripe/api', () => jest.fn() );

jest.mock( 'wcstripe/stripe-utils', () => ( {
	validateBlikCode: jest.fn(),
	getExcludedPaymentMethodTypesForBillingCountry: jest.fn( () => [] ),
} ) );

jest.mock( 'wcstripe/stripe-utils/cash-app-limit-notice-handler', () => ( {
	maybeShowCashAppLimitNotice: jest.fn(),
	removeCashAppLimitNotice: jest.fn(),
} ) );

jest.mock( 'wcstripe/stripe-utils/upe-appearance', () => ( {
	invalidateAppearanceCache: jest.fn(),
	initializeUPEAppearance: jest.fn( () => ( {} ) ),
} ) );

jest.mock( 'wcstripe/styles/upe', () => ( {
	sampleFontFamily: jest.fn( () => '' ),
} ) );

jest.mock(
	'wcstripe/optimized-checkout/handle-display-of-payment-instructions',
	() => ( {
		handleDisplayOfPaymentInstructions: jest.fn(),
	} )
);

jest.mock( 'wcstripe/optimized-checkout/apply-styles', () => ( {
	applyStyles: jest.fn(),
} ) );

jest.mock(
	'wcstripe/optimized-checkout/handle-display-of-saving-checkbox',
	() => ( {
		handleDisplayOfSavingCheckbox: jest.fn(),
	} )
);

jest.mock( 'wcstripe/blocks/wait-for-payment-element-completion', () => ( {
	waitForPaymentElementCompletion: jest.fn(),
} ) );

const renderProcessor = ( {
	billingCountry = 'US',
	paymentMethodId = 'card',
} = {} ) => {
	const props = {
		api: { getStripe: jest.fn() },
		activePaymentMethod: 'stripe',
		eventRegistration: {
			onPaymentSetup: jest.fn(),
			onCheckoutSuccess: jest.fn(),
			onCheckoutFail: jest.fn(),
		},
		emitResponse: {},
		paymentMethodId,
		upeMethods: { card: 'stripe' },
		errorMessage: '',
		shouldSavePayment: false,
		fingerprint: '',
		billing: { billingAddress: { country: billingCountry } },
	};
	const view = render( <PaymentProcessor { ...props } /> );

	return {
		...view,
		rerenderWithCountry: ( country ) =>
			view.rerender(
				<PaymentProcessor
					{ ...props }
					billing={ { billingAddress: { country } } }
				/>
			),
	};
};

describe( 'PaymentProcessor billing-country exclusions', () => {
	beforeEach( () => {
		useStripe.mockReturnValue( {} );
		getExcludedPaymentMethodTypesForBillingCountry.mockImplementation(
			( country ) => [ `excluded-for-${ country || 'unknown' }` ]
		);
		getBlocksConfiguration.mockReturnValue( {
			shouldShowOptimizedCheckout: true,
			paymentMethodsConfig: {
				card: { isReusable: true, supportsDeferredIntent: true },
			},
		} );
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'updates the element with the exclusions for the billing country', () => {
		const elements = { update: jest.fn() };
		useElements.mockReturnValue( elements );

		renderProcessor( { billingCountry: 'NL' } );

		expect(
			getExcludedPaymentMethodTypesForBillingCountry
		).toHaveBeenCalledWith( 'NL' );
		expect( elements.update ).toHaveBeenCalledWith( {
			excludedPaymentMethodTypes: [ 'excluded-for-NL' ],
		} );
	} );

	it( 'recomputes the exclusions when the billing country changes', () => {
		const elements = { update: jest.fn() };
		useElements.mockReturnValue( elements );

		const { rerenderWithCountry } = renderProcessor( {
			billingCountry: 'NL',
		} );
		rerenderWithCountry( 'US' );

		expect(
			getExcludedPaymentMethodTypesForBillingCountry
		).toHaveBeenLastCalledWith( 'US' );
		expect( elements.update ).toHaveBeenLastCalledWith( {
			excludedPaymentMethodTypes: [ 'excluded-for-US' ],
		} );
	} );

	it( 'skips the update for a non-deferred method whose element has no mode', () => {
		// BLIK/ACSS render their own Payment Element created without a `mode`;
		// excludedPaymentMethodTypes only applies to the mode-based OC element, so
		// calling update() for them throws a Stripe IntegrationError.
		getBlocksConfiguration.mockReturnValue( {
			shouldShowOptimizedCheckout: true,
			paymentMethodsConfig: {
				blik: { supportsDeferredIntent: false },
			},
		} );
		const elements = { update: jest.fn() };
		useElements.mockReturnValue( elements );

		renderProcessor( { paymentMethodId: 'blik' } );

		expect(
			getExcludedPaymentMethodTypesForBillingCountry
		).not.toHaveBeenCalled();
		expect( elements.update ).not.toHaveBeenCalled();
	} );

	it( 'does not update exclusions when Optimized Checkout is disabled', () => {
		getBlocksConfiguration.mockReturnValue( {
			shouldShowOptimizedCheckout: false,
			paymentMethodsConfig: { card: { isReusable: true } },
		} );
		const elements = { update: jest.fn() };
		useElements.mockReturnValue( elements );

		renderProcessor();

		expect(
			getExcludedPaymentMethodTypesForBillingCountry
		).not.toHaveBeenCalled();
		expect( elements.update ).not.toHaveBeenCalled();
	} );

	it( 'skips the update when the elements instance has no update()', () => {
		// Adaptive Pricing's initCheckoutElementsSdk() returns a Checkout
		// object without update(); the effect must not throw there.
		useElements.mockReturnValue( {} );

		expect( () => renderProcessor() ).not.toThrow();
		expect(
			getExcludedPaymentMethodTypesForBillingCountry
		).not.toHaveBeenCalled();
	} );

	it( 'skips the update when elements is not available', () => {
		useElements.mockReturnValue( null );

		expect( () => renderProcessor() ).not.toThrow();
		expect(
			getExcludedPaymentMethodTypesForBillingCountry
		).not.toHaveBeenCalled();
	} );
} );

describe( 'PaymentProcessor save checkbox', () => {
	let checkbox;

	beforeEach( () => {
		useStripe.mockReturnValue( {} );
		// The Blocks store-level save checkbox, shared by every Stripe entry.
		const wrapper = document.createElement( 'div' );
		wrapper.className =
			'wc-block-components-payment-methods__save-card-info';
		checkbox = document.createElement( 'input' );
		checkbox.type = 'checkbox';
		wrapper.appendChild( checkbox );
		document.body.appendChild( wrapper );
	} );

	afterEach( () => {
		checkbox.parentElement.remove();
		jest.clearAllMocks();
	} );

	const toggleSaveCheckbox = () => {
		checkbox.checked = true;
		checkbox.dispatchEvent( new Event( 'change' ) );
	};

	it( 'sets setupFutureUsage on the Optimized Checkout element', () => {
		getBlocksConfiguration.mockReturnValue( {
			shouldShowOptimizedCheckout: true,
			paymentMethodsConfig: {
				card: { isReusable: true, supportsDeferredIntent: true },
			},
		} );
		const elements = { update: jest.fn() };
		useElements.mockReturnValue( elements );

		renderProcessor();
		toggleSaveCheckbox();

		expect( elements.update ).toHaveBeenCalledWith( {
			setupFutureUsage: 'off_session',
		} );
	} );

	it( 'does not set setupFutureUsage on a non-deferred element, which has no mode', () => {
		// ACSS renders its own element from a client secret, without a `mode`;
		// Stripe rejects setupFutureUsage there, and the method is saved through
		// the server instead.
		getBlocksConfiguration.mockReturnValue( {
			shouldShowOptimizedCheckout: true,
			paymentMethodsConfig: {
				acss_debit: { isReusable: true, supportsDeferredIntent: false },
			},
		} );
		const elements = { update: jest.fn() };
		useElements.mockReturnValue( elements );

		renderProcessor( { paymentMethodId: 'acss_debit' } );
		toggleSaveCheckbox();

		expect( elements.update ).not.toHaveBeenCalled();
	} );
} );
