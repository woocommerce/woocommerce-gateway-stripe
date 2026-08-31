/**
 * The classic checkout handler's RETURN VALUE is the contract with WooCommerce core:
 * core submits the form unless a `checkout_place_order_*` handler returns exactly false.
 * Returning early without false lets the form POST with no payment method, which creates
 * an order and then fails it. These tests pin the return value, not just the side effects.
 */
const mockProcessPayment = jest.fn();
const mockShowErrorCheckout = jest.fn();
let mockIsEmpty = false;
let mockUsingSavedMethod = false;

jest.mock( '../../../api', () => jest.fn() );

jest.mock( '../../../stripe-utils', () => ( {
	generateCheckoutEventNames: () => 'checkout_place_order_stripe',
	getSelectedUPEGatewayPaymentMethod: () => 'card',
	getStripeServerData: () => ( {} ),
	isPaymentMethodRestrictedToLocation: () => false,
	isUsingSavedPaymentMethod: () => mockUsingSavedMethod,
	paymentMethodSupportsDeferredIntent: () => true,
	showErrorCheckout: ( ...args ) => mockShowErrorCheckout( ...args ),
	togglePaymentMethodForCountry: () => {},
} ) );

jest.mock( '../payment-processing', () => ( {
	confirmVoucherPayment: () => {},
	confirmWalletPayment: () => {},
	createAndConfirmSetupIntent: () => {},
	getMountedUPEComponent: () => null,
	hasEmptyRequiredFields: () => mockIsEmpty,
	initializeUPEComponents: () => {},
	maybeUpdateAdaptivePricingCheckoutSession: () => Promise.resolve(),
	maybeUpdateOptimizedCheckoutExclusions: () => {},
	mountStripePaymentElement: () => Promise.resolve(),
	processPayment: ( ...args ) => mockProcessPayment( ...args ),
	trackMountInProgress: () => {},
} ) );

// jQuery defers its ready callback through setTimeout and then resolves it through a
// promise chain, so binding needs several turns of the loop, not a single flush.
const flushReady = async () => {
	for ( let i = 0; i < 5; i++ ) {
		await new Promise( ( resolve ) => setTimeout( resolve, 1 ) );
	}
};

// The module under test binds its handler with the jQuery from its own module
// registry, so the test has to trigger through that same instance.
let $;

const placeOrder = () =>
	$( 'form.checkout' ).triggerHandler( 'checkout_place_order_stripe' );

describe( 'classic checkout place-order handler', () => {
	beforeEach( async () => {
		mockIsEmpty = false;
		mockUsingSavedMethod = false;
		mockProcessPayment.mockReset();
		mockProcessPayment.mockReturnValue( false );
		mockShowErrorCheckout.mockReset();

		document.body.innerHTML = '<form class="checkout"></form>';

		await jest.isolateModulesAsync( async () => {
			$ = require( 'jquery' );
			require( '../deferred-intent' );
			await flushReady();
		} );
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		jest.resetModules();
	} );

	it( 'returns false so core does not submit when a required field is empty', () => {
		mockIsEmpty = true;

		expect( placeOrder() ).toBe( false );
		expect( mockProcessPayment ).not.toHaveBeenCalled();
		expect( mockShowErrorCheckout ).toHaveBeenCalledWith(
			'Please fill in all required fields.'
		);
	} );

	it( 'delegates to processPayment when the required fields are filled', () => {
		placeOrder();

		expect( mockProcessPayment ).toHaveBeenCalled();
		expect( mockShowErrorCheckout ).not.toHaveBeenCalled();
	} );

	// The saved token travels in the POST, so core must be allowed to submit even when
	// the required-field check would have objected.
	it( 'lets core submit a saved token without consulting the required fields', () => {
		mockIsEmpty = true;
		mockUsingSavedMethod = true;

		expect( placeOrder() ).toBeUndefined();
		expect( mockProcessPayment ).not.toHaveBeenCalled();
		expect( mockShowErrorCheckout ).not.toHaveBeenCalled();
	} );
} );
