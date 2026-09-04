/**
 * Internal dependencies
 */
import { normalizeOrderData } from '../utils';
import {
	onConfirmHandler,
	shippingAddressChangeHandler,
	shippingRateChangeHandler,
	setCartApiHandler,
} from 'wcstripe/express-checkout/event-handler';

jest.mock( '@woocommerce/blocks-checkout', () => {}, { virtual: true } );

jest.mock( 'wcstripe/stripe-utils', () => ( {
	getStripeServerData: jest.fn( () => ( {
		isCheckout: true,
	} ) ),
} ) );

jest.mock( 'wcstripe/express-checkout/cart-api', () => {
	return jest.fn().mockImplementation( () => ( {
		updateCustomer: jest.fn(),
		selectShippingRate: jest.fn(),
		getCart: jest.fn(),
	} ) );
} );

jest.mock( 'wcstripe/express-checkout/transformers/stripe-to-wc', () => ( {
	transformStripeShippingAddressForStoreApi: jest.fn( ( name, address ) => ( {
		first_name: name?.split( ' ' )[ 0 ] ?? '',
		last_name: name?.split( ' ' ).slice( 1 ).join( ' ' ) ?? '',
		city: address.city ?? '',
		state: address.state ?? '',
		postcode: address.postal_code ?? '',
		country: address.country ?? '',
	} ) ),
} ) );

jest.mock( 'wcstripe/express-checkout/transformers/wc-to-stripe', () => ( {
	// Delegate to the real helper so the mock cannot drift from its
	// minor-unit conversion behavior.
	transformCartTotalAmount: jest.fn( ( totals ) =>
		jest
			.requireActual(
				'wcstripe/express-checkout/transformers/wc-to-stripe'
			)
			.transformCartTotalAmount( totals )
	),
	transformCartDataForDisplayItems: jest.fn( () => [
		{ name: 'Item', amount: 500 },
	] ),
	transformCartDataForShippingRates: jest.fn( () => [
		{ id: 'flat_rate:1', displayName: 'Flat rate', amount: 500 },
	] ),
} ) );

