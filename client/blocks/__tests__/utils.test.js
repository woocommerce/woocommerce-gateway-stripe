import { render } from '@testing-library/react';
import {
	extractOrderAttributionData,
	getStripeElementOptions,
	populateOrderAttributionInputs,
	shouldSetupOffSessionPayment,
} from 'wcstripe/blocks/utils';
import { isLinkEnabled } from 'wcstripe/stripe-utils';

jest.mock( 'wcstripe/stripe-utils', () => ( {
	isLinkEnabled: jest.fn().mockReturnValue( false ),
} ) );

describe( 'Blocks Utils', () => {
	describe( 'extractOrderAttributionData', () => {
		it( 'order attribution wrapper not found', () => {
			const data = extractOrderAttributionData();
			expect( data ).toStrictEqual( {} );
		} );

		it( 'order attribution wrapper exists', () => {
			render(
				<wc-order-attribution-inputs>
					<input name="foo" defaultValue="bar" />
					<input name="baz" defaultValue="qux" />
				</wc-order-attribution-inputs>
			);

			const data = extractOrderAttributionData();
			expect( data ).toStrictEqual( {
				foo: 'bar',
				baz: 'qux',
			} );
		} );
	} );

	describe( 'populateOrderAttributionInputs', () => {
		test( 'order attribution global present', () => {
			global.wc_order_attribution = {
				params: {
					allowTracking: true,
				},
				setOrderTracking: jest.fn(),
			};

			populateOrderAttributionInputs();

			expect(
				global.wc_order_attribution.setOrderTracking
			).toHaveBeenCalledWith( true );
		} );
	} );

	describe( 'getStripeElementOptions', () => {
		let mockGetSetting;

		beforeEach( () => {
			mockGetSetting = jest.fn();
			global.wc = {
				wcSettings: {
					getSetting: mockGetSetting,
				},
			};
			isLinkEnabled.mockReturnValue( false );
		} );

		describe( 'when Optimized Checkout is disabled', () => {
			beforeEach( () => {
				mockGetSetting.mockReturnValue( {
					shouldShowOptimizedCheckout: false,
				} );
			} );

			it( 'defaults layout to tabs', () => {
				const options = getStripeElementOptions();
				expect( options.layout ).toStrictEqual( { type: 'tabs' } );
			} );

			it( 'preserves wallets options', () => {
				const options = getStripeElementOptions();
				expect( options.wallets ).toStrictEqual( {
					applePay: 'never',
					googlePay: 'never',
				} );
			} );

			it( 'preserves billing details fields', () => {
				const options = getStripeElementOptions();
				expect( options.fields.billingDetails.name ).toBe( 'never' );
				expect( options.fields.billingDetails.email ).toBe( 'never' );
			} );
		} );

		describe( 'when Optimized Checkout is enabled', () => {
			it( 'uses accordion layout with default OC settings', () => {
				mockGetSetting.mockReturnValue( {
					shouldShowOptimizedCheckout: true,
					OCLayout: undefined,
				} );

				const options = getStripeElementOptions();

				expect( options.layout.type ).toBe( 'accordion' );
				expect( options.layout.radios ).toBe( false );
				expect( options.layout.spacedAccordionItems ).toBe( false );
			} );

			it( 'uses custom OCLayout when explicitly set - tabs', () => {
				mockGetSetting.mockReturnValue( {
					shouldShowOptimizedCheckout: true,
					OCLayout: 'tabs',
				} );

				const options = getStripeElementOptions();

				expect( options.layout.type ).toBe( 'tabs' );
				expect( options.layout.radios ).toBeUndefined();
				expect( options.layout.spacedAccordionItems ).toBeUndefined();
			} );

			it( 'uses custom OCLayout when explicitly set - accordion', () => {
				mockGetSetting.mockReturnValue( {
					shouldShowOptimizedCheckout: true,
					OCLayout: 'accordion',
				} );

				const options = getStripeElementOptions();

				expect( options.layout.type ).toBe( 'accordion' );
				expect( options.layout.radios ).toBe( false );
				expect( options.layout.spacedAccordionItems ).toBe( false );
			} );
		} );
	} );

	describe( 'shouldSetupOffSessionPayment', () => {
		let mockGetSetting;

		beforeEach( () => {
			mockGetSetting = jest.fn().mockReturnValue( {} );
			global.wc = {
				wcSettings: {
					getSetting: mockGetSetting,
				},
			};
		} );

		test( 'cart has auto renewal subscription', () => {
			mockGetSetting.mockReturnValue( {
				cartContainsSubscription: true,
				subscriptionManualRenewalEnabled: false,
			} );
			expect( shouldSetupOffSessionPayment( false, false ) ).toBeTruthy();
		} );

		test( 'showSaveOption is true', () => {
			expect( shouldSetupOffSessionPayment( true, true ) ).toBeTruthy();
		} );

		test( 'cart does not have auto renewal subscription and showSaveOption is false', () => {
			expect( shouldSetupOffSessionPayment( false, false ) ).toBeFalsy();
		} );
	} );
} );
