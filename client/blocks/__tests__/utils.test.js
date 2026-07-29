import { getSetting } from '@woocommerce/settings';
import { render } from '@testing-library/react';
import {
	extractOrderAttributionData,
	getStripeElementOptions,
	shouldSetupOffSessionPayment,
} from 'wcstripe/blocks/utils';
import { isLinkEnabled } from 'wcstripe/stripe-utils';

jest.mock( '@woocommerce/settings', () => ( {
	getSetting: jest.fn(),
} ) );

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
		// Matches the debounce wait used by populateOrderAttributionInputs.
		const RETRY_DELAY = 1000;

		let populateOrderAttributionInputs;

		beforeEach( () => {
			jest.useFakeTimers();
			delete window.wc_order_attribution;

			// The retry is debounced at module scope, so load a fresh copy of the
			// module per test to avoid leaking a pending retry between tests.
			jest.resetModules();
			( {
				populateOrderAttributionInputs,
			} = require( 'wcstripe/blocks/utils' ) );
		} );

		afterEach( () => {
			jest.clearAllTimers();
			jest.useRealTimers();
			delete window.wc_order_attribution;
		} );

		test.each( [ [ true ], [ false ] ] )(
			'forwards allowTracking=%s immediately when the order attribution script is ready',
			( allowTracking ) => {
				window.wc_order_attribution = {
					params: { allowTracking },
					setOrderTracking: jest.fn(),
				};

				populateOrderAttributionInputs();

				expect(
					window.wc_order_attribution.setOrderTracking
				).toHaveBeenCalledWith( allowTracking );
			}
		);

		test( 'does not schedule a retry when tracking was set immediately', () => {
			window.wc_order_attribution = {
				params: { allowTracking: true },
				setOrderTracking: jest.fn(),
			};

			populateOrderAttributionInputs();

			expect( jest.getTimerCount() ).toBe( 0 );

			jest.runAllTimers();

			expect(
				window.wc_order_attribution.setOrderTracking
			).toHaveBeenCalledTimes( 1 );
		} );

		test( 'retries once setOrderTracking becomes available', () => {
			window.wc_order_attribution = { params: { allowTracking: true } };

			populateOrderAttributionInputs();

			window.wc_order_attribution.setOrderTracking = jest.fn();

			expect(
				window.wc_order_attribution.setOrderTracking
			).not.toHaveBeenCalled();

			jest.advanceTimersByTime( RETRY_DELAY - 1 );

			expect(
				window.wc_order_attribution.setOrderTracking
			).not.toHaveBeenCalled();

			jest.advanceTimersByTime( 1 );

			expect(
				window.wc_order_attribution.setOrderTracking
			).toHaveBeenCalledWith( true );
		} );

		test( 'retries once the order attribution global becomes available', () => {
			populateOrderAttributionInputs();

			window.wc_order_attribution = {
				params: { allowTracking: false },
				setOrderTracking: jest.fn(),
			};

			jest.runAllTimers();

			expect(
				window.wc_order_attribution.setOrderTracking
			).toHaveBeenCalledWith( false );
		} );

		test( 'coalesces repeated calls into a single retry', () => {
			window.wc_order_attribution = { params: { allowTracking: true } };

			populateOrderAttributionInputs();
			jest.advanceTimersByTime( RETRY_DELAY / 2 );
			populateOrderAttributionInputs();

			window.wc_order_attribution.setOrderTracking = jest.fn();

			jest.runAllTimers();

			expect(
				window.wc_order_attribution.setOrderTracking
			).toHaveBeenCalledTimes( 1 );
		} );

		test( 'does not throw when the order attribution script never loads', () => {
			expect( () => {
				populateOrderAttributionInputs();
				jest.runAllTimers();
			} ).not.toThrow();

			expect( jest.getTimerCount() ).toBe( 0 );
		} );
	} );

	describe( 'getStripeElementOptions', () => {
		let mockGetSetting;

		beforeEach( () => {
			mockGetSetting = getSetting;
			mockGetSetting.mockClear();
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
				expect( options.layout.radios ).toBe( 'never' );
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
				expect( options.layout.radios ).toBe( 'never' );
				expect( options.layout.spacedAccordionItems ).toBe( false );
			} );
		} );
	} );

	describe( 'shouldSetupOffSessionPayment', () => {
		let mockGetSetting;

		beforeEach( () => {
			mockGetSetting = getSetting;
			mockGetSetting.mockReturnValue( {} );
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
