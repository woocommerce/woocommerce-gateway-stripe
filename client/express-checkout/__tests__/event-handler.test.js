/**
 * Internal dependencies
 */
import {
	normalizeLineItems,
	normalizeShippingAddress,
	normalizeOrderData,
	normalizePayForOrderData,
} from '../utils';
import {
	onConfirmHandler,
	shippingAddressChangeHandler,
	shippingRateChangeHandler,
} from 'wcstripe/express-checkout/event-handler';

describe( 'Express checkout event handlers', () => {
	describe( 'shippingAddressChangeHandler', () => {
		let api;
		let event;
		let elements;

		beforeEach( () => {
			api = {
				expressCheckoutECECalculateShippingOptions: jest.fn(),
			};
			event = {
				address: {
					recipient: 'John Doe',
					addressLine: [ '123 Main St' ],
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

		test( 'should handle successful response', async () => {
			const response = {
				result: 'success',
				total: { amount: 1000 },
				shipping_options: [
					{ id: 'option_1', label: 'Standard Shipping' },
				],
				displayItems: [ { label: 'Sample Item', amount: 500 } ],
			};

			api.expressCheckoutECECalculateShippingOptions.mockResolvedValue(
				response
			);

			await shippingAddressChangeHandler( api, event, elements );

			const expectedNormalizedAddress = normalizeShippingAddress(
				event.address
			);
			expect(
				api.expressCheckoutECECalculateShippingOptions
			).toHaveBeenCalledWith( expectedNormalizedAddress );

			const expectedNormalizedLineItems = normalizeLineItems(
				response.displayItems
			);
			expect( elements.update ).toHaveBeenCalledWith( { amount: 1000 } );
			expect( event.resolve ).toHaveBeenCalledWith( {
				shippingRates: response.shipping_options,
				lineItems: expectedNormalizedLineItems,
			} );
			expect( event.reject ).not.toHaveBeenCalled();
		} );

		test( 'should handle unsuccessful response', async () => {
			const response = {
				result: 'error',
			};

			api.expressCheckoutECECalculateShippingOptions.mockResolvedValue(
				response
			);

			await shippingAddressChangeHandler( api, event, elements );

			const expectedNormalizedAddress = normalizeShippingAddress(
				event.address
			);
			expect(
				api.expressCheckoutECECalculateShippingOptions
			).toHaveBeenCalledWith( expectedNormalizedAddress );
			expect( elements.update ).not.toHaveBeenCalled();
			expect( event.resolve ).not.toHaveBeenCalled();
			expect( event.reject ).toHaveBeenCalled();
		} );

		test( 'should handle API call failure', async () => {
			api.expressCheckoutECECalculateShippingOptions.mockRejectedValue(
				new Error( 'API error' )
			);

			await shippingAddressChangeHandler( api, event, elements );

			const expectedNormalizedAddress = normalizeShippingAddress(
				event.address
			);
			expect(
				api.expressCheckoutECECalculateShippingOptions
			).toHaveBeenCalledWith( expectedNormalizedAddress );
			expect( elements.update ).not.toHaveBeenCalled();
			expect( event.resolve ).not.toHaveBeenCalled();
			expect( event.reject ).toHaveBeenCalled();
		} );
	} );

	describe( 'shippingRateChangeHandler', () => {
		let api;
		let event;
		let elements;

		beforeEach( () => {
			api = {
				expressCheckoutUpdateShippingDetails: jest.fn(),
			};
			event = {
				shippingRate: {
					id: 'rate_1',
					label: 'Standard Shipping',
					amount: 500,
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

		test( 'should handle successful response', async () => {
			const response = {
				result: 'success',
				total: { amount: 1500 },
				displayItems: [ { label: 'Sample Item', amount: 1000 } ],
			};

			api.expressCheckoutUpdateShippingDetails.mockResolvedValue(
				response
			);

			await shippingRateChangeHandler( api, event, elements );

			const expectedNormalizedLineItems = normalizeLineItems(
				response.displayItems
			);
			expect(
				api.expressCheckoutUpdateShippingDetails
			).toHaveBeenCalledWith( event.shippingRate );
			expect( elements.update ).toHaveBeenCalledWith( { amount: 1500 } );
			expect( event.resolve ).toHaveBeenCalledWith( {
				lineItems: expectedNormalizedLineItems,
			} );
			expect( event.reject ).not.toHaveBeenCalled();
		} );

		test( 'should handle unsuccessful response', async () => {
			const response = {
				result: 'error',
			};

			api.expressCheckoutUpdateShippingDetails.mockResolvedValue(
				response
			);

			await shippingRateChangeHandler( api, event, elements );

			expect(
				api.expressCheckoutUpdateShippingDetails
			).toHaveBeenCalledWith( event.shippingRate );
			expect( elements.update ).not.toHaveBeenCalled();
			expect( event.resolve ).not.toHaveBeenCalled();
			expect( event.reject ).toHaveBeenCalled();
		} );

		test( 'should handle API call failure', async () => {
			api.expressCheckoutUpdateShippingDetails.mockRejectedValue(
				new Error( 'API error' )
			);

			await shippingRateChangeHandler( api, event, elements );

			expect(
				api.expressCheckoutUpdateShippingDetails
			).toHaveBeenCalledWith( event.shippingRate );
			expect( elements.update ).not.toHaveBeenCalled();
			expect( event.resolve ).not.toHaveBeenCalled();
			expect( event.reject ).toHaveBeenCalled();
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
			api = {
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
		} );

		test( 'should abort payment if elements.submit fails', async () => {
			elements.submit.mockResolvedValue( {
				error: { message: 'Submit error' },
			} );

			await onConfirmHandler(
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event
			);

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

			await onConfirmHandler(
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event
			);

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
				result: 'error',
				messages: 'Order creation error',
			} );

			await onConfirmHandler(
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event
			);

			const expectedOrderData = normalizeOrderData( event, 'pm_123' );
			expect( api.expressCheckoutECECreateOrder ).toHaveBeenCalledWith(
				expectedOrderData
			);
			expect( abortPayment ).toHaveBeenCalledWith(
				event,
				'Order creation error',
				true
			);
			expect( completePayment ).not.toHaveBeenCalled();
		} );

		test( 'should complete payment if confirmationRequest is true', async () => {
			elements.submit.mockResolvedValue( {} );
			stripe.createPaymentMethod.mockResolvedValue( {
				paymentMethod: { id: 'pm_123' },
			} );
			api.expressCheckoutECECreateOrder.mockResolvedValue( {
				result: 'success',
				redirect: 'https://example.com/redirect',
			} );
			api.confirmIntent.mockReturnValue( true );

			await onConfirmHandler(
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event
			);

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
				result: 'success',
				redirect: 'https://example.com/redirect',
			} );
			api.confirmIntent.mockReturnValue( {
				request: Promise.resolve(
					'https://example.com/confirmation_redirect'
				),
				isOrderPage: false,
			} );

			await onConfirmHandler(
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event
			);

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
				result: 'success',
				redirect: 'https://example.com/redirect',
			} );
			api.confirmIntent.mockReturnValue( {
				request: Promise.reject(
					new Error( 'Intent confirmation error' )
				),
				isOrderPage: false,
			} );

			await onConfirmHandler(
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event
			);

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
				result: 'error',
				messages: 'Order creation error',
			} );

			await onConfirmHandler(
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event,
				order
			);

			const expectedOrderData = normalizePayForOrderData(
				event,
				'pm_123'
			);
			expect( api.expressCheckoutECEPayForOrder ).toHaveBeenCalledWith(
				123,
				expectedOrderData
			);
			expect( abortPayment ).toHaveBeenCalledWith(
				event,
				'Order creation error',
				true
			);
			expect( completePayment ).not.toHaveBeenCalled();
		} );

		test( 'should complete payment (pay for order) if confirmationRequest is true', async () => {
			elements.submit.mockResolvedValue( {} );
			stripe.createPaymentMethod.mockResolvedValue( {
				paymentMethod: { id: 'pm_123' },
			} );
			api.expressCheckoutECEPayForOrder.mockResolvedValue( {
				result: 'success',
				redirect: 'https://example.com/redirect',
			} );
			api.confirmIntent.mockReturnValue( true );

			await onConfirmHandler(
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event,
				order
			);

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
				result: 'success',
				redirect: 'https://example.com/redirect',
			} );
			api.confirmIntent.mockReturnValue( {
				request: Promise.resolve(
					'https://example.com/confirmation_redirect'
				),
				isOrderPage: false,
			} );

			await onConfirmHandler(
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event,
				order
			);

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
				result: 'success',
				redirect: 'https://example.com/redirect',
			} );
			api.confirmIntent.mockReturnValue( {
				request: Promise.reject(
					new Error( 'Intent confirmation error' )
				),
				isOrderPage: false,
			} );

			await onConfirmHandler(
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event,
				order
			);

			expect( api.confirmIntent ).toHaveBeenCalledWith(
				'https://example.com/redirect'
			);
			expect( abortPayment ).toHaveBeenCalledWith(
				event,
				'Intent confirmation error'
			);
			expect( completePayment ).not.toHaveBeenCalled();
		} );
	} );
} );