describe( 'Express checkout event handlers', () => {
	describe( 'shippingAddressChangeHandler', () => {
		let mockCartApi;
		let event;
		let elements;

		const cartResponse = {
			totals: {
				total_price: '1000',
				total_refund: '0',
				currency_minor_unit: 2,
			},
			shipping_rates: [
				{
					package_id: 0,
					shipping_rates: [
						{
							rate_id: 'flat_rate:1',
							name: 'Flat rate',
							price: '500',
							selected: true,
						},
					],
				},
			],
			items: [],
		};

		beforeEach( () => {
			mockCartApi = {
				updateCustomer: jest.fn().mockResolvedValue( cartResponse ),
			};
			setCartApiHandler( mockCartApi );

			event = {
				name: 'John Doe',
				address: {
					city: 'New York',
					state: 'NY',
					country: 'US',
					postal_code: '10001',
				},
				resolve: jest.fn(),
				reject: jest.fn(),
			};
			elements = {
				update: jest.fn(),
			};
		} );

		afterEach( () => {
			jest.clearAllMocks();
		} );

		test( 'should call cartApi.updateCustomer with transformed address', async () => {
			await shippingAddressChangeHandler( event, elements );

			expect( mockCartApi.updateCustomer ).toHaveBeenCalledWith( {
				shipping_address: expect.objectContaining( {
					country: 'US',
					state: 'NY',
				} ),
			} );
		} );

		test( 'should update elements amount and resolve with shipping rates and line items', async () => {
			await shippingAddressChangeHandler( event, elements );

			expect( elements.update ).toHaveBeenCalledWith( { amount: 1000 } );
			expect( event.resolve ).toHaveBeenCalledWith( {
				shippingRates: expect.any( Array ),
				lineItems: expect.any( Array ),
			} );
			expect( event.reject ).not.toHaveBeenCalled();
		} );

		test( 'should reject when no shipping rates available', async () => {
			const {
				transformCartDataForShippingRates,
			} = require( 'wcstripe/express-checkout/transformers/wc-to-stripe' );
			transformCartDataForShippingRates.mockReturnValueOnce( [] );

			await shippingAddressChangeHandler( event, elements );

			expect( event.reject ).toHaveBeenCalled();
			expect( event.resolve ).not.toHaveBeenCalled();
		} );

		test( 'should reject on API error', async () => {
			mockCartApi.updateCustomer.mockRejectedValue(
				new Error( 'API error' )
			);

			await shippingAddressChangeHandler( event, elements );

			expect( event.reject ).toHaveBeenCalled();
			expect( event.resolve ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'shippingRateChangeHandler', () => {
		let mockCartApi;
		let event;
		let elements;

		const cartResponse = {
			totals: {
				total_price: '1500',
				total_refund: '0',
				currency_minor_unit: 2,
			},
			items: [],
		};

		beforeEach( () => {
			mockCartApi = {
				selectShippingRate: jest.fn().mockResolvedValue( cartResponse ),
			};
			setCartApiHandler( mockCartApi );

			event = {
				shippingRate: {
					id: 'flat_rate:1',
				},
				resolve: jest.fn(),
				reject: jest.fn(),
			};
			elements = {
				update: jest.fn(),
			};
		} );

		afterEach( () => {
			jest.clearAllMocks();
		} );

		test( 'should call cartApi.selectShippingRate with correct data', async () => {
			await shippingRateChangeHandler( event, elements );

			expect( mockCartApi.selectShippingRate ).toHaveBeenCalledWith( {
				package_id: 0,
				rate_id: 'flat_rate:1',
			} );
		} );

		test( 'should update elements amount and resolve with line items', async () => {
			await shippingRateChangeHandler( event, elements );

			expect( elements.update ).toHaveBeenCalledWith( { amount: 1500 } );
			expect( event.resolve ).toHaveBeenCalledWith( {
				lineItems: expect.any( Array ),
			} );
			expect( event.reject ).not.toHaveBeenCalled();
		} );

		test( 'should subtract total_refund from amount', async () => {
			mockCartApi.selectShippingRate.mockResolvedValue( {
				totals: {
					total_price: '2000',
					total_refund: '500',
					currency_minor_unit: 2,
				},
				items: [],
			} );

			await shippingRateChangeHandler( event, elements );

			expect( elements.update ).toHaveBeenCalledWith( { amount: 1500 } );
		} );

		test( 'should reject on API error', async () => {
			mockCartApi.selectShippingRate.mockRejectedValue(
				new Error( 'error' )
			);

			await shippingRateChangeHandler( event, elements );

			expect( event.reject ).toHaveBeenCalled();
			expect( event.resolve ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'onConfirmHandler', () => {
		let api;
		let stripe;
		let elements;
		let completePayment;
		let abortPayment;
		let event;
		let order;

		beforeEach( () => {
			global.jQuery = {
				blockUI: jest.fn(),
				unblockUI: jest.fn(),
			};
			api = {
				expressCheckoutNormalizeAddress: jest.fn(),
				expressCheckoutECECreateOrder: jest.fn(),
				expressCheckoutECEPayForOrder: jest.fn(),
				confirmIntent: jest.fn(),
			};
			stripe = {
				createPaymentMethod: jest.fn(),
			};
			elements = {
				submit: jest.fn(),
			};
			completePayment = jest.fn();
			abortPayment = jest.fn();
			event = {
				billingDetails: {
					name: 'John Doe',
					email: 'john.doe@example.com',
					address: {
						organization: 'Some Company',
						country: 'US',
						line1: '123 Main St',
						line2: 'Apt 4B',
						city: 'New York',
						state: 'NY',
						postal_code: '10001',
					},
					phone: '(123) 456-7890',
				},
				shippingAddress: {
					name: 'John Doe',
					organization: 'Some Company',
					address: {
						country: 'US',
						line1: '123 Main St',
						line2: 'Apt 4B',
						city: 'New York',
						state: 'NY',
						postal_code: '10001',
					},
				},
				shippingRate: { id: 'rate_1' },
				expressPaymentType: 'express',
			};
			order = 123;
		} );

		afterEach( () => {
			jest.clearAllMocks();
			delete global.jQuery;
		} );

		test( 'should block UI immediately when payment confirmation starts, before any processing', async () => {
			// Fail fast on submit so we can confirm blockUI was called before any async work.
			elements.submit.mockResolvedValue( {
				error: { message: 'Submit error' },
			} );

			await onConfirmHandler( {
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event,
			} );

			expect( global.jQuery.blockUI ).toHaveBeenCalled();
		} );

		test( 'should abort payment if elements.submit fails', async () => {
			elements.submit.mockResolvedValue( {
				error: { message: 'Submit error' },
			} );

			await onConfirmHandler( {
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event,
			} );

			expect( elements.submit ).toHaveBeenCalled();
			expect( abortPayment ).toHaveBeenCalledWith(
				event,
				'Submit error'
			);
			expect( completePayment ).not.toHaveBeenCalled();
		} );

		test( 'should abort payment if stripe.createPaymentMethod fails', async () => {
			elements.submit.mockResolvedValue( {} );
			stripe.createPaymentMethod.mockResolvedValue( {
				error: { message: 'Payment method error' },
			} );

			await onConfirmHandler( {
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event,
			} );

			expect( elements.submit ).toHaveBeenCalled();
			expect( stripe.createPaymentMethod ).toHaveBeenCalledWith( {
				elements,
			} );
			expect( abortPayment ).toHaveBeenCalledWith(
				event,
				'Payment method error'
			);
			expect( completePayment ).not.toHaveBeenCalled();
		} );

		test( 'should abort payment if expressCheckoutECECreateOrder fails', async () => {
			elements.submit.mockResolvedValue( {} );
			stripe.createPaymentMethod.mockResolvedValue( {
				paymentMethod: { id: 'pm_123' },
			} );
			api.expressCheckoutECECreateOrder.mockResolvedValue( {
				payment_result: {
					payment_status: 'error',
					payment_details: [
						{
							key: 'errorMessage',
							value: 'Order creation error',
						},
					],
				},
			} );

			await onConfirmHandler( {
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event,
			} );

			const expectedOrderData = normalizeOrderData( {
				event,
				paymentMethodId: 'pm_123',
			} );
			expect( api.expressCheckoutECECreateOrder ).toHaveBeenCalledWith(
				expectedOrderData
			);
			expect( abortPayment ).toHaveBeenCalledWith(
				event,
				'Order creation error'
			);
			expect( completePayment ).not.toHaveBeenCalled();
		} );

		test( 'should complete payment if confirmationRequest is true', async () => {
			elements.submit.mockResolvedValue( {} );
			stripe.createPaymentMethod.mockResolvedValue( {
				paymentMethod: { id: 'pm_123' },
			} );
			api.expressCheckoutECECreateOrder.mockResolvedValue( {
				payment_result: {
					payment_status: 'success',
					redirect_url: 'https://example.com/redirect',
				},
			} );
			api.confirmIntent.mockReturnValue( true );

			await onConfirmHandler( {
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event,
			} );

			expect( api.confirmIntent ).toHaveBeenCalledWith(
				'https://example.com/redirect'
			);
			expect( completePayment ).toHaveBeenCalledWith(
				'https://example.com/redirect'
			);
			expect( abortPayment ).not.toHaveBeenCalled();
		} );

		test( 'should complete payment if confirmationRequest returns a redirect URL', async () => {
			elements.submit.mockResolvedValue( {} );
			stripe.createPaymentMethod.mockResolvedValue( {
				paymentMethod: { id: 'pm_123' },
			} );
			api.expressCheckoutECECreateOrder.mockResolvedValue( {
				payment_result: {
					payment_status: 'success',
					redirect_url: 'https://example.com/redirect',
				},
			} );
			api.confirmIntent.mockReturnValue( {
				request: Promise.resolve(
					'https://example.com/confirmation_redirect'
				),
			} );

			await onConfirmHandler( {
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event,
			} );

			expect( api.confirmIntent ).toHaveBeenCalledWith(
				'https://example.com/redirect'
			);
			expect( completePayment ).toHaveBeenCalledWith(
				'https://example.com/confirmation_redirect'
			);
			expect( abortPayment ).not.toHaveBeenCalled();
		} );

		test( 'should abort payment if confirmIntent throws an error', async () => {
			elements.submit.mockResolvedValue( {} );
			stripe.createPaymentMethod.mockResolvedValue( {
				paymentMethod: { id: 'pm_123' },
			} );
			api.expressCheckoutECECreateOrder.mockResolvedValue( {
				payment_result: {
					payment_status: 'success',
					redirect_url: 'https://example.com/redirect',
				},
			} );
			api.confirmIntent.mockReturnValue( {
				request: Promise.reject(
					new Error( 'Intent confirmation error' )
				),
			} );

			await onConfirmHandler( {
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event,
			} );

			expect( api.confirmIntent ).toHaveBeenCalledWith(
				'https://example.com/redirect'
			);
			expect( abortPayment ).toHaveBeenCalledWith(
				event,
				'Intent confirmation error'
			);
			expect( completePayment ).not.toHaveBeenCalled();
		} );

		test( 'should abort payment if expressCheckoutECEPayForOrder fails', async () => {
			elements.submit.mockResolvedValue( {} );
			stripe.createPaymentMethod.mockResolvedValue( {
				paymentMethod: { id: 'pm_123' },
			} );
			api.expressCheckoutECEPayForOrder.mockResolvedValue( {
				payment_result: {
					payment_status: 'error',
					payment_details: [
						{
							key: 'errorMessage',
							value: 'Order creation error',
						},
					],
				},
			} );

			await onConfirmHandler( {
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event,
				order,
			} );

			const expectedOrderData = normalizeOrderData( {
				event,
				paymentMethodId: 'pm_123',
			} );
			expect( api.expressCheckoutECEPayForOrder ).toHaveBeenCalledWith(
				123,
				{},
				expectedOrderData
			);
			expect( abortPayment ).toHaveBeenCalledWith(
				event,
				'Order creation error'
			);
			expect( completePayment ).not.toHaveBeenCalled();
		} );

		test( 'should complete payment (pay for order) if confirmationRequest is true', async () => {
			elements.submit.mockResolvedValue( {} );
			stripe.createPaymentMethod.mockResolvedValue( {
				paymentMethod: { id: 'pm_123' },
			} );
			api.expressCheckoutECEPayForOrder.mockResolvedValue( {
				payment_result: {
					payment_status: 'success',
					redirect_url: 'https://example.com/redirect',
				},
			} );
			api.confirmIntent.mockReturnValue( true );

			await onConfirmHandler( {
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event,
				order,
			} );

			expect( api.confirmIntent ).toHaveBeenCalledWith(
				'https://example.com/redirect'
			);
			expect( completePayment ).toHaveBeenCalledWith(
				'https://example.com/redirect'
			);
			expect( abortPayment ).not.toHaveBeenCalled();
		} );

		test( 'should complete payment (pay for order) if confirmationRequest returns a redirect URL', async () => {
			elements.submit.mockResolvedValue( {} );
			stripe.createPaymentMethod.mockResolvedValue( {
				paymentMethod: { id: 'pm_123' },
			} );
			api.expressCheckoutECEPayForOrder.mockResolvedValue( {
				payment_result: {
					payment_status: 'success',
					redirect_url: 'https://example.com/redirect',
				},
			} );
			api.confirmIntent.mockReturnValue( {
				request: Promise.resolve(
					'https://example.com/confirmation_redirect'
				),
			} );

			await onConfirmHandler( {
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event,
				order,
			} );

			expect( api.confirmIntent ).toHaveBeenCalledWith(
				'https://example.com/redirect'
			);
			expect( completePayment ).toHaveBeenCalledWith(
				'https://example.com/confirmation_redirect'
			);
			expect( abortPayment ).not.toHaveBeenCalled();
		} );

		test( 'should abort payment (pay for order) if confirmIntent throws an error', async () => {
			elements.submit.mockResolvedValue( {} );
			stripe.createPaymentMethod.mockResolvedValue( {
				paymentMethod: { id: 'pm_123' },
			} );
			api.expressCheckoutECEPayForOrder.mockResolvedValue( {
				payment_result: {
					payment_status: 'success',
					redirect_url: 'https://example.com/redirect',
				},
			} );
			api.confirmIntent.mockReturnValue( {
				request: Promise.reject(
					new Error( 'Intent confirmation error' )
				),
			} );

			await onConfirmHandler( {
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event,
				order,
			} );

			expect( api.confirmIntent ).toHaveBeenCalledWith(
				'https://example.com/redirect'
			);
			expect( abortPayment ).toHaveBeenCalledWith(
				event,
				'Intent confirmation error'
			);
			expect( completePayment ).not.toHaveBeenCalled();
		} );

		test( 'should extract redirect URL from payment_details when redirect_url is empty for 3DS authentication', async () => {
			const threeDSRedirectUrl =
				'#confirm-pi-pi_1234567890abcdef_secret_test1234567890abcdef:fake_nonce';

			elements.submit.mockResolvedValue( {} );
			stripe.createPaymentMethod.mockResolvedValue( {
				paymentMethod: { id: 'pm_123' },
			} );
			api.expressCheckoutECECreateOrder.mockResolvedValue( {
				payment_result: {
					payment_status: 'success',
					payment_details: [
						{
							key: 'result',
							value: 'success',
						},
						{
							key: 'redirect',
							value: threeDSRedirectUrl,
						},
						{
							key: 'payment_method',
							value: 'pm_test1234567890abcdef',
						},
					],
					redirect_url: '',
				},
			} );

			api.confirmIntent.mockReturnValue( true );

			await onConfirmHandler( {
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event,
			} );

			expect( api.expressCheckoutECECreateOrder ).toHaveBeenCalled();
			expect( api.confirmIntent ).toHaveBeenCalledWith(
				threeDSRedirectUrl
			);
			expect( completePayment ).toHaveBeenCalledWith(
				threeDSRedirectUrl
			);
			expect( abortPayment ).not.toHaveBeenCalled();
		} );
	} );
} );
