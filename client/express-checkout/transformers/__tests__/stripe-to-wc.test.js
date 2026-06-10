import { transformStripeShippingAddressForStoreApi } from '../stripe-to-wc';

describe( 'stripe-to-wc transformers', () => {
	describe( 'transformStripeShippingAddressForStoreApi', () => {
		it( 'maps all Stripe ECE address fields to Store API format', () => {
			const result = transformStripeShippingAddressForStoreApi(
				'John Doe',
				{
					line1: '123 Main St',
					line2: 'Apt 4B',
					city: 'New York',
					state: 'NY',
					postal_code: '10 001',
					country: 'US',
					organization: 'Acme Inc',
				}
			);

			expect( result ).toEqual( {
				first_name: 'John',
				last_name: 'Doe',
				company: 'Acme Inc',
				address_1: '123 Main St',
				address_2: 'Apt 4B',
				city: 'New York',
				state: 'NY',
				postcode: '10001',
				country: 'US',
			} );
		} );

		it( 'handles missing optional fields with empty strings', () => {
			const result = transformStripeShippingAddressForStoreApi(
				undefined,
				{
					city: 'London',
					country: 'GB',
				}
			);

			expect( result ).toEqual( {
				first_name: '',
				last_name: '',
				company: '',
				address_1: '',
				address_2: '',
				city: 'London',
				state: '',
				postcode: '',
				country: 'GB',
			} );
		} );

		it( 'handles single-word name', () => {
			const result = transformStripeShippingAddressForStoreApi(
				'Madonna',
				{ country: 'US' }
			);

			expect( result.first_name ).toBe( 'Madonna' );
			expect( result.last_name ).toBe( '' );
		} );

		it( 'handles multi-word name', () => {
			const result = transformStripeShippingAddressForStoreApi(
				'Mary Jane Watson',
				{ country: 'US' }
			);

			expect( result.first_name ).toBe( 'Mary' );
			expect( result.last_name ).toBe( 'Jane Watson' );
		} );

		it( 'strips spaces from postal code', () => {
			const result = transformStripeShippingAddressForStoreApi( 'Test', {
				postal_code: 'SW1A 1AA',
				country: 'GB',
			} );

			expect( result.postcode ).toBe( 'SW1A1AA' );
		} );

		it( 'handles null/undefined shippingAddress without throwing', () => {
			expect( () =>
				transformStripeShippingAddressForStoreApi( 'John Doe', null )
			).not.toThrow();
			expect( () =>
				transformStripeShippingAddressForStoreApi(
					'John Doe',
					undefined
				)
			).not.toThrow();

			const result = transformStripeShippingAddressForStoreApi(
				'John Doe',
				null
			);
			expect( result ).toEqual( {
				first_name: 'John',
				last_name: 'Doe',
				company: '',
				address_1: '',
				address_2: '',
				city: '',
				state: '',
				postcode: '',
				country: '',
			} );
		} );

		it( 'normalizes leading and collapsed whitespace in name', () => {
			const result = transformStripeShippingAddressForStoreApi(
				'  John   Doe  ',
				{ country: 'US' }
			);

			expect( result.first_name ).toBe( 'John' );
			expect( result.last_name ).toBe( 'Doe' );
		} );
	} );
} );
