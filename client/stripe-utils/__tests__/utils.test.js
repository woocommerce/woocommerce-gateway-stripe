import { getSetting } from '@woocommerce/settings';
import {
	getFontSizeBase,
	getDefaultValues,
	getBillingDetailsForDeferredFlow,
	getHiddenBillingFields,
	getStripeServerData,
	showErrorCheckout,
	getExcludedPaymentMethodTypesForBillingCountry,
	getAdaptivePricingSavedTokenPaymentMethod,
} from '../utils';
import { initializeUPEAppearance } from '../upe-appearance';
import { getAppearance } from '../../styles/upe';
import { dispatch } from '@wordpress/data';

jest.mock( '../../styles/upe', () => ( {
	getAppearance: jest.fn(),
	getExpandedOptimizedCheckoutRules: jest.fn( ( rules ) => rules ),
} ) );

jest.mock( '@woocommerce/settings', () => ( {
	getSetting: jest.fn(),
} ) );

jest.mock( '@wordpress/data', () => ( {
	...jest.requireActual( '@wordpress/data' ),
	dispatch: jest.fn(),
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

	describe( 'getStripeServerData', () => {
		const globalValues = global.wc_stripe_upe_params;

		afterEach( () => {
			global.wc_stripe_upe_params = globalValues;
			getSetting.mockReset();
		} );

		it( 'returns the UPE params global when present', () => {
			global.wc_stripe_upe_params = { key: 'pk_test_123' };

			expect( getStripeServerData() ).toEqual( { key: 'pk_test_123' } );
		} );

		it( 'falls back to the Blocks stripe_data setting', () => {
			global.wc_stripe_upe_params = undefined;
			getSetting.mockReturnValue( { key: 'pk_test_blocks' } );

			expect( getStripeServerData() ).toEqual( {
				key: 'pk_test_blocks',
			} );
		} );

		it( 'returns null when no data is localized', () => {
			global.wc_stripe_upe_params = undefined;
			getSetting.mockReturnValue( null );

			expect( () => getStripeServerData() ).not.toThrow();
			expect( getStripeServerData() ).toBeNull();
		} );
	} );
} );

describe( 'showErrorCheckout', () => {
	let container;
	const originalJQuery = global.jQuery;
	const originalWcSettings = global.wcSettings;
	const originalWc = global.wc;

	beforeEach( () => {
		container = {
			length: 1,
			find: jest.fn().mockReturnThis(),
			remove: jest.fn().mockReturnThis(),
			prepend: jest.fn().mockReturnThis(),
		};

		const jQueryMock = jest.fn( ( selector ) => {
			if ( selector === '.woocommerce-notices-wrapper' ) {
				return { first: () => container };
			}
			if ( selector === '.woocommerce-MyAccount-content' ) {
				return { length: 0 };
			}
			if ( selector === 'form.checkout' ) {
				return { find: () => ( { length: 0 } ) };
			}
			return { trigger: jest.fn().mockReturnThis(), each: jest.fn() };
		} );
		jQueryMock.scroll_to_notices = jest.fn();
		global.jQuery = jQueryMock;

		// A classic-shortcode page never mounts the Blocks checkout StoreNotice helper.
		delete global.wc;
	} );

	afterEach( () => {
		global.jQuery = originalJQuery;
		global.wcSettings = originalWcSettings;
		global.wc = originalWc;
		dispatch.mockReset();
	} );

	it( 'uses the WC Blocks notices store when it is registered', () => {
		const createErrorNotice = jest.fn();
		dispatch.mockReturnValue( { createErrorNotice } );
		global.wcSettings = { wcBlocksConfig: { foo: true } };

		showErrorCheckout( 'Your card was declined.' );

		expect( createErrorNotice ).toHaveBeenCalledWith(
			'Your card was declined.',
			{ context: 'wc/checkout/payments' }
		);
		// The classic notice markup must not also be prepended.
		expect( container.prepend ).not.toHaveBeenCalled();
	} );

	// Regression: on a woocommerce/classic-shortcode page wcBlocksConfig is truthy but the
	// core/notices store isn't registered, so dispatch() returns null. The error must not be
	// silently dropped via an uncaught TypeError.
	it( 'falls back to the classic notice when core/notices is not registered', () => {
		dispatch.mockReturnValue( null );
		global.wcSettings = { wcBlocksConfig: { foo: true } };

		expect( () =>
			showErrorCheckout( 'Your card was declined.' )
		).not.toThrow();

		expect( container.prepend ).toHaveBeenCalledWith(
			expect.stringContaining( 'woocommerce-error' )
		);
	} );

	it( 'renders the classic notice when not in a block context', () => {
		dispatch.mockReturnValue( null );
		global.wcSettings = { wcBlocksConfig: false };

		showErrorCheckout( 'Your card was declined.' );

		expect( container.prepend ).toHaveBeenCalledWith(
			expect.stringContaining( 'Your card was declined.' )
		);
	} );

	describe( 'getExcludedPaymentMethodTypesForBillingCountry', () => {
		const globalValues = global.wc_stripe_upe_params;

		afterEach( () => {
			global.wc_stripe_upe_params = globalValues;
		} );

		const setServerData = (
			countriesByMethod,
			excludedPaymentMethodTypes
		) => {
			global.wc_stripe_upe_params = {
				excludedPaymentMethodTypes,
				paymentMethodsConfig: {
					card: { countriesByMethod },
				},
			};
		};

		const setCountryExcludedSeed = (
			countryExcludedPaymentMethodTypes
		) => {
			global.wc_stripe_upe_params.countryExcludedPaymentMethodTypes =
				countryExcludedPaymentMethodTypes;
		};

		it( 'excludes a country-restricted method when the billing country is unsupported', () => {
			setServerData( { ideal: [ 'NL' ] }, [ 'amazon_pay' ] );

			expect(
				getExcludedPaymentMethodTypesForBillingCountry( 'US' )
			).toEqual( expect.arrayContaining( [ 'amazon_pay', 'ideal' ] ) );
		} );

		it( 'keeps a country-restricted method when the billing country is supported', () => {
			setServerData( { ideal: [ 'NL' ] }, [ 'amazon_pay' ] );

			expect(
				getExcludedPaymentMethodTypesForBillingCountry( 'NL' )
			).not.toContain( 'ideal' );
		} );

		it( 'excludes country-restricted methods when the billing country is unknown', () => {
			setServerData( { ideal: [ 'NL' ] }, [ 'amazon_pay' ] );

			expect(
				getExcludedPaymentMethodTypesForBillingCountry( '' )
			).toContain( 'ideal' );
		} );

		it( 're-surfaces a server-seeded exclusion when the billing country becomes supported', () => {
			// The server seeds exclusions for the page-load country (e.g. US);
			// switching to a supported country must drop the stale exclusion.
			setServerData( { ideal: [ 'NL' ] }, [ 'amazon_pay', 'ideal' ] );
			setCountryExcludedSeed( [ 'ideal' ] );

			const excluded =
				getExcludedPaymentMethodTypesForBillingCountry( 'NL' );

			expect( excluded ).not.toContain( 'ideal' );
			expect( excluded ).toContain( 'amazon_pay' );
		} );

		it( 'preserves seed entries that are not country-derived, even when country-governed', () => {
			// A third party excluded Klarna via `wc_stripe_upe_params` for a
			// non-country reason: it is absent from the country-derived seed,
			// so the recompute must not drop it for a supported country.
			setServerData( { klarna: [ 'US', 'NL' ] }, [
				'amazon_pay',
				'klarna',
			] );
			setCountryExcludedSeed( [] );

			expect(
				getExcludedPaymentMethodTypesForBillingCountry( 'NL' )
			).toContain( 'klarna' );
		} );

		it( 'matches billing countries case-insensitively', () => {
			setServerData( { ideal: [ 'NL' ] }, [ 'amazon_pay' ] );

			expect(
				getExcludedPaymentMethodTypesForBillingCountry( 'nl' )
			).not.toContain( 'ideal' );
		} );

		it( 'keeps Amazon Pay excluded even when its country list allows the billing country', () => {
			setServerData( { amazon_pay: [ 'US' ] }, [ 'amazon_pay' ] );

			expect(
				getExcludedPaymentMethodTypesForBillingCountry( 'US' )
			).toContain( 'amazon_pay' );
		} );

		it( 'preserves the server-provided exclusions and de-duplicates', () => {
			setServerData( { ideal: [ 'NL' ] }, [ 'amazon_pay', 'ideal' ] );

			const excluded =
				getExcludedPaymentMethodTypesForBillingCountry( 'US' );

			expect(
				excluded.filter( ( type ) => type === 'ideal' )
			).toHaveLength( 1 );
			expect( excluded ).toContain( 'amazon_pay' );
		} );

		it( 'falls back to the Amazon Pay exclusion when no country map is present', () => {
			global.wc_stripe_upe_params = {};

			expect(
				getExcludedPaymentMethodTypesForBillingCountry( 'US' )
			).toEqual( [ 'amazon_pay' ] );
		} );
	} );

	describe( 'getAdaptivePricingSavedTokenPaymentMethod', () => {
		const globalValues = global.wc_stripe_upe_params;

		const renderTokenRadios = ( checkedValue ) => {
			document.body.innerHTML = `
				<input id="wc-stripe-payment-token-1" name="wc-stripe-payment-token" value="12" type="radio" ${
					checkedValue === '12' ? 'checked' : ''
				} />
				<input id="wc-stripe-payment-token-2" name="wc-stripe-payment-token" value="34" type="radio" ${
					checkedValue === '34' ? 'checked' : ''
				} />
				<input id="wc-stripe-payment-token-new" name="wc-stripe-payment-token" value="new" type="radio" ${
					checkedValue === 'new' ? 'checked' : ''
				} />
			`;
		};

		beforeEach( () => {
			global.wc_stripe_upe_params = {
				adaptivePricingSavedTokens: { 12: 'pm_saved_card_12' },
			};
		} );

		afterEach( () => {
			global.wc_stripe_upe_params = globalValues;
			document.body.innerHTML = '';
		} );

		it( 'returns the PaymentMethod id for a mapped (card) token', () => {
			renderTokenRadios( '12' );

			expect( getAdaptivePricingSavedTokenPaymentMethod( 'card' ) ).toBe(
				'pm_saved_card_12'
			);
		} );

		it( 'returns null for a token the server left out of the map', () => {
			renderTokenRadios( '34' );

			expect(
				getAdaptivePricingSavedTokenPaymentMethod( 'card' )
			).toBeNull();
		} );

		it( 'returns null when "Use a new payment method" is selected', () => {
			renderTokenRadios( 'new' );

			expect(
				getAdaptivePricingSavedTokenPaymentMethod( 'card' )
			).toBeNull();
		} );

		it( 'returns null when no map was provided (guest or AP off)', () => {
			global.wc_stripe_upe_params = {};
			renderTokenRadios( '12' );

			expect(
				getAdaptivePricingSavedTokenPaymentMethod( 'card' )
			).toBeNull();
		} );
	} );
} );
