/**
 * The classic checkout handler's RETURN VALUE is the contract with WooCommerce core:
 * core submits the form unless a `checkout_place_order_*` handler returns exactly false.
 * Returning early without false lets the form POST with no payment method, which creates
 * an order and then fails it. These tests pin the return value, not just the side effects.
 */
const mockProcessPayment = jest.fn();
const mockResetCheckoutCompletionState = jest.fn();
const mockShowErrorCheckout = jest.fn();
let mockIsEmpty = false;
let mockUsingSavedMethod = false;

jest.mock( '../../../stripe-utils', () => ( {
	generateCheckoutEventNames: () => 'checkout_place_order_stripe',
	getSelectedUPEGatewayPaymentMethod: () => 'card',
	getStripeServerData: () => ( {} ),
	isPaymentMethodRestrictedToLocation: () => false,
	isUsingSavedPaymentMethod: () => mockUsingSavedMethod,
	paymentMethodSupportsDeferredIntent: () => true,
	removeCheckoutSessionIdFromForm: jest.requireActual(
		'../../../stripe-utils/utils'
	).removeCheckoutSessionIdFromForm,
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
	resetCheckoutCompletionState: ( ...args ) =>
		mockResetCheckoutCompletionState( ...args ),
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
		mockResetCheckoutCompletionState.mockReset();
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
		expect( mockResetCheckoutCompletionState ).toHaveBeenCalledTimes( 1 );
		expect( mockShowErrorCheckout ).toHaveBeenCalledWith(
			'Please fill in all required fields.'
		);
	} );

	it( 'delegates to processPayment when the required fields are filled', () => {
		placeOrder();

		expect( mockProcessPayment ).toHaveBeenCalled();
		expect( mockResetCheckoutCompletionState ).not.toHaveBeenCalled();
		expect( mockShowErrorCheckout ).not.toHaveBeenCalled();
	} );

	it( 'blocks a saved-token checkout when a required field is empty', () => {
		mockIsEmpty = true;
		mockUsingSavedMethod = true;

		expect( placeOrder() ).toBe( false );
		expect( mockProcessPayment ).not.toHaveBeenCalled();
		expect( mockResetCheckoutCompletionState ).toHaveBeenCalledTimes( 1 );
		expect( mockShowErrorCheckout ).toHaveBeenCalledWith(
			'Please fill in all required fields.'
		);
	} );

	it( 'lets core submit a saved token when required fields are filled', () => {
		mockUsingSavedMethod = true;

		expect( placeOrder() ).toBeUndefined();
		expect( mockProcessPayment ).not.toHaveBeenCalled();
		expect( mockResetCheckoutCompletionState ).not.toHaveBeenCalled();
		expect( mockShowErrorCheckout ).not.toHaveBeenCalled();
	} );

	// A saved token bypasses Checkout Session confirmation, so a stale Session id
	// left in the form by an earlier Adaptive Pricing attempt must be dropped
	// before core submits; new payment methods keep it for the Session flow.
	const appendStaleSessionId = () =>
		$( 'form.checkout' ).append(
			'<input type="hidden" id="wc_stripe_checkout_session_id" name="wc_stripe_checkout_session_id" value="cs_test_stale" />'
		);

	it( 'drops a stale Checkout Session id when retrying with a saved token', () => {
		mockUsingSavedMethod = true;
		appendStaleSessionId();

		placeOrder();

		expect(
			document.getElementById( 'wc_stripe_checkout_session_id' )
		).toBeNull();
		// The saved token is charged through the deferred intent, not the Checkout Session.
		expect( mockProcessPayment ).not.toHaveBeenCalled();
	} );

	it( 'keeps the Checkout Session id when retrying with a new payment method', () => {
		appendStaleSessionId();

		placeOrder();

		expect(
			document.getElementById( 'wc_stripe_checkout_session_id' )
		).not.toBeNull();
		expect( mockProcessPayment ).toHaveBeenCalled();
	} );
} );
