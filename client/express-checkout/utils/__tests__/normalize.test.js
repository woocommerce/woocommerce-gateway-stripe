import { select } from '@wordpress/data';
import {
	normalizeLineItems,
	normalizeOrderData,
	normalizeShippingAddress,
} from '../normalize';
import { getExpressCheckoutData } from 'wcstripe/express-checkout/utils';

jest.mock( '@wordpress/data' );

jest.mock( 'wcstripe/express-checkout/utils', () => ( {
	getExpressCheckoutData: jest.fn(),
} ) );

describe( 'Express checkout normalization', () => {
	describe( 'normalizeLineItems', () => {
		test( 'normalizes blocks array properly', () => {
			const displayItems = [
				{
					label: 'Item 1',
					value: 100,
				},
				{
					label: 'Item 2',
					value: 200,
				},
				{
					label: 'Item 3',
					valueWithTax: 300,
					value: 200,
				},
			];

			// Extra items in the array are expected since they're not stripped.
			const expected = [
				{
					name: 'Item 1',
					amount: 100,
				},
				{
					name: 'Item 2',
					amount: 200,
				},
				{
					name: 'Item 3',
					amount: 200,
				},
			];

			expect( normalizeLineItems( displayItems ) ).toStrictEqual(
				expected
			);
		} );

		test( 'normalizes shortcode array properly', () => {
			const displayItems = [
				{
					label: 'Item 1',
					amount: 100,
				},
				{
					label: 'Item 2',
					amount: 200,
				},
				{
					label: 'Item 3',
					amount: 300,
				},
			];

			const expected = [
				{
					name: 'Item 1',
					amount: 100,
				},
				{
					name: 'Item 2',
					amount: 200,
				},
				{
					name: 'Item 3',
					amount: 300,
				},
			];

			expect( normalizeLineItems( displayItems ) ).toStrictEqual(
				expected
			);
		} );

		test( 'normalizes discount line item properly', () => {
			const displayItems = [
				{
					label: 'Item 1',
					amount: 100,
				},
				{
					label: 'Item 2',
					amount: 200,
				},
				{
					label: 'Item 3',
					amount: 300,
				},
				{
					key: 'total_discount',
					label: 'Discount',
					amount: 50,
				},
			];

			const expected = [
				{
					name: 'Item 1',
					amount: 100,
				},
				{
					name: 'Item 2',
					amount: 200,
				},
				{
					name: 'Item 3',
					amount: 300,
				},
				{
					name: 'Discount',
					amount: -50,
				},
			];

			expect( normalizeLineItems( displayItems ) ).toStrictEqual(
				expected
			);
		} );
	} );

	describe( 'normalizeOrderData', () => {
		beforeEach( () => {
			expectedNormalizedData.payment_data[ 1 ].value = paymentMethodId;
			expectedNormalizedData.payment_data[ 2 ].value = '';

			window.wc = {
				wcBlocksData: {
					checkoutStore: 'checkoutStore',
					cartStore: 'cartStore',
				},
			};

			select.mockImplementation( () => {
				return {
					getAdditionalFields: () => {
						return {};
					},
					getCustomerData: () => {
						return {};
					},
				};
			} );

			getExpressCheckoutData.mockImplementation( ( param ) => {
				if ( param === 'has_block' ) {
					return true;
				}
				if ( param === 'is_checkout_page' ) {
					return true;
				}
				return undefined;
			} );
		} );
		const event = {
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

		const paymentMethodId = 'pm_123456';
		const expectedNormalizedData = {
			billing_address: {
				address_1: '123 Main St',
				address_2: 'Apt 4B',
				city: 'New York',
				company: 'Some Company',
				country: 'US',
				email: 'john.doe@example.com',
				first_name: 'John',
				last_name: 'Doe',
				phone: '1234567890',
				postcode: '10001',
				state: 'NY',
			},
			extensions: {},
			payment_data: [
				{
					key: 'payment_method',
					value: 'stripe',
				},
				{
					key: 'wc-stripe-payment-method',
					value: '',
				},
				{
					key: 'wc-stripe-confirmation-token',
					value: '',
				},
				{
					key: 'express_payment_type',
					value: 'express',
				},
				{
					key: 'wc-stripe-is-deferred-intent',
					value: true,
				},
			],
			payment_method: 'stripe',
			shipping_address: {
				address_1: '123 Main St',
				address_2: 'Apt 4B',
				city: 'New York',
				company: 'Some Company',
				country: 'US',
				first_name: 'John',
				last_name: 'Doe',
				method: [ 'rate_1' ],
				phone: '1234567890',
				postcode: '10001',
				state: 'NY',
			},
			additional_fields: {},
		};

		test( 'should normalize order data with complete event and payment information', () => {
			expect( normalizeOrderData( { event, paymentMethodId } ) ).toEqual(
				expectedNormalizedData
			);

			// Test with confirmation token.
			const confirmationTokenId = 'ctoken_123456';
			const expectedNormalizedDataWithConfirmationToken = {
				...expectedNormalizedData,
			};
			expectedNormalizedData.payment_data[ 1 ].value = '';
			expectedNormalizedData.payment_data[ 2 ].value = confirmationTokenId;

			expect(
				normalizeOrderData( { event, confirmationTokenId } )
			).toEqual( expectedNormalizedDataWithConfirmationToken );
		} );

		test( 'should include additional fields in the normalized order data', () => {
			const additionalFields = {
				'my-plugin': {
					field1: 'value1',
					field2: 'value2',
				},
			};
			select.mockImplementation( () => {
				return {
					getAdditionalFields: () => {
						return additionalFields;
					},
					getCustomerData: () => {
						return {};
					},
				};
			} );

			const expectedNormalizedDataWithAdditionalFields = {
				...expectedNormalizedData,
				additional_fields: additionalFields,
			};

			expect( normalizeOrderData( { event, paymentMethodId } ) ).toEqual(
				expectedNormalizedDataWithAdditionalFields
			);
		} );

		test( 'should include additional customer (address) fields in the normalized order data', () => {
			const additionalCustomerData = {
				custom_address_field1: 'test1',
				custom_address_field2: 'test2',
			};
			select.mockImplementation( () => {
				return {
					getAdditionalFields: () => {
						return {};
					},
					getCustomerData: () => {
						return {
							shippingAddress: {
								...additionalCustomerData,
							},
							billingAddress: {
								...additionalCustomerData,
							},
						};
					},
				};
			} );

			const expectedNormalizedDataWithAdditionalCustomerFields = {
				...expectedNormalizedData,
				shipping_address: {
					...expectedNormalizedData.shipping_address,
					...additionalCustomerData,
				},
				billing_address: {
					...expectedNormalizedData.billing_address,
					...additionalCustomerData,
				},
			};

			expect( normalizeOrderData( { event, paymentMethodId } ) ).toEqual(
				expectedNormalizedDataWithAdditionalCustomerFields
			);
		} );

		test( 'should normalize order data with missing optional event fields', () => {
			const expectedNormalizedDataWithMissingFields = {
				billing_address: {
					address_1: '',
					address_2: '',
					city: '',
					company: '',
					country: '',
					email: '',
					first_name: '',
					last_name: '-',
					phone: '',
					postcode: '',
					state: '',
				},
				extensions: {},
				payment_data: [
					{
						key: 'payment_method',
						value: 'stripe',
					},
					{
						key: 'wc-stripe-payment-method',
						value: paymentMethodId,
					},
					{
						key: 'wc-stripe-confirmation-token',
						value: '',
					},
					{
						key: 'express_payment_type',
						value: undefined,
					},
					{
						key: 'wc-stripe-is-deferred-intent',
						value: true,
					},
				],
				payment_method: 'stripe',
				shipping_address: {
					address_1: '',
					address_2: '',
					city: '',
					company: '',
					country: '',
					first_name: '',
					last_name: '',
					method: [ null ],
					phone: '',
					postcode: '',
					state: '',
				},
				additional_fields: {},
			};

			expect(
				normalizeOrderData( { event: {}, paymentMethodId } )
			).toEqual( expectedNormalizedDataWithMissingFields );
		} );

		test( 'should normalize order data with minimum required fields', () => {
			const minimumEvent = {
				billingDetails: {
					name: 'John',
				},
			};

			const expectedNormalizedDataWithMinimumFields = {
				billing_address: {
					address_1: '',
					address_2: '',
					city: '',
					company: '',
					country: '',
					email: '',
					first_name: 'John',
					last_name: '',
					phone: '',
					postcode: '',
					state: '',
				},
				extensions: {},
				payment_data: [
					{
						key: 'payment_method',
						value: 'stripe',
					},
					{
						key: 'wc-stripe-payment-method',
						value: paymentMethodId,
					},
					{
						key: 'wc-stripe-confirmation-token',
						value: '',
					},
					{
						key: 'express_payment_type',
						value: undefined,
					},
					{
						key: 'wc-stripe-is-deferred-intent',
						value: true,
					},
				],
				payment_method: 'stripe',
				shipping_address: {
					address_1: '',
					address_2: '',
					city: '',
					company: '',
					country: '',
					first_name: '',
					last_name: '',
					method: [ null ],
					phone: '',
					postcode: '',
					state: '',
				},
				additional_fields: {},
			};

			expect(
				normalizeOrderData( { event: minimumEvent, paymentMethodId } )
			).toEqual( expectedNormalizedDataWithMinimumFields );
		} );
	} );

	describe( 'normalizeShippingAddress', () => {
		test( 'should normalize shipping address with all fields present', () => {
			const shippingAddress = {
				recipient: 'John Doe',
				addressLine: [ '123 Main St', 'Apt 4B' ],
				city: 'New York',
				state: 'NY',
				country: 'US',
				postal_code: '10001',
			};

			const expectedNormalizedAddress = {
				first_name: 'John',
				last_name: 'Doe',
				company: '',
				address_1: '123 Main St',
				address_2: 'Apt 4B',
				city: 'New York',
				state: 'NY',
				country: 'US',
				postcode: '10001',
			};

			expect( normalizeShippingAddress( shippingAddress ) ).toEqual(
				expectedNormalizedAddress
			);
		} );

		test( 'should normalize shipping address with only recipient name', () => {
			const shippingAddress = {
				recipient: 'John',
			};

			const expectedNormalizedAddress = {
				first_name: 'John',
				last_name: '',
				company: '',
				address_1: '',
				address_2: '',
				city: '',
				state: '',
				country: '',
				postcode: '',
			};

			expect( normalizeShippingAddress( shippingAddress ) ).toEqual(
				expectedNormalizedAddress
			);
		} );

		test( 'should normalize shipping address with missing recipient name', () => {
			const shippingAddress = {
				addressLine: [ '123 Main St' ],
				city: 'New York',
				state: 'NY',
				country: 'US',
				postal_code: '10001',
			};

			const expectedNormalizedAddress = {
				first_name: '',
				last_name: '',
				company: '',
				address_1: '123 Main St',
				address_2: '',
				city: 'New York',
				state: 'NY',
				country: 'US',
				postcode: '10001',
			};

			expect( normalizeShippingAddress( shippingAddress ) ).toEqual(
				expectedNormalizedAddress
			);
		} );

		test( 'should normalize shipping address with empty addressLine', () => {
			const shippingAddress = {
				recipient: 'John Doe',
				addressLine: [],
				city: 'New York',
				state: 'NY',
				country: 'US',
				postal_code: '10001',
			};

			const expectedNormalizedAddress = {
				first_name: 'John',
				last_name: 'Doe',
				company: '',
				address_1: '',
				address_2: '',
				city: 'New York',
				state: 'NY',
				country: 'US',
				postcode: '10001',
			};

			expect( normalizeShippingAddress( shippingAddress ) ).toEqual(
				expectedNormalizedAddress
			);
		} );

		test( 'should normalize an empty shipping address', () => {
			const shippingAddress = {};

			const expectedNormalizedAddress = {
				first_name: '',
				last_name: '',
				company: '',
				address_1: '',
				address_2: '',
				city: '',
				state: '',
				country: '',
				postcode: '',
			};

			expect( normalizeShippingAddress( shippingAddress ) ).toEqual(
				expectedNormalizedAddress
			);
		} );

		test( 'should normalize a shipping address with a multi-word recipient name', () => {
			const shippingAddress = {
				recipient: 'John Doe Smith',
				addressLine: [ '123 Main St', 'Apt 4B' ],
				city: 'New York',
				state: 'NY',
				country: 'US',
				postal_code: '10001',
			};

			const expectedNormalizedAddress = {
				first_name: 'John',
				last_name: 'Doe Smith',
				company: '',
				address_1: '123 Main St',
				address_2: 'Apt 4B',
				city: 'New York',
				state: 'NY',
				country: 'US',
				postcode: '10001',
			};

			expect( normalizeShippingAddress( shippingAddress ) ).toEqual(
				expectedNormalizedAddress
			);
		} );
	} );
} );
