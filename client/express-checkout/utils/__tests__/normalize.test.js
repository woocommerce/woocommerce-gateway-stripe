/**
 * Internal dependencies
 */
import {
	normalizeLineItems,
	normalizeOrderData,
	normalizePayForOrderData,
	normalizeShippingAddress,
} from '../normalize';

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
		test( 'should normalize order data with complete event and paymentMethodId', () => {
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
				customer_note: undefined,
				extensions: {},
				payment_data: [
					{
						key: 'payment_method',
						value: 'stripe',
					},
					{
						key: 'wc-stripe-payment-method',
						value: 'pm_123456',
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
			};

			expect( normalizeOrderData( event, paymentMethodId ) ).toEqual(
				expectedNormalizedData
			);
		} );

		test( 'should normalize order data with missing optional event fields', () => {
			const event = {};
			const paymentMethodId = 'pm_123456';

			const expectedNormalizedData = {
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
				customer_note: undefined,
				extensions: {},
				payment_data: [
					{
						key: 'payment_method',
						value: 'stripe',
					},
					{
						key: 'wc-stripe-payment-method',
						value: 'pm_123456',
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
			};

			expect( normalizeOrderData( event, paymentMethodId ) ).toEqual(
				expectedNormalizedData
			);
		} );

		test( 'should normalize order data with minimum required fields', () => {
			const event = {
				billingDetails: {
					name: 'John',
				},
			};
			const paymentMethodId = 'pm_123456';

			const expectedNormalizedData = {
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
				customer_note: undefined,
				extensions: {},
				payment_data: [
					{
						key: 'payment_method',
						value: 'stripe',
					},
					{
						key: 'wc-stripe-payment-method',
						value: 'pm_123456',
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
			};

			expect( normalizeOrderData( event, paymentMethodId ) ).toEqual(
				expectedNormalizedData
			);
		} );
	} );

	describe( 'normalizePayForOrderData', () => {
		test( 'should normalize pay for order data with complete event and paymentMethodId', () => {
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

			expect( normalizePayForOrderData( event, 'pm_123456' ) ).toEqual( {
				payment_data: [
					{
						key: 'payment_method',
						value: 'stripe',
					},
					{
						key: 'wc-stripe-payment-method',
						value: 'pm_123456',
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
			} );
		} );

		test( 'should normalize pay for order data with empty event and empty payment method', () => {
			const event = {};
			const paymentMethodId = '';

			expect(
				normalizePayForOrderData( event, paymentMethodId )
			).toEqual( {
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
						key: 'express_payment_type',
						value: undefined,
					},
					{
						key: 'wc-stripe-is-deferred-intent',
						value: true,
					},
				],
				payment_method: 'stripe',
			} );
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
