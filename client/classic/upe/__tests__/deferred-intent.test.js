jest.mock( 'wcstripe/api', () => jest.fn() );

jest.mock( 'wcstripe/classic/upe/payment-processing', () => ( {
	confirmVoucherPayment: jest.fn(),
	confirmWalletPayment: jest.fn(),
	createAndConfirmSetupIntent: jest.fn(),
	getMountedUPEComponent: jest.fn(),
	hasEmptyRequiredFields: jest.fn().mockReturnValue( false ),
	initializeUPEComponents: jest.fn(),
	maybeUpdateAdaptivePricingCheckoutSession: jest.fn(),
	maybeUpdateOptimizedCheckoutExclusions: jest.fn(),
	mountStripePaymentElement: jest.fn(),
	processPayment: jest.fn(),
	trackMountInProgress: jest.fn(),
} ) );

jest.mock( 'wcstripe/stripe-utils', () => ( {
	generateCheckoutEventNames: jest
		.fn()
		.mockReturnValue( 'checkout_place_order_stripe' ),
	getSelectedUPEGatewayPaymentMethod: jest.fn().mockReturnValue( 'card' ),
	getStripeServerData: jest.fn().mockReturnValue( {} ),
	isPaymentMethodRestrictedToLocation: jest.fn().mockReturnValue( false ),
	isUsingSavedPaymentMethod: jest.fn().mockReturnValue( false ),
	paymentMethodSupportsDeferredIntent: jest.fn().mockReturnValue( true ),
	removeCheckoutSessionIdFromForm: jest.requireActual(
		'wcstripe/stripe-utils/utils'
	).removeCheckoutSessionIdFromForm,
	togglePaymentMethodForCountry: jest.fn(),
} ) );

const STALE_SESSION_ID = 'cs_test_stale';

/**
 * Renders a checkout form still carrying the Checkout Session id of an earlier attempt, loads the
 * classic checkout script against it and places the order.
 *
 * The module registry is reset first so every case gets a script instance bound to the jQuery
 * instance and the DOM used here, rather than to a previous case's.
 *
 * @param {boolean} usingSavedToken Whether the customer picked a saved payment method.
 * @return {Promise<Object>} The mocked payment-processing module the script was loaded against.
 */
const placeOrderWithStaleSessionId = async ( usingSavedToken ) => {
	document.body.innerHTML = `
		<form class="checkout">
			<input type="hidden" id="wc_stripe_checkout_session_id" name="wc_stripe_checkout_session_id" value="${ STALE_SESSION_ID }" />
		</form>`;

	jest.resetModules();

	require( 'wcstripe/stripe-utils' ).isUsingSavedPaymentMethod.mockReturnValue(
		usingSavedToken
	);
	const paymentProcessing = require( 'wcstripe/classic/upe/payment-processing' );

	const jQuery = require( 'jquery' );
	require( '../deferred-intent' );
	// The script binds its handlers from a jQuery ready callback. Ours is queued behind it.
	await new Promise( ( resolve ) => jQuery( resolve ) );

	jQuery( 'form.checkout' ).trigger( 'checkout_place_order_stripe' );

	return paymentProcessing;
};

describe( 'classic checkout submission', () => {
	it( 'drops a stale Checkout Session id when retrying with a saved token', async () => {
		const { processPayment } = await placeOrderWithStaleSessionId( true );

		expect(
			document.getElementById( 'wc_stripe_checkout_session_id' )
		).toBeNull();
		// The saved token is charged through the deferred intent, not the Checkout Session.
		expect( processPayment ).not.toHaveBeenCalled();
	} );

	it( 'keeps the Checkout Session id when retrying with a new payment method', async () => {
		const { processPayment } = await placeOrderWithStaleSessionId( false );

		expect(
			document.getElementById( 'wc_stripe_checkout_session_id' )
		).not.toBeNull();
		expect( processPayment ).toHaveBeenCalled();
	} );
} );
