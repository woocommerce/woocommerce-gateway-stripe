import {
	getFontSizeBase,
	getDefaultValues,
	getBillingDetailsForDeferredFlow,
	getHiddenBillingFields,
} from '../utils';
import { initializeUPEAppearance } from '../upe-appearance';
import { getAppearance } from '../../styles/upe';

jest.mock( '../../styles/upe', () => ( {
	getAppearance: jest.fn(),
	getExpandedOptimizedCheckoutRules: jest.fn( ( rules ) => rules ),
} ) );

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

	describe( 'getBillingDetailsForDeferredFlow', () => {
		const globalValues = global.wc_stripe_upe_params;

		afterEach( () => {
			global.wc_stripe_upe_params = globalValues;
		} );

		it.each( [ 'isOrderPay', 'isChangingPayment', 'isAddPaymentMethod' ] )(
			'returns billing_details from customerBillingData when %s is true',
			( flag ) => {
				global.wc_stripe_upe_params = {
					[ flag ]: true,
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

				expect( getBillingDetailsForDeferredFlow() ).toEqual( {
					name: 'John Doe',
					email: 'john@example.com',
					phone: '+1234567890',
					address: {
						country: 'US',
						line1: '123 Main St',
						line2: 'Apt 4B',
						city: 'New York',
						state: 'NY',
						postal_code: '10001',
					},
				} );
			}
		);

		it( 'omits empty address fields, trims values, and uppercases the country', () => {
			global.wc_stripe_upe_params = {
				isOrderPay: true,
				customerBillingData: {
					name: '  John Doe  ',
					email: '  john@example.com  ',
					phone: '',
					address: {
						country: 'gb',
						line1: '10 Downing St',
						line2: '',
						city: '',
						state: '',
						postal_code: 'SW1A 2AA',
					},
				},
			};

			expect( getBillingDetailsForDeferredFlow() ).toEqual( {
				name: 'John Doe',
				email: 'john@example.com',
				address: {
					country: 'GB',
					line1: '10 Downing St',
					postal_code: 'SW1A 2AA',
				},
			} );
		} );

		it( 'returns null on standard checkout', () => {
			global.wc_stripe_upe_params = {
				isCheckout: true,
				customerBillingData: {
					email: 'john@example.com',
				},
			};

			expect( getBillingDetailsForDeferredFlow() ).toBeNull();
		} );

		it( 'returns null when customer email is missing', () => {
			global.wc_stripe_upe_params = {
				isOrderPay: true,
				customerBillingData: {
					name: 'John Doe',
					address: { line1: '123 Main St' },
				},
			};

			expect( getBillingDetailsForDeferredFlow() ).toBeNull();
		} );
	} );

	describe( 'initializeUPEAppearance', () => {
		const globalValues = global.wc_stripe_upe_params;

		beforeEach( () => {
			global.wc_stripe_upe_params = {};
			getAppearance.mockReturnValue( { theme: 'computed' } );
		} );

		afterEach( () => {
			global.wc_stripe_upe_params = globalValues;
		} );

		describe( 'returns server-provided appearance', () => {
			it( 'returns classic appearance from server when isBlockCheckout is false', () => {
				const serverAppearance = { theme: 'server-classic' };
				global.wc_stripe_upe_params = { appearance: serverAppearance };

				const result = initializeUPEAppearance( 'false' );

				expect( result ).toBe( serverAppearance );
				expect( getAppearance ).not.toHaveBeenCalled();
			} );

			it( 'returns blocks appearance from server when isBlockCheckout is true', () => {
				const serverAppearance = { theme: 'server-blocks' };
				global.wc_stripe_upe_params = {
					blocksAppearance: serverAppearance,
				};

				const result = initializeUPEAppearance( 'true' );

				expect( result ).toBe( serverAppearance );
				expect( getAppearance ).not.toHaveBeenCalled();
			} );

			it( 'does not use classic server appearance when isBlockCheckout is true', () => {
				// Only `appearance` is set, not `blocksAppearance`.
				global.wc_stripe_upe_params = {
					appearance: { theme: 'server-classic' },
				};

				initializeUPEAppearance( 'true' );

				expect( getAppearance ).toHaveBeenCalledWith(
					true,
					false,
					false
				);
			} );

			it( 'falls through to computed appearance when server appearance is falsy', () => {
				// The guard is `if (customAppearance)`, so null is ignored.
				global.wc_stripe_upe_params = { appearance: null };

				initializeUPEAppearance( 'false' );

				expect( getAppearance ).toHaveBeenCalledWith(
					false,
					false,
					false
				);
			} );

			it( 'does not use server blocks appearance when isBlockCheckout is false', () => {
				// Only `blocksAppearance` is set, not `appearance`.
				global.wc_stripe_upe_params = {
					blocksAppearance: { theme: 'server-blocks' },
				};

				initializeUPEAppearance( 'false' );

				expect( getAppearance ).toHaveBeenCalledWith(
					false,
					false,
					false
				);
			} );
		} );

		// Cache tests require a fresh module instance so the module-level
		// `appearanceCache` starts empty. jest.isolateModules() and require() allow us
		// to get both a fresh `initializeUPEAppearance` and the fresh mock instance
		// that the isolated `utils.js` will actually use.
		describe( 'computes and caches appearance', () => {
			it( 'calls getAppearance with isBlocks=false for classic checkout', () => {
				jest.isolateModules( () => {
					const {
						initializeUPEAppearance: init,
					} = require( '../upe-appearance' );
					const {
						getAppearance: mockGetAppearance,
					} = require( '../../styles/upe' );
					mockGetAppearance.mockReturnValue( { theme: 'classic' } );

					const result = init( 'false' );

					expect( mockGetAppearance ).toHaveBeenCalledWith(
						false,
						false,
						false
					);
					expect( result ).toEqual( { theme: 'classic' } );
				} );
			} );

			it( 'calls getAppearance with isBlocks=true for blocks checkout', () => {
				jest.isolateModules( () => {
					const {
						initializeUPEAppearance: init,
					} = require( '../upe-appearance' );
					const {
						getAppearance: mockGetAppearance,
					} = require( '../../styles/upe' );
					mockGetAppearance.mockReturnValue( { theme: 'blocks' } );

					const result = init( 'true' );

					expect( mockGetAppearance ).toHaveBeenCalledWith(
						true,
						false,
						false
					);
					expect( result ).toEqual( { theme: 'blocks' } );
				} );
			} );

			it( 'defaults to classic checkout when isBlockCheckout argument is omitted', () => {
				jest.isolateModules( () => {
					const {
						initializeUPEAppearance: init,
					} = require( '../upe-appearance' );
					const {
						getAppearance: mockGetAppearance,
					} = require( '../../styles/upe' );
					mockGetAppearance.mockReturnValue( { theme: 'classic' } );

					init();

					expect( mockGetAppearance ).toHaveBeenCalledWith(
						false,
						false,
						false
					);
				} );
			} );

			it( 'caches computed appearance for classic checkout', () => {
				jest.isolateModules( () => {
					const {
						initializeUPEAppearance: init,
					} = require( '../upe-appearance' );
					const {
						getAppearance: mockGetAppearance,
					} = require( '../../styles/upe' );
					mockGetAppearance.mockReturnValue( { theme: 'cached' } );
					// Clear accumulated calls from earlier tests; keep the
					// return-value implementation set above.
					mockGetAppearance.mockClear();

					init( 'false' );
					init( 'false' );

					expect( mockGetAppearance ).toHaveBeenCalledTimes( 1 );
				} );
			} );

			it( 'caches computed appearance for blocks checkout', () => {
				jest.isolateModules( () => {
					const {
						initializeUPEAppearance: init,
					} = require( '../upe-appearance' );
					const {
						getAppearance: mockGetAppearance,
					} = require( '../../styles/upe' );
					mockGetAppearance.mockReturnValue( { theme: 'cached' } );
					mockGetAppearance.mockClear();

					init( 'true' );
					init( 'true' );

					expect( mockGetAppearance ).toHaveBeenCalledTimes( 1 );
				} );
			} );

			it( 'returns the same cached object reference on subsequent calls', () => {
				jest.isolateModules( () => {
					const {
						initializeUPEAppearance: init,
					} = require( '../upe-appearance' );
					const {
						getAppearance: mockGetAppearance,
					} = require( '../../styles/upe' );
					mockGetAppearance.mockReturnValue( { theme: 'cached' } );
					mockGetAppearance.mockClear();

					const firstResult = init( 'false' );
					const secondResult = init( 'false' );

					expect( secondResult ).toBe( firstResult );
				} );
			} );

			it( 'treats boolean true as classic checkout, not blocks', () => {
				// isBlockCheckout uses string comparison (=== 'true'), so a
				// boolean true is not equivalent and falls back to classic.
				jest.isolateModules( () => {
					const {
						initializeUPEAppearance: init,
					} = require( '../upe-appearance' );
					const {
						getAppearance: mockGetAppearance,
					} = require( '../../styles/upe' );
					mockGetAppearance.mockReturnValue( { theme: 'classic' } );

					init( true );

					expect( mockGetAppearance ).toHaveBeenCalledWith(
						false,
						false,
						false
					);
				} );
			} );

			it( 'maintains separate caches for classic and blocks', () => {
				jest.isolateModules( () => {
					const classicAppearance = { theme: 'classic' };
					const blocksAppearance = { theme: 'blocks' };
					const {
						initializeUPEAppearance: init,
					} = require( '../upe-appearance' );
					const {
						getAppearance: mockGetAppearance,
					} = require( '../../styles/upe' );
					mockGetAppearance.mockClear();
					mockGetAppearance
						.mockReturnValueOnce( classicAppearance )
						.mockReturnValueOnce( blocksAppearance );

					const classicResult = init( 'false' );
					const blocksResult = init( 'true' );

					expect( classicResult ).toBe( classicAppearance );
					expect( blocksResult ).toBe( blocksAppearance );
					expect( mockGetAppearance ).toHaveBeenCalledTimes( 2 );
				} );
			} );

			it( 'passes the editor flag through to getAppearance', () => {
				jest.isolateModules( () => {
					const {
						initializeUPEAppearance: init,
					} = require( '../upe-appearance' );
					const {
						getAppearance: mockGetAppearance,
					} = require( '../../styles/upe' );
					mockGetAppearance.mockReturnValue( { theme: 'stripe' } );
					mockGetAppearance.mockClear();

					init( 'true', false, true );

					expect( mockGetAppearance ).toHaveBeenCalledWith(
						true,
						false,
						true
					);
				} );
			} );

			it( 'maintains separate caches for editor and storefront blocks checkout', () => {
				jest.isolateModules( () => {
					const storefrontAppearance = { theme: 'night' };
					const editorAppearance = { theme: 'stripe' };
					const {
						initializeUPEAppearance: init,
					} = require( '../upe-appearance' );
					const {
						getAppearance: mockGetAppearance,
					} = require( '../../styles/upe' );
					mockGetAppearance.mockClear();
					mockGetAppearance
						.mockReturnValueOnce( storefrontAppearance )
						.mockReturnValueOnce( editorAppearance );

					const storefrontResult = init( 'true', false, false );
					const editorResult = init( 'true', false, true );
					// Subsequent calls hit each location's cache.
					init( 'true', false, false );
					init( 'true', false, true );

					expect( storefrontResult ).toBe( storefrontAppearance );
					expect( editorResult ).toBe( editorAppearance );
					expect( mockGetAppearance ).toHaveBeenCalledTimes( 2 );
				} );
			} );
		} );

		describe( 'server appearance takes priority over cache', () => {
			it( 'returns server appearance even after cache is populated', () => {
				jest.isolateModules( () => {
					const {
						initializeUPEAppearance: init,
					} = require( '../upe-appearance' );
					const {
						getAppearance: mockGetAppearance,
					} = require( '../../styles/upe' );
					mockGetAppearance.mockReturnValue( { theme: 'computed' } );

					// Populate the cache first.
					init( 'false' );

					// Then configure a server appearance.
					const serverAppearance = { theme: 'server-override' };
					global.wc_stripe_upe_params = {
						appearance: serverAppearance,
					};

					const result = init( 'false' );

					expect( result ).toBe( serverAppearance );
				} );
			} );
		} );
	} );

	describe( 'getHiddenBillingFields', () => {
		it( 'should always set address line2 to "never" so Stripe never renders it', () => {
			// Address line 2 disabled in WooCommerce.
			expect( getHiddenBillingFields( [] ).address.line2 ).toBe(
				'never'
			);

			// Address line 2 enabled in WooCommerce.
			expect(
				getHiddenBillingFields( [ 'billing_address_2' ] ).address.line2
			).toBe( 'never' );
		} );

		it( 'should set enabled fields to "never" and disabled fields to "auto"', () => {
			const result = getHiddenBillingFields( [
				'billing_first_name',
				'billing_email',
				'billing_country',
				'billing_address_1',
			] );

			expect( result.name ).toBe( 'never' );
			expect( result.email ).toBe( 'never' );
			expect( result.address.country ).toBe( 'never' );
			expect( result.address.line1 ).toBe( 'never' );

			// Not enabled, so Stripe is allowed to collect them.
			expect( result.address.city ).toBe( 'auto' );
			expect( result.address.state ).toBe( 'auto' );
			expect( result.address.postalCode ).toBe( 'auto' );
		} );

		it( 'should always keep phone as "auto"', () => {
			expect( getHiddenBillingFields( [] ).phone ).toBe( 'auto' );
			expect( getHiddenBillingFields( [ 'billing_phone' ] ).phone ).toBe(
				'auto'
			);
		} );
	} );
} );
