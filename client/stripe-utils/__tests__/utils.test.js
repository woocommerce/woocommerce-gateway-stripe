import { getFontSizeBase, getDefaultValues } from '../utils';

describe( 'utils', () => {
	describe( 'getFontSizeBase', () => {
		const globalValues = global.wc_stripe_upe_params;

		beforeEach( () => {
			global.wc_stripe_upe_params = {
				shouldShowOptimizedCheckout: false,
			};
		} );

		afterEach( () => {
			global.wc_stripe_upe_params = globalValues;
		} );

		it( 'Optimized Checkout - should increase the provided font size by 2', () => {
			global.wc_stripe_upe_params = { shouldShowOptimizedCheckout: true };

			const fontSize = '16px';
			const expectedFontSize = '18px';
			const result = getFontSizeBase( fontSize );
			expect( result ).toBe( expectedFontSize );
		} );

		it( 'Optimized Checkout - should increase the provided font size by 2 (decimal value)', () => {
			global.wc_stripe_upe_params = { shouldShowOptimizedCheckout: true };

			const fontSize = '16.5px';
			const expectedFontSize = '18.5px';
			const result = getFontSizeBase( fontSize );
			expect( result ).toBe( expectedFontSize );
		} );

		it( 'default size', () => {
			const fontSize = '16px';
			const expectedFontSize = '16px';
			const result = getFontSizeBase( fontSize );
			expect( result ).toBe( expectedFontSize );
		} );
	} );

	describe( 'getDefaultValues', () => {
		const globalValues = global.wc_stripe_upe_params;
		let mockGetElementById;

		beforeEach( () => {
			global.wc_stripe_upe_params = {
				shouldShowOptimizedCheckout: false,
			};

			// Mock document.getElementById for fallback behavior
			mockGetElementById = jest.fn();
			document.getElementById = mockGetElementById;
		} );

		afterEach( () => {
			global.wc_stripe_upe_params = globalValues;
			jest.restoreAllMocks();
		} );

		describe( 'when isOrderPay, isChangingPayment, or isAddPaymentMethod is true', () => {
			it( 'should return correctly formatted billing data from customerBillingData', () => {
				global.wc_stripe_upe_params = {
					isOrderPay: true,
					customerBillingData: {
						name: 'John Doe',
						email: 'john@example.com',
						phone: '+1234567890',
						address: {
							country: 'us', // lowercase, should be uppercased
							line1: '123 Main St',
							line2: 'Apt 4B',
							city: 'New York',
							state: 'NY',
							postal_code: '10001',
						},
					},
				};

				const result = getDefaultValues();

				expect( result ).toEqual( {
					defaultValues: {
						billingDetails: {
							name: 'John Doe',
							email: 'john@example.com',
							phone: '+1234567890',
							address: {
								country: 'US', // Should be uppercase
								line1: '123 Main St',
								line2: 'Apt 4B',
								city: 'New York',
								state: 'NY',
								postal_code: '10001',
							},
						},
					},
				} );
			} );

			it( 'should filter out empty address fields and trim whitespace', () => {
				global.wc_stripe_upe_params = {
					isOrderPay: true,
					customerBillingData: {
						name: '  John Doe  ',
						email: '  john@example.com  ',
						phone: '  +1234567890  ',
						address: {
							country: '  us  ',
							line1: '  123 Main St  ',
							line2: '', // empty, should be filtered out
							city: '  New York  ',
							state: '    ', // only whitespace, should be filtered out
							postal_code: '  10001  ',
						},
					},
				};

				const result = getDefaultValues();

				expect( result.defaultValues.billingDetails.name ).toBe(
					'John Doe'
				);
				expect( result.defaultValues.billingDetails.email ).toBe(
					'john@example.com'
				);
				expect( result.defaultValues.billingDetails.phone ).toBe(
					'+1234567890'
				);
				expect( result.defaultValues.billingDetails.address ).toEqual( {
					country: 'US',
					line1: '123 Main St',
					city: 'New York',
					postal_code: '10001',
				} );
				expect(
					result.defaultValues.billingDetails.address.line2
				).toBeUndefined();
				expect(
					result.defaultValues.billingDetails.address.state
				).toBeUndefined();
			} );

			it( 'should not include address object if all address fields are empty', () => {
				global.wc_stripe_upe_params = {
					isOrderPay: true,
					customerBillingData: {
						email: 'test@example.com',
						address: {
							country: '',
							line1: '',
							line2: '',
							city: '',
							state: '',
							postal_code: '',
						},
					},
				};

				const result = getDefaultValues();

				expect(
					result.defaultValues.billingDetails.address
				).toBeUndefined();
				expect( result.defaultValues.billingDetails.email ).toBe(
					'test@example.com'
				);
			} );

			it( 'should return undefined for empty name and phone', () => {
				global.wc_stripe_upe_params = {
					isOrderPay: true,
					customerBillingData: {
						email: 'test@example.com',
						name: '',
						phone: '',
						address: {
							country: 'US',
						},
					},
				};

				const result = getDefaultValues();

				expect(
					result.defaultValues.billingDetails.name
				).toBeUndefined();
				expect(
					result.defaultValues.billingDetails.phone
				).toBeUndefined();
			} );

			it( 'should return empty object if customerBillingData is missing or email is missing', () => {
				mockGetElementById.mockReturnValue( null );

				// Missing customerBillingData
				global.wc_stripe_upe_params = {
					isOrderPay: true,
				};
				expect( getDefaultValues() ).toEqual( {} );

				// Missing email
				global.wc_stripe_upe_params = {
					isOrderPay: true,
					customerBillingData: {
						name: 'John Doe',
						// email missing
					},
				};
				expect( getDefaultValues() ).toEqual( {} );
			} );
		} );

		describe( 'fallback behavior when no customer billing data', () => {
			it( 'should fallback to reading from DOM elements for Link on checkout page', () => {
				global.wc_stripe_upe_params = {
					isCheckout: true,
					// No isOrderPay, isChangingPayment, or isAddPaymentMethod
				};

				const mockBillingEmail = {
					value: 'checkout@example.com',
				};
				const mockBillingPhone = {
					value: '+1987654321',
				};

				mockGetElementById
					.mockReturnValueOnce( mockBillingEmail ) // billing_email
					.mockReturnValueOnce( mockBillingPhone ); // billing_phone

				const result = getDefaultValues();

				expect( result ).toEqual( {
					defaultValues: {
						billingDetails: {
							email: 'checkout@example.com',
							phone: '+1987654321',
						},
					},
				} );
			} );
		} );
	} );
} );
