/**
 * Internal dependencies
 */
import {
	handleChangePaymentMethodFlow,
	handleManualPaymentMethodFlow,
	handleConfirmationTokenFlow,
} from '../payment-flow';

jest.mock( '@woocommerce/blocks-checkout', () => {}, { virtual: true } );

// Transitively required: a jQuery submit handler bound by modules pulled in via
// payment-flow.js's import graph calls getStripeServerData() when form.trigger('submit')
// fires in the success-path tests. Without this mock those tests throw and the
// completePayment assertions fail. Not consumed directly by the test cases.
jest.mock( 'wcstripe/stripe-utils', () => ( {
	getStripeServerData: jest.fn( () => ( {
		isCheckout: true,
	} ) ),
} ) );

describe( 'handleChangePaymentMethodFlow', () => {
	let stripe;
	let elements;
	let abortPayment;
	let event;

	beforeEach( () => {
		stripe = {
			createPaymentMethod: jest.fn(),
		};
		elements = {};
		abortPayment = jest.fn();
		event = {
			expressPaymentType: 'apple_pay',
		};

		// Set up a minimal DOM with a form that the function expects.
		document.body.innerHTML = `
			<form id="order_review">
				<input type="radio" name="payment_method" value="stripe" />
			</form>
		`;

		// Set up jQuery mock that works with the real DOM.
		const jQuery = require( 'jquery' );
		global.jQuery = jQuery;
	} );

	afterEach( () => {
		jest.clearAllMocks();
		document.body.innerHTML = '';
	} );

	test( 'should create payment method and submit the form', async () => {
		stripe.createPaymentMethod.mockResolvedValue( {
			paymentMethod: { id: 'pm_test_123' },
		} );

		const form = global.jQuery( 'form#order_review' );
		const submitSpy = jest.fn();
		form.on( 'submit', submitSpy );

		await handleChangePaymentMethodFlow( {
			stripe,
			elements,
			abortPayment,
			event,
		} );

		expect( stripe.createPaymentMethod ).toHaveBeenCalledWith( {
			elements,
		} );

		// Verify the payment method hidden field was created.
		const pmInput = form.find( 'input[name="wc-stripe-payment-method"]' );
		expect( pmInput.length ).toBe( 1 );
		expect( pmInput.val() ).toBe( 'pm_test_123' );

		// Verify the express checkout type hidden field was created.
		const typeInput = form.find( 'input[name="express_checkout_type"]' );
		expect( typeInput.length ).toBe( 1 );
		expect( typeInput.val() ).toBe( 'apple_pay' );

		// Verify the payment method radio is checked.
		const gatewayInput = form.find(
			'input[name="payment_method"][value="stripe"]'
		);
		expect( gatewayInput.prop( 'checked' ) ).toBe( true );

		// Verify form was submitted.
		expect( submitSpy ).toHaveBeenCalled();

		// Verify abortPayment was NOT called.
		expect( abortPayment ).not.toHaveBeenCalled();
	} );

	test( 'should update existing hidden fields instead of creating new ones', async () => {
		// Pre-populate the form with existing hidden fields.
		const form = global.jQuery( 'form#order_review' );
		form.append(
			global
				.jQuery( '<input>' )
				.attr( 'type', 'hidden' )
				.attr( 'name', 'wc-stripe-payment-method' )
				.val( 'pm_old' )
		);
		form.append(
			global
				.jQuery( '<input>' )
				.attr( 'type', 'hidden' )
				.attr( 'name', 'express_checkout_type' )
				.val( 'google_pay' )
		);

		stripe.createPaymentMethod.mockResolvedValue( {
			paymentMethod: { id: 'pm_new_456' },
		} );

		await handleChangePaymentMethodFlow( {
			stripe,
			elements,
			abortPayment,
			event,
		} );

		// Should update, not duplicate.
		expect(
			form.find( 'input[name="wc-stripe-payment-method"]' ).length
		).toBe( 1 );
		expect(
			form.find( 'input[name="wc-stripe-payment-method"]' ).val()
		).toBe( 'pm_new_456' );

		expect(
			form.find( 'input[name="express_checkout_type"]' ).length
		).toBe( 1 );
		expect( form.find( 'input[name="express_checkout_type"]' ).val() ).toBe(
			'apple_pay'
		);
	} );

	test( 'should abort when createPaymentMethod returns an error', async () => {
		stripe.createPaymentMethod.mockResolvedValue( {
			error: { message: 'Card declined' },
		} );

		await handleChangePaymentMethodFlow( {
			stripe,
			elements,
			abortPayment,
			event,
		} );

		expect( abortPayment ).toHaveBeenCalledWith( event, 'Card declined' );

		// Form should not have been modified.
		const form = global.jQuery( 'form#order_review' );
		expect(
			form.find( 'input[name="wc-stripe-payment-method"]' ).length
		).toBe( 0 );
	} );

	test( 'should route createPaymentMethod rejection through abortPayment', async () => {
		// Transport-level failure (network down, malformed request, etc.) —
		// the Stripe SDK rejects rather than resolving with `{ error }`.
		// Guards that the try/catch around createPaymentMethod routes the
		// rejection through handlePaymentFlowException rather than letting
		// it escape as an unhandled rejection.
		stripe.createPaymentMethod.mockRejectedValue(
			new Error( 'Network failure' )
		);

		await handleChangePaymentMethodFlow( {
			stripe,
			elements,
			abortPayment,
			event,
		} );

		expect( abortPayment ).toHaveBeenCalled();
		expect( abortPayment.mock.calls[ 0 ][ 1 ] ).toBe( 'Network failure' );
	} );

	test( 'should abort when no form is found', async () => {
		// Remove all forms from the DOM.
		document.body.innerHTML = '<div>no form here</div>';

		stripe.createPaymentMethod.mockResolvedValue( {
			paymentMethod: { id: 'pm_test_789' },
		} );

		await handleChangePaymentMethodFlow( {
			stripe,
			elements,
			abortPayment,
			event,
		} );

		expect( abortPayment ).toHaveBeenCalledWith(
			event,
			'Could not find the checkout form.'
		);
		expect( stripe.createPaymentMethod ).not.toHaveBeenCalled();
	} );

	test( 'should handle missing expressPaymentType gracefully', async () => {
		stripe.createPaymentMethod.mockResolvedValue( {
			paymentMethod: { id: 'pm_test_abc' },
		} );

		// Event without expressPaymentType.
		event = {};

		await handleChangePaymentMethodFlow( {
			stripe,
			elements,
			abortPayment,
			event,
		} );

		const form = global.jQuery( 'form#order_review' );
		const typeInput = form.find( 'input[name="express_checkout_type"]' );
		expect( typeInput.val() ).toBe( '' );
	} );

	test( 'should also match form.checkout selector', async () => {
		// Replace the DOM with a form using the checkout class.
		document.body.innerHTML = `
			<form class="checkout">
				<input type="radio" name="payment_method" value="stripe" />
			</form>
		`;

		stripe.createPaymentMethod.mockResolvedValue( {
			paymentMethod: { id: 'pm_test_def' },
		} );

		const form = global.jQuery( 'form.checkout' );
		const submitSpy = jest.fn();
		form.on( 'submit', submitSpy );

		await handleChangePaymentMethodFlow( {
			stripe,
			elements,
			abortPayment,
			event,
		} );

		expect( submitSpy ).toHaveBeenCalled();
		expect( abortPayment ).not.toHaveBeenCalled();
	} );
} );

