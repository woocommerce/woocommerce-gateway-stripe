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
let mockSelectedMethod = 'card';
let mockStripeServerData = {};

jest.mock( '../../../api', () => jest.fn() );

jest.mock( '../../../stripe-utils', () => ( {
	generateCheckoutEventNames: () => 'checkout_place_order_stripe',
	getSelectedUPEGatewayPaymentMethod: () => mockSelectedMethod,
	getStripeServerData: () => mockStripeServerData,
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
		mockSelectedMethod = 'card';
		mockStripeServerData = {};
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

/**
 * The pay-for-order and add-payment-method forms are shared with every gateway, unlike
 * classic checkout where core only fires Stripe's own `checkout_place_order_*` events.
 * Returning false from these submit handlers cancels the submit for whichever gateway
 * is selected, so for a non-Stripe gateway they must return undefined and do nothing.
 */
describe.each( [
	[ 'pay-for-order', '<form id="order_review"></form>', '#order_review', {} ],
	[
		// Subscriptions renders its change-payment-method page on the same form.
		'change-payment-method',
		'<form id="order_review"><input type="hidden" name="change_payment_method" value="1"></form>',
		'#order_review',
		{ isChangingPayment: true },
	],
	[
		'add-payment-method',
		'<form id="add_payment_method"></form>',
		'form#add_payment_method',
		{},
	],
] )( '%s submit handler', ( _, formMarkup, formSelector, serverData ) => {
	const submit = () => $( formSelector ).triggerHandler( 'submit' );
	// Only the #order_review form offers saved-method radios.
	const hasSavedMethods = formSelector === '#order_review';

	beforeEach( async () => {
		mockUsingSavedMethod = false;
		mockSelectedMethod = 'card';
		mockStripeServerData = serverData;
		mockProcessPayment.mockReset();
		mockProcessPayment.mockReturnValue( false );
		mockShowErrorCheckout.mockReset();

		document.body.innerHTML = formMarkup;

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

	it( 'lets core submit untouched when a non-Stripe gateway is selected', () => {
		mockSelectedMethod = null;

		expect( submit() ).toBeUndefined();
		expect( mockProcessPayment ).not.toHaveBeenCalled();
		expect( mockShowErrorCheckout ).not.toHaveBeenCalled();
	} );

	it( 'delegates to processPayment when a Stripe method is selected', () => {
		expect( submit() ).toBe( false );
		expect( mockProcessPayment ).toHaveBeenCalledTimes( 1 );
		expect( mockProcessPayment.mock.calls[ 0 ][ 2 ] ).toBe( 'card' );
	} );

	if ( hasSavedMethods ) {
		// A saved card has no form to read on the page: WooCommerce submits the token id and PHP charges it.
		it( 'lets core submit a saved Stripe method untouched', () => {
			mockUsingSavedMethod = true;

			expect( submit() ).toBeUndefined();
			expect( mockProcessPayment ).not.toHaveBeenCalled();
		} );
	}
} );
