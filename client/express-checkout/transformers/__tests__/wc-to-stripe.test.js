import {
	transformPrice,
	transformPriceWithMinorUnits,
	transformCartDataForDisplayItems,
	transformCartDataForShippingRates,
	transformLabeledDisplayItems,
} from '../wc-to-stripe';

global.wc_stripe_express_checkout_params = {};
global.wc_stripe_express_checkout_params.checkout = {};

jest.mock( '@woocommerce/blocks-checkout', () => {}, { virtual: true } );

describe( 'wc-to-stripe transformers', () => {
	describe( 'transformCartDataForDisplayItems', () => {
		it( 'transforms the cart items and their names, if they contain special characters', () => {
			expect(
				transformCartDataForDisplayItems( {
					items: [
						{
							key: '30e94626ff41df1be0572e19f249746f',
							id: 44,
							type: 'subscription',
							quantity: 1,
							name: 'Physical subscription',
							variation: [],
							prices: {
								price: '4500',
								regular_price: '5000',
								sale_price: '4500',
								price_range: null,
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
								raw_prices: {
									precision: 6,
									price: '45000000',
									regular_price: '50000000',
									sale_price: '45000000',
								},
							},
							totals: {
								line_subtotal: '4500',
								line_subtotal_tax: '0',
								line_total: '4500',
								line_total_tax: '0',
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
							},
							catalog_visibility: 'visible',
							extensions: {
								subscriptions: {
									billing_period: 'month',
									billing_interval: 1,
									subscription_length: 0,
									trial_length: 0,
									trial_period: 'day',
									is_resubscribe: false,
									switch_type: null,
									synchronization: null,
									sign_up_fees: '300',
									sign_up_fees_tax: '33',
								},
								addons: [],
							},
						},
						{
							key: '4cf7f86c98b84855e3d5811a5712b35d',
							id: 66,
							type: 'booking',
							quantity: 1,
							name: 'WC Bookings &#8211; Equipment Rental',
							variation: [],
							item_data: [
								{
									name: 'Booking Date',
									value: 'August 3, 2024',
									display: '',
								},
								{
									name: 'Qty (Sample person)',
									value: '1',
									display: '',
								},
								{
									name: 'Booking Type',
									value: 'Black folding chairs (Sample resource)',
									display: '',
								},
							],
							prices: {
								price: '150',
								regular_price: '150',
								sale_price: '150',
								price_range: null,
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
								raw_prices: {
									precision: 6,
									price: '1500000',
									regular_price: '1500000',
									sale_price: '1500000',
								},
							},
							totals: {
								line_subtotal: '150',
								line_subtotal_tax: '0',
								line_total: '150',
								line_total_tax: '0',
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
							},
							catalog_visibility: 'visible',
							extensions: {
								addons: [],
							},
						},
					],
					shipping_rates: [],
					totals: {},
				} )
			).toStrictEqual( [
				{ amount: 4500, name: 'Physical subscription' },
				{ amount: 150, name: 'WC Bookings – Equipment Rental' },
			] );
		} );

		it( 'transforms the tax amount when present', () => {
			expect(
				transformCartDataForDisplayItems( {
					items: [],
					shipping_rates: [],
					totals: {
						total_items: '0',
						total_items_tax: '545',
						total_fees: '0',
						total_fees_tax: '0',
						total_discount: '0',
						total_discount_tax: '0',
						total_shipping: '0',
						total_shipping_tax: '0',
						total_price: '545',
						total_tax: '545',
						tax_lines: [
							{
								name: 'CA-Tax-Rate',
								price: '545',
								rate: '11%',
							},
						],
						currency_code: 'USD',
						currency_symbol: '$',
						currency_minor_unit: 2,
						currency_decimal_separator: '.',
						currency_thousand_separator: ',',
						currency_prefix: '$',
						currency_suffix: '',
					},
				} )
			).toStrictEqual( [ { amount: 545, name: 'Tax' } ] );
		} );

		it( 'transforms the tax amount when not present', () => {
			expect(
				transformCartDataForDisplayItems( {
					items: [],
					shipping_rates: [],
					totals: {
						total_items: '0',
						total_items_tax: '0',
						total_fees: '0',
						total_fees_tax: '0',
						total_discount: '0',
						total_discount_tax: '0',
						total_shipping: '0',
						total_shipping_tax: '0',
						total_price: '0',
						total_tax: '0',
						tax_lines: [],
						currency_code: 'USD',
						currency_symbol: '$',
						currency_minor_unit: 2,
						currency_decimal_separator: '.',
						currency_thousand_separator: ',',
						currency_prefix: '$',
						currency_suffix: '',
					},
				} )
			).toStrictEqual( [] );
		} );

		it( 'does not return line items when there is a discrepancy with the totals', () => {
			expect(
				transformCartDataForDisplayItems( {
					items: [
						{
							key: '6fd9b4da889ae534ceae47561b939f24',
							id: 214,
							type: 'simple',
							quantity: 2,
							name: 'Deposit',
							variation: [],
							item_data: [
								{
									name: 'Payment Plan',
									value: 'Deposit 30',
									display: '',
								},
								{
									key: 'Payable In Total',
									value: '&#36;45.00 payable over 20 days',
								},
							],
							prices: {
								price: '4500',
								regular_price: '5000',
								sale_price: '4500',
								price_range: null,
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
								raw_prices: {
									precision: 6,
									price: '45000000',
									regular_price: '50000000',
									sale_price: '45000000',
								},
							},
							totals: {
								line_subtotal: '1350',
								line_subtotal_tax: 0,
								line_total: '4500',
								line_total_tax: '388',
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
							},
							catalog_visibility: 'visible',
							extensions: {
								'woocommerce-deposits': {
									is_deposit: true,
									has_payment_plan: true,
									plan_schedule: [
										{
											schedule_id: '2',
											schedule_index: '0',
											plan_id: '2',
											amount: '30',
											interval_amount: '0',
											interval_unit: '0',
										},
										{
											schedule_id: '3',
											schedule_index: '1',
											plan_id: '2',
											amount: '70',
											interval_amount: '20',
											interval_unit: 'day',
										},
									],
								},
								bundles: [],
							},
						},
					],
					shipping_rates: [],
					totals: {
						total_items: '0',
						total_items_tax: '0',
						total_fees: '0',
						total_fees_tax: '0',
						total_discount: '0',
						total_discount_tax: '0',
						total_shipping: '0',
						total_shipping_tax: '0',
						total_price: '0',
						total_tax: '0',
						tax_lines: [],
						currency_code: 'USD',
						currency_symbol: '$',
						currency_minor_unit: 2,
						currency_decimal_separator: '.',
						currency_thousand_separator: ',',
						currency_prefix: '$',
						currency_suffix: '',
					},
				} )
			).toStrictEqual( [] );
		} );
	} );

	describe( 'transformLabeledDisplayItems', () => {
		it( 'normalizes keyed discounts without changing unkeyed amounts', () => {
			expect(
				transformLabeledDisplayItems( [
					{ label: 'Subtotal', amount: 1000 },
					{
						key: 'total_discount',
						label: 'Discount',
						amount: 100,
					},
					{ label: 'Refund', amount: -50 },
				] )
			).toStrictEqual( [
				{ name: 'Subtotal', amount: 1000 },
				{ name: 'Discount', amount: -100 },
				{ name: 'Refund', amount: -50 },
			] );
		} );
	} );

	describe( 'transformPrice', () => {
		afterEach( () => {
			delete global.wc_stripe_express_checkout_params.checkout
				.currency_decimals;
		} );

		it( 'transforms the price', () => {
			expect( transformPrice( 180, { currency_minor_unit: 2 } ) ).toBe(
				180
			);
		} );

		it( 'transforms the price if the currency is configured with one decimal', () => {
			// with one decimal, `180` would mean `18.0`.
			// But since Stripe expects the price to be in cents, the return value should be `1800`
			expect( transformPrice( 180, { currency_minor_unit: 1 } ) ).toBe(
				1800
			);
		} );

		it( 'transforms the price if the currency is configured with two decimals', () => {
			// with two decimals, `1800` would mean `18.00`.
			// But since Stripe expects the price to be in cents, the return value should be `1800`
			expect( transformPrice( 1800, { currency_minor_unit: 2 } ) ).toBe(
				1800
			);
		} );

		it( 'transforms the price if the currency is a zero decimal currency (e.g.: Yen)', () => {
			global.wc_stripe_express_checkout_params.checkout.currency_decimals = 0;
			// with zero decimals, `18` would mean `18`.
			expect( transformPrice( 18, { currency_minor_unit: 0 } ) ).toBe(
				18
			);
		} );

		it( 'transforms the price if the currency a zero decimal currency (e.g.: Yen) but it is configured with one decimal', () => {
			global.wc_stripe_express_checkout_params.checkout.currency_decimals = 0;
			// with zero decimals, `18` would mean `18`.
			// But since Stripe expects the price to be in the minimum currency amount, the return value should be `18`
			expect( transformPrice( 180, { currency_minor_unit: 1 } ) ).toBe(
				18
			);
		} );
	} );

	describe( 'transformPriceWithMinorUnits', () => {
		afterEach( () => {
			delete global.wc_stripe_express_checkout_params.checkout
				.currency_decimals;
		} );

		const testCases = {
			'minor units of 3 with Woo default (2)': {
				price: 18000,
				minorUnits: 3,
				expected: 1800,
			},
			'minor units of 2 with Woo default (2)': {
				price: 180,
				minorUnits: 2,
				expected: 180,
			},
			'minor units of 1 with Woo default (2)': {
				price: 180,
				minorUnits: 1,
				expected: 1800,
			},
			'minor units of 0 with Woo default (2)': {
				price: 180,
				minorUnits: 0,
				expected: 18000,
			},
			'minor units of 3 with explicit currency decimals 2': {
				price: 1800,
				minorUnits: 3,
				expected: 180,
				currencyDecimals: 2,
			},
			'minor units of 2 with explicit currency decimals 2': {
				price: 1800,
				minorUnits: 2,
				expected: 1800,
				currencyDecimals: 2,
			},
			'minor units of 1 with explicit currency decimals 2': {
				price: 1800,
				minorUnits: 1,
				expected: 18000,
				currencyDecimals: 2,
			},
			'minor units of 0 with explicit currency decimals 2': {
				price: 180,
				minorUnits: 0,
				expected: 18000,
				currencyDecimals: 2,
			},
			'minor units of 3 with explicit currency decimals 1': {
				price: 18000,
				minorUnits: 3,
				expected: 180,
				currencyDecimals: 1,
			},
			'minor units of 2 with explicit currency decimals 1': {
				price: 1800,
				minorUnits: 2,
				expected: 180,
				currencyDecimals: 1,
			},
			'minor units of 1 with explicit currency decimals 1': {
				price: 1800,
				minorUnits: 1,
				expected: 1800,
				currencyDecimals: 1,
			},
			'minor units of 0 with explicit currency decimals 1': {
				price: 180,
				minorUnits: 0,
				expected: 1800,
				currencyDecimals: 1,
			},
			'minor units of 3 with explicit currency decimals 0': {
				price: 18000,
				minorUnits: 3,
				expected: 18,
				currencyDecimals: 0,
			},
			'minor units of 2 with explicit currency decimals 0': {
				price: 1800,
				minorUnits: 2,
				expected: 18,
				currencyDecimals: 0,
			},
			'minor units of 1 with explicit currency decimals 0': {
				price: 1800,
				minorUnits: 1,
				expected: 180,
				currencyDecimals: 0,
			},
			'minor units of 0 with explicit currency decimals 0': {
				price: 180,
				minorUnits: 0,
				expected: 180,
				currencyDecimals: 0,
			},
		};

		Object.entries( testCases ).forEach( ( [ description, testCase ] ) => {
			// eslint-disable-next-line jest/valid-title
			it( description, () => {
				if ( undefined !== testCase.currencyDecimals ) {
					global.wc_stripe_express_checkout_params.checkout.currency_decimals =
						testCase.currencyDecimals;
				}
				expect(
					transformPriceWithMinorUnits(
						testCase.price,
						testCase.minorUnits
					)
				).toBe( testCase.expected );
			} );
		} );
	} );

	describe( 'transformCartDataForShippingRates', () => {
		const makeRate = ( overrides = {} ) => ( {
			rate_id: 'flat_rate:1',
			name: 'Flat Rate',
			price: '500',
			taxes: '50',
			selected: false,
			currency_minor_unit: 2,
			meta_data: [],
			...overrides,
		} );

		beforeEach( () => {
			global.wc_stripe_express_checkout_params = {
				checkout: {
					display_prices_with_tax: false,
				},
			};
		} );

		afterEach( () => {
			global.wc_stripe_express_checkout_params = {};
		} );

		it( 'performs basic transformation of rate fields', () => {
			const cartData = {
				shipping_rates: [
					{
						shipping_rates: [
							makeRate( {
								rate_id: 'flat_rate:1',
								name: 'Flat Rate',
								price: '500',
								taxes: '0',
							} ),
						],
					},
				],
			};

			const result = transformCartDataForShippingRates( cartData );

			expect( result ).toHaveLength( 1 );
			expect( result[ 0 ] ).toMatchObject( {
				id: 'flat_rate:1',
				displayName: 'Flat Rate',
				amount: 500,
			} );
		} );

		it( 'uses price + taxes as amount when display_prices_with_tax is true', () => {
			global.wc_stripe_express_checkout_params.checkout.display_prices_with_tax = true;

			const cartData = {
				shipping_rates: [
					{
						shipping_rates: [
							makeRate( {
								price: '500',
								taxes: '50',
							} ),
						],
					},
				],
			};

			const result = transformCartDataForShippingRates( cartData );

			expect( result[ 0 ].amount ).toBe( 550 );
		} );

		it( 'uses only price as amount when display_prices_with_tax is false', () => {
			global.wc_stripe_express_checkout_params.checkout.display_prices_with_tax = false;

			const cartData = {
				shipping_rates: [
					{
						shipping_rates: [
							makeRate( {
								price: '500',
								taxes: '50',
							} ),
						],
					},
				],
			};

			const result = transformCartDataForShippingRates( cartData );

			expect( result[ 0 ].amount ).toBe( 500 );
		} );

		it( 'sorts selected rates first', () => {
			const cartData = {
				shipping_rates: [
					{
						shipping_rates: [
							makeRate( {
								rate_id: 'flat_rate:1',
								name: 'Flat Rate',
								selected: false,
							} ),
							makeRate( {
								rate_id: 'free_shipping:1',
								name: 'Free Shipping',
								selected: true,
							} ),
							makeRate( {
								rate_id: 'flat_rate:2',
								name: 'Express',
								selected: false,
							} ),
						],
					},
				],
			};

			const result = transformCartDataForShippingRates( cartData );

			expect( result[ 0 ].id ).toBe( 'free_shipping:1' );
			expect( result[ 1 ].id ).toBe( 'flat_rate:1' );
			expect( result[ 2 ].id ).toBe( 'flat_rate:2' );
		} );

		it( 'does not mutate the caller-provided shipping_rates array', () => {
			const originalRates = [
				makeRate( { rate_id: 'a', selected: false } ),
				makeRate( { rate_id: 'b', selected: true } ),
			];
			const cartData = {
				shipping_rates: [ { shipping_rates: originalRates } ],
			};

			transformCartDataForShippingRates( cartData );

			expect( originalRates[ 0 ].rate_id ).toBe( 'a' );
			expect( originalRates[ 1 ].rate_id ).toBe( 'b' );
		} );

		it( 'returns empty array when there are no shipping rates', () => {
			expect( transformCartDataForShippingRates( {} ) ).toStrictEqual(
				[]
			);
			expect(
				transformCartDataForShippingRates( {
					shipping_rates: [ { shipping_rates: [] } ],
				} )
			).toStrictEqual( [] );
		} );

		it( 'builds deliveryEstimate from pickup_address and pickup_details metadata', () => {
			const cartData = {
				shipping_rates: [
					{
						shipping_rates: [
							makeRate( {
								meta_data: [
									{
										key: 'pickup_address',
										value: '123 Main St',
									},
									{
										key: 'pickup_details',
										value: 'Ring doorbell',
									},
								],
							} ),
						],
					},
				],
			};

			const result = transformCartDataForShippingRates( cartData );

			expect( result[ 0 ].deliveryEstimate ).toBe(
				'123 Main St - Ring doorbell'
			);
		} );

		it( 'builds deliveryEstimate from only pickup_address when pickup_details is absent', () => {
			const cartData = {
				shipping_rates: [
					{
						shipping_rates: [
							makeRate( {
								meta_data: [
									{
										key: 'pickup_address',
										value: '123 Main St',
									},
								],
							} ),
						],
					},
				],
			};

			const result = transformCartDataForShippingRates( cartData );

			expect( result[ 0 ].deliveryEstimate ).toBe( '123 Main St' );
		} );

		it( 'returns empty deliveryEstimate string when no pickup metadata is present', () => {
			const cartData = {
				shipping_rates: [
					{
						shipping_rates: [ makeRate( { meta_data: [] } ) ],
					},
				],
			};

			const result = transformCartDataForShippingRates( cartData );

			expect( result[ 0 ].deliveryEstimate ).toBe( '' );
		} );

		it( 'handles missing meta_data without throwing', () => {
			const cartData = {
				shipping_rates: [
					{
						shipping_rates: [
							makeRate( { meta_data: undefined } ),
						],
					},
				],
			};

			expect( () =>
				transformCartDataForShippingRates( cartData )
			).not.toThrow();

			const result = transformCartDataForShippingRates( cartData );
			expect( result[ 0 ].deliveryEstimate ).toBe( '' );
		} );

		it( 'handles missing taxes as zero when display_prices_with_tax is true', () => {
			global.wc_stripe_express_checkout_params.checkout.display_prices_with_tax = true;

			const cartData = {
				shipping_rates: [
					{
						shipping_rates: [
							makeRate( { price: '500', taxes: undefined } ),
						],
					},
				],
			};

			const result = transformCartDataForShippingRates( cartData );

			expect( result[ 0 ].amount ).toBe( 500 );
		} );

		it( 'slices results to SHIPPING_RATES_UPPER_LIMIT_COUNT (9)', () => {
			const rates = Array.from( { length: 12 }, ( _, i ) =>
				makeRate( { rate_id: `flat_rate:${ i }`, name: `Rate ${ i }` } )
			);

			const cartData = {
				shipping_rates: [ { shipping_rates: rates } ],
			};

			const result = transformCartDataForShippingRates( cartData );

			expect( result ).toHaveLength( 9 );
		} );

		it( 'decodes HTML entities in rate names', () => {
			const cartData = {
				shipping_rates: [
					{
						shipping_rates: [
							makeRate( { name: 'Pickup &amp; Delivery' } ),
						],
					},
				],
			};

			const result = transformCartDataForShippingRates( cartData );

			expect( result[ 0 ].displayName ).toBe( 'Pickup & Delivery' );
		} );
	} );
} );
