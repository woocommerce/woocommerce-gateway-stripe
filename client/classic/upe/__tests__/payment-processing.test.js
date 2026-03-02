/**
 * Tests for the mount-guard logic added to mountStripePaymentElement in
 * client/classic/upe/payment-processing.js.
 *
 * Covers:
 *  1. initializeUPEComponents sets mountedDomElement to null.
 *  2. First mount records the container and calls upeElement.mount.
 *  3. Same-container guard: second mount to the same node is a no-op.
 *  4. Cross-container guard: mount to a different node unmounts first.
 *  5. unmount() errors are swallowed and mount still proceeds.
 *  6. Null upeElement (creation failure) exits without calling mount.
 */

import {
	initializeUPEComponents,
	mountStripePaymentElement,
} from 'wcstripe/classic/upe/payment-processing';

// ---------------------------------------------------------------------------
// Module-level mocks (hoisted before imports by babel-jest)
// ---------------------------------------------------------------------------

jest.mock( 'wcstripe/stripe-utils', () => ( {
	getStripeServerData: jest.fn( () => ( {
		paymentMethodsConfig: { card: { supportsDeferredIntent: true } },
		isAdaptivePricingEnabled: false,
		cartTotal: 1000,
		currency: 'usd',
		isOCEnabled: false,
		isCheckout: false,
		isPaymentNeeded: true,
		isChangingPayment: false,
	} ) ),
	initializeUPEAppearance: jest.fn( () => ( {} ) ),
	getPaymentMethodTypes: jest.fn( () => [ 'card' ] ),
	isLinkEnabled: jest.fn( () => false ),
	getDefaultValues: jest.fn( () => ( {} ) ),
	getUpeSettings: jest.fn( () => ( {} ) ),
	showErrorCheckout: jest.fn(),
	showErrorPaymentMethod: jest.fn(),
	appendPaymentMethodIdToForm: jest.fn(),
	appendPaymentIntentIdToForm: jest.fn(),
	appendSetupIntentToForm: jest.fn(),
	unblockBlockCheckout: jest.fn(),
	resetBlockCheckoutPaymentState: jest.fn(),
	getAdditionalSetupIntentData: jest.fn(),
	validateBlikCode: jest.fn(),
	getExcludedPaymentMethodTypes: jest.fn( () => [] ),
} ) );

jest.mock( 'wcstripe/styles/upe', () => ( {
	getFontRulesFromPage: jest.fn( () => [] ),
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
	sprintf: ( template ) => template,
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

// ---------------------------------------------------------------------------
// Test helpers
// ---------------------------------------------------------------------------

/**
 * Returns a fresh mock Stripe Payment Element with jest.fn() for every method
 * the code under test calls.
 */
function createMockUpeElement() {
	return {
		mount: jest.fn(),
		unmount: jest.fn(),
		on: jest.fn(),
		update: jest.fn(),
	};
}

/**
 * Builds a mock api whose getStripe().elements().create() returns the given
 * upeElement (or null to simulate a creation failure).
 *
 * @param {Object} upeElement The mock Stripe Payment Element to return from create().
 * @return {Object} The mock API object.
 */
function createMockApi( upeElement ) {
	const mockCreate = jest.fn().mockReturnValue( upeElement );
	const mockStripeElements = { create: mockCreate };
	const mockStripe = {
		elements: jest.fn().mockReturnValue( mockStripeElements ),
	};
	return { getStripe: jest.fn().mockReturnValue( mockStripe ) };
}

/**
 * Creates a DOM <div> whose dataset.paymentMethodType is set so that
 * mountStripePaymentElement routes to the 'card' component slot.
 *
 * @param {string} paymentMethodType The payment method type to set on the DOM element.
 * @return {Object} The created DOM element.
 */
function createDomElement( paymentMethodType = 'card' ) {
	const el = document.createElement( 'div' );
	el.dataset.paymentMethodType = paymentMethodType;
	return el;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe( 'initializeUPEComponents', () => {
	it( 'sets mountedDomElement to null so the first mount is never skipped', async () => {
		initializeUPEComponents();

		const mockUpeElement = createMockUpeElement();
		const api = createMockApi( mockUpeElement );
		const domElement = createDomElement();

		// If mountedDomElement were equal to domElement after init the same-container
		// guard would fire and mount would be skipped.  Confirming mount is called
		// once proves the field was initialised to null (not to domElement).
		await mountStripePaymentElement( api, domElement );

		expect( mockUpeElement.mount ).toHaveBeenCalledTimes( 1 );
	} );
} );

describe( 'mountStripePaymentElement', () => {
	let mockUpeElement;
	let api;
	let domElement;

	beforeEach( () => {
		// Reset all module-level component state before every test.
		initializeUPEComponents();

		mockUpeElement = createMockUpeElement();
		api = createMockApi( mockUpeElement );
		domElement = createDomElement();
	} );

	it( 'first mount calls upeElement.mount with the container', async () => {
		await mountStripePaymentElement( api, domElement );

		expect( mockUpeElement.mount ).toHaveBeenCalledTimes( 1 );
		expect( mockUpeElement.mount ).toHaveBeenCalledWith( domElement );
	} );

	it( 'second mount to the SAME container is a no-op (same-container guard)', async () => {
		await mountStripePaymentElement( api, domElement );
		await mountStripePaymentElement( api, domElement );

		// mount must only be called from the first invocation.
		expect( mockUpeElement.mount ).toHaveBeenCalledTimes( 1 );
		expect( mockUpeElement.unmount ).not.toHaveBeenCalled();
	} );

	it( 'mount to a DIFFERENT container unmounts first, then mounts to the new container', async () => {
		const domElement2 = createDomElement();

		await mountStripePaymentElement( api, domElement );
		await mountStripePaymentElement( api, domElement2 );

		expect( mockUpeElement.unmount ).toHaveBeenCalledTimes( 1 );
		expect( mockUpeElement.mount ).toHaveBeenCalledTimes( 2 );
		expect( mockUpeElement.mount ).toHaveBeenLastCalledWith( domElement2 );
	} );

	it( 'swallows unmount errors and still mounts to the new container', async () => {
		const domElement2 = createDomElement();

		await mountStripePaymentElement( api, domElement );

		// Simulate a page-builder that detaches the DOM node before unmount completes.
		mockUpeElement.unmount.mockImplementation( () => {
			throw new Error( 'Element already detached from DOM' );
		} );

		// If the error propagates, Jest will fail this test with a rejected promise.
		await mountStripePaymentElement( api, domElement2 );

		// Despite the unmount failure, mount must still be called on the new container.
		expect( mockUpeElement.mount ).toHaveBeenLastCalledWith( domElement2 );
	} );

	it( 'exits gracefully without calling mount when upeElement creation returns null', async () => {
		// Simulate Stripe element creation failure by returning null from elements.create().
		const apiWithNullElement = createMockApi( null );

		const result = await mountStripePaymentElement(
			apiWithNullElement,
			domElement
		);

		// Function must resolve (not throw) and return undefined (early exit).
		expect( result ).toBeUndefined();

		// The mockUpeElement from beforeEach must never be mounted (it was never
		// returned by createStripePaymentElement in this test).
		expect( mockUpeElement.mount ).not.toHaveBeenCalled();
	} );
} );