describe( 'handleManualPaymentMethodFlow', () => {
	let api;
	let stripe;
	let elements;
	let completePayment;
	let abortPayment;
	let event;

	beforeEach( () => {
		api = {
			expressCheckoutECECreateOrder: jest.fn(),
			expressCheckoutECEPayForOrder: jest.fn(),
			expressCheckoutNormalizeAddress: jest
				.fn()
				.mockResolvedValue( null ),
			confirmIntent: jest.fn(),
		};
		stripe = {
			createPaymentMethod: jest.fn(),
		};
		elements = {};
		completePayment = jest.fn();
		abortPayment = jest.fn();
		event = {
			billingDetails: {
				name: 'John Doe',
				email: 'john@example.com',
				phone: '555-1234',
				address: {
					city: 'New York',
					country: 'US',
					line1: '123 Main St',
					postal_code: '10001',
					state: 'NY',
				},
			},
			shippingAddress: {
				name: 'John Doe',
				address: {
					city: 'New York',
					country: 'US',
					line1: '123 Main St',
					postal_code: '10001',
					state: 'NY',
				},
			},
			shippingRate: { id: 'flat_rate:1' },
			expressPaymentType: 'apple_pay',
		};
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'should route createPaymentMethod rejection through abortPayment', async () => {
		// Transport-level failure path: SDK rejects rather than returning
		// `{ error }`. Guards that handlePaymentFlowException catches it and
		// completePayment is never reached.
		stripe.createPaymentMethod.mockRejectedValue(
			new Error( 'Network failure' )
		);

		await handleManualPaymentMethodFlow( {
			api,
			stripe,
			elements,
			completePayment,
			abortPayment,
			event,
		} );

		expect( abortPayment ).toHaveBeenCalled();
		expect( completePayment ).not.toHaveBeenCalled();
	} );

	test( 'should abort when createPaymentMethod returns an error', async () => {
		stripe.createPaymentMethod.mockResolvedValue( {
			error: { message: 'Your card was declined.' },
		} );

		await handleManualPaymentMethodFlow( {
			api,
			stripe,
			elements,
			completePayment,
			abortPayment,
			event,
		} );

		expect( abortPayment ).toHaveBeenCalledWith(
			event,
			'Your card was declined.'
		);
		expect( completePayment ).not.toHaveBeenCalled();
	} );

	// Stripe messages are plain text; parsing them as HTML truncated anything
	// tag-like, e.g. an email address in angle brackets.
	test( 'should not truncate a Stripe error containing angle brackets', async () => {
		stripe.createPaymentMethod.mockResolvedValue( {
			error: { message: 'Invalid email <user@example.com>' },
		} );

		await handleManualPaymentMethodFlow( {
			api,
			stripe,
			elements,
			completePayment,
			abortPayment,
			event,
		} );

		expect( abortPayment ).toHaveBeenCalledWith(
			event,
			'Invalid email <user@example.com>'
		);
	} );

	test( 'should complete payment on successful order', async () => {
		stripe.createPaymentMethod.mockResolvedValue( {
			paymentMethod: { id: 'pm_test_123' },
		} );
		api.expressCheckoutECECreateOrder.mockResolvedValue( {
			payment_result: {
				payment_status: 'success',
				redirect_url: 'https://example.com/order-received',
			},
		} );
		api.confirmIntent.mockReturnValue( true );

		await handleManualPaymentMethodFlow( {
			api,
			stripe,
			elements,
			completePayment,
			abortPayment,
			event,
		} );

		expect( completePayment ).toHaveBeenCalledWith(
			'https://example.com/order-received'
		);
		expect( abortPayment ).not.toHaveBeenCalled();
	} );

	// WooCommerce answers 200 with an empty payment status when it skips payment
	// entirely (e.g. it could not resolve the gateway), leaving the order unpaid
	// with nothing attached to explain why. The shopper still needs a message.
	test( 'should abort with a generic message when the order reports no payment status', async () => {
		stripe.createPaymentMethod.mockResolvedValue( {
			paymentMethod: { id: 'pm_test_123' },
		} );
		api.expressCheckoutECECreateOrder.mockResolvedValue( {
			payment_result: { payment_status: '' },
		} );

		await handleManualPaymentMethodFlow( {
			api,
			stripe,
			elements,
			completePayment,
			abortPayment,
			event,
		} );

		expect( abortPayment ).toHaveBeenCalledWith(
			event,
			'There was a problem processing the order.'
		);
		expect( completePayment ).not.toHaveBeenCalled();
	} );

	test( 'should abort when order creation fails', async () => {
		stripe.createPaymentMethod.mockResolvedValue( {
			paymentMethod: { id: 'pm_test_123' },
		} );
		api.expressCheckoutECECreateOrder.mockResolvedValue( {
			payment_result: {
				payment_status: 'failure',
				payment_details: [
					{ key: 'errorMessage', value: 'Insufficient funds' },
				],
			},
		} );

		await handleManualPaymentMethodFlow( {
			api,
			stripe,
			elements,
			completePayment,
			abortPayment,
			event,
		} );

		expect( abortPayment ).toHaveBeenCalled();
		expect( completePayment ).not.toHaveBeenCalled();
	} );
} );

describe( 'handleConfirmationTokenFlow', () => {
	let api;
	let stripe;
	let elements;
	let completePayment;
	let abortPayment;
	let event;

	beforeEach( () => {
		api = {
			expressCheckoutECECreateOrder: jest.fn(),
			expressCheckoutECEPayForOrder: jest.fn(),
			expressCheckoutNormalizeAddress: jest
				.fn()
				.mockResolvedValue( null ),
			confirmIntent: jest.fn(),
		};
		stripe = {
			createConfirmationToken: jest.fn(),
		};
		elements = {};
		completePayment = jest.fn();
		abortPayment = jest.fn();
		event = {
			billingDetails: {
				name: 'Jane Doe',
				email: 'jane@example.com',
				phone: '555-5678',
				address: {
					city: 'San Francisco',
					country: 'US',
					line1: '456 Oak Ave',
					postal_code: '94110',
					state: 'CA',
				},
			},
			shippingAddress: {
				name: 'Jane Doe',
				address: {
					city: 'San Francisco',
					country: 'US',
					line1: '456 Oak Ave',
					postal_code: '94110',
					state: 'CA',
				},
			},
			shippingRate: { id: 'flat_rate:1' },
			expressPaymentType: 'google_pay',
		};
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'should route createConfirmationToken rejection through abortPayment', async () => {
		// Transport-level failure path: SDK rejects rather than returning
		// `{ error }`. Guards that handlePaymentFlowException catches it and
		// completePayment is never reached.
		stripe.createConfirmationToken.mockRejectedValue(
			new Error( 'Network failure' )
		);

		await handleConfirmationTokenFlow( {
			api,
			stripe,
			elements,
			completePayment,
			abortPayment,
			event,
		} );

		expect( abortPayment ).toHaveBeenCalled();
		expect( completePayment ).not.toHaveBeenCalled();
	} );

	test( 'should abort when createConfirmationToken returns an error', async () => {
		stripe.createConfirmationToken.mockResolvedValue( {
			error: { message: 'Token creation failed' },
		} );

		await handleConfirmationTokenFlow( {
			api,
			stripe,
			elements,
			completePayment,
			abortPayment,
			event,
		} );

		expect( abortPayment ).toHaveBeenCalled();
		expect( completePayment ).not.toHaveBeenCalled();
	} );

	test( 'should complete payment on successful order', async () => {
		stripe.createConfirmationToken.mockResolvedValue( {
			confirmationToken: { id: 'ct_test_456' },
		} );
		api.expressCheckoutECECreateOrder.mockResolvedValue( {
			payment_result: {
				payment_status: 'success',
				redirect_url: 'https://example.com/order-received',
			},
		} );
		api.confirmIntent.mockReturnValue( true );

		await handleConfirmationTokenFlow( {
			api,
			stripe,
			elements,
			completePayment,
			abortPayment,
			event,
		} );

		expect( completePayment ).toHaveBeenCalledWith(
			'https://example.com/order-received'
		);
		expect( abortPayment ).not.toHaveBeenCalled();
	} );
} );

describe( 'address normalization', () => {
	let api;
	let stripe;
	let elements;
	let completePayment;
	let abortPayment;
	let event;

	// What normalizeOrderData() builds from the wallet event below.
	const walletBillingAddress = {
		first_name: 'Jane',
		last_name: 'Doe',
		company: '',
		email: 'jane@example.com',
		phone: '',
		country: 'US',
		address_1: '456 Oak Ave',
		address_2: '',
		city: 'San Juan',
		state: 'PR',
		postcode: '00901',
	};
	const walletShippingAddress = {
		first_name: 'Jane',
		last_name: 'Doe',
		company: '',
		phone: '',
		country: 'US',
		address_1: '456 Oak Ave',
		address_2: '',
		city: 'San Juan',
		state: 'PR',
		postcode: '00901',
		method: [ 'flat_rate:1' ],
	};

	const getCreateOrderPayload = () =>
		api.expressCheckoutECECreateOrder.mock.calls[ 0 ][ 0 ];

	beforeEach( () => {
		const paymentResult = {
			payment_result: {
				payment_status: 'success',
				redirect_url: 'https://example.com/order-received',
			},
		};
		api = {
			expressCheckoutECECreateOrder: jest
				.fn()
				.mockResolvedValue( paymentResult ),
			expressCheckoutECEPayForOrder: jest
				.fn()
				.mockResolvedValue( paymentResult ),
			expressCheckoutNormalizeAddress: jest.fn(),
			confirmIntent: jest.fn().mockReturnValue( true ),
		};
		stripe = {
			createPaymentMethod: jest
				.fn()
				.mockResolvedValue( { paymentMethod: { id: 'pm_test_123' } } ),
			createConfirmationToken: jest.fn().mockResolvedValue( {
				confirmationToken: { id: 'ct_test_456' },
			} ),
		};
		elements = {};
		completePayment = jest.fn();
		abortPayment = jest.fn();
		event = {
			billingDetails: {
				name: 'Jane Doe',
				email: 'jane@example.com',
				address: {
					city: 'San Juan',
					country: 'US',
					line1: '456 Oak Ave',
					postal_code: '00901',
					state: 'PR',
				},
			},
			shippingAddress: {
				name: 'Jane Doe',
				address: {
					city: 'San Juan',
					country: 'US',
					line1: '456 Oak Ave',
					postal_code: '00901',
					state: 'PR',
				},
			},
			shippingRate: { id: 'flat_rate:1' },
		};
	} );

	// Apple Pay and Google Pay run through the manual flow; Amazon Pay runs through the
	// confirmation token flow unless the cart has a free trial. Both share processOrder.
	describe.each( [
		[
			'handleManualPaymentMethodFlow - Apple Pay',
			handleManualPaymentMethodFlow,
			'apple_pay',
		],
		[
			'handleManualPaymentMethodFlow - Google Pay',
			handleManualPaymentMethodFlow,
			'google_pay',
		],
		[
			'handleConfirmationTokenFlow - Amazon Pay',
			handleConfirmationTokenFlow,
			'amazon_pay',
		],
	] )( '%s', ( _name, runFlow, expressPaymentType ) => {
		const flow = ( params = {} ) =>
			runFlow( {
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event: { ...event, expressPaymentType },
				...params,
			} );

		test.each( [
			[ 'null', null ],
			[
				'an HTML page served by a cache or a redirect',
				'<!DOCTYPE html>',
			],
			[ 'an empty array', [] ],
			[
				'a body without the address keys (undefined addresses)',
				{ success: true },
			],
			[
				'an empty string and a null address',
				{ billing_address: '', shipping_address: null },
			],
			[
				'addresses encoded as non-empty strings',
				{
					billing_address: 'invalid',
					shipping_address: 'invalid',
				},
			],
			[
				'addresses encoded as scalars',
				{ billing_address: 1, shipping_address: true },
			],
			[
				'addresses encoded as empty PHP arrays',
				{ billing_address: [], shipping_address: [] },
			],
			[
				'addresses encoded as non-empty arrays',
				{
					billing_address: [ 'invalid' ],
					shipping_address: [ 'invalid' ],
				},
			],
			[
				'empty addresses',
				{ billing_address: {}, shipping_address: {} },
			],
		] )(
			'keeps the wallet addresses when normalization responds with %s',
			async ( _label, response ) => {
				api.expressCheckoutNormalizeAddress.mockResolvedValue(
					response
				);

				await flow();

				const payload = getCreateOrderPayload();
				expect( payload.billing_address ).toEqual(
					walletBillingAddress
				);
				expect( payload.shipping_address ).toEqual(
					walletShippingAddress
				);
				expect( completePayment ).toHaveBeenCalled();
				expect( abortPayment ).not.toHaveBeenCalled();
			}
		);

		test( 'applies the normalized addresses when the response provides them', async () => {
			// Puerto Rico: the wallet reports it as a US state, and normalization
			// promotes it to a country.
			api.expressCheckoutNormalizeAddress.mockResolvedValue( {
				billing_address: { country: 'PR', state: '' },
				shipping_address: { country: 'PR', state: '' },
			} );

			await flow();

			const payload = getCreateOrderPayload();
			expect( payload.billing_address ).toEqual( {
				...walletBillingAddress,
				country: 'PR',
				state: '',
			} );
			expect( payload.shipping_address ).toEqual( {
				...walletShippingAddress,
				country: 'PR',
				state: '',
			} );
		} );

		test( 'keeps the wallet fields the response omits', async () => {
			api.expressCheckoutNormalizeAddress.mockResolvedValue( {
				billing_address: { country: 'PR' },
			} );

			await flow();

			const payload = getCreateOrderPayload();
			expect( payload.billing_address ).toEqual( {
				...walletBillingAddress,
				country: 'PR',
			} );
			expect( payload.shipping_address ).toEqual( walletShippingAddress );
		} );

		test( 'keeps the wallet billing address when paying for an existing order', async () => {
			api.expressCheckoutNormalizeAddress.mockResolvedValue(
				'<!DOCTYPE html>'
			);

			await flow( {
				order: 123,
				orderDetails: {
					orderKey: 'wc_order_abc',
					billingEmail: 'jane@example.com',
					shippingAddress: walletShippingAddress,
				},
			} );

			const payload =
				api.expressCheckoutECEPayForOrder.mock.calls[ 0 ][ 2 ];
			expect( payload.billing_address ).toEqual( walletBillingAddress );
		} );
	} );
} );
