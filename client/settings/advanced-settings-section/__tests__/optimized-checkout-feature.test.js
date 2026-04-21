import { render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import userEvent from '@testing-library/user-event';
import OptimizedCheckoutFeature from 'wcstripe/settings/advanced-settings-section/optimized-checkout-feature';
import {
	useIsOCEnabled,
	useIsAdaptivePricingEnabled,
	useManualCapture,
	useOCLayout,
} from 'wcstripe/data';

jest.useFakeTimers();

jest.mock( 'wcstripe/data', () => ( {
	useIsOCEnabled: jest.fn(),
	useIsAdaptivePricingEnabled: jest.fn(),
	useManualCapture: jest.fn(),
	useOCLayout: jest.fn(),
} ) );

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn().mockReturnValue( {} ),
} ) );

const ADAPTIVE_PRICING_CHECKBOX_LABEL =
	'Let customers pay in their local currency with Adaptive Pricing.';

describe( 'Optimized Checkout Element feature setting', () => {
	beforeEach( () => {
		global.wc_stripe_settings_params = { is_cs_available: false };

		useIsOCEnabled.mockReturnValue( [ false, jest.fn() ] );
		useIsAdaptivePricingEnabled.mockReturnValue( [ false, jest.fn() ] );
		useManualCapture.mockReturnValue( [ false, jest.fn() ] );
		useOCLayout.mockReturnValue( [ 'accordion', jest.fn() ] );
	} );

	it( 'should render', () => {
		render( <OptimizedCheckoutFeature /> );

		expect(
			screen.queryByText(
				'Enable Optimized Checkout Suite (recommended)'
			)
		).toBeInTheDocument();
	} );

	it( 'should disable the OC setting on click', async () => {
		const setIsOCEnabledMock = jest.fn();
		useIsOCEnabled.mockReturnValue( [ true, setIsOCEnabledMock ] );

		render( <OptimizedCheckoutFeature /> );

		const OCCheckbox = screen.getByTestId(
			'optimized-checkout-element-checkbox'
		);

		await userEvent.click( OCCheckbox );

		await waitFor( () => {
			expect( setIsOCEnabledMock ).toHaveBeenCalled();
		} );
	} );

	it( 'triggers the hook when changing the layout setting', async () => {
		useIsOCEnabled.mockReturnValue( [ true, jest.fn() ] );

		const setLayoutMock = jest.fn();
		useOCLayout.mockReturnValue( [ 'accordion', setLayoutMock ] );

		render( <OptimizedCheckoutFeature /> );

		expect( setLayoutMock ).not.toHaveBeenCalled();

		await userEvent.click( screen.getByLabelText( 'Tabs' ) );

		await waitFor( async () => {
			expect( setLayoutMock ).toHaveBeenCalledWith( 'tabs' );
		} );
	} );
} );

describe( 'Adaptive Pricing feature', () => {
	const visibleSetup = () => {
		global.wc_stripe_settings_params = { is_cs_available: true };
		useIsOCEnabled.mockReturnValue( [ true, jest.fn() ] );
		useManualCapture.mockReturnValue( [ false, jest.fn() ] );
		useIsAdaptivePricingEnabled.mockReturnValue( [ false, jest.fn() ] );
		useOCLayout.mockReturnValue( [ 'accordion', jest.fn() ] );
	};

	beforeEach( () => {
		global.wc_stripe_settings_params = { is_cs_available: false };
		useIsOCEnabled.mockReturnValue( [ false, jest.fn() ] );
		useIsAdaptivePricingEnabled.mockReturnValue( [ false, jest.fn() ] );
		useManualCapture.mockReturnValue( [ false, jest.fn() ] );
		useOCLayout.mockReturnValue( [ 'accordion', jest.fn() ] );
	} );

	it( 'shows the Adaptive Pricing checkbox when OC is on, manual capture is off, and checkout sessions are available', () => {
		visibleSetup();

		render( <OptimizedCheckoutFeature /> );

		expect(
			screen.getByLabelText( ADAPTIVE_PRICING_CHECKBOX_LABEL )
		).toBeInTheDocument();
	} );

	it( 'hides the Adaptive Pricing checkbox when Optimized Checkout is off', () => {
		global.wc_stripe_settings_params = { is_cs_available: true };
		useIsOCEnabled.mockReturnValue( [ false, jest.fn() ] );
		useManualCapture.mockReturnValue( [ false, jest.fn() ] );

		render( <OptimizedCheckoutFeature /> );

		expect(
			screen.queryByLabelText( ADAPTIVE_PRICING_CHECKBOX_LABEL )
		).not.toBeInTheDocument();
	} );

	it( 'hides the Adaptive Pricing checkbox when manual capture is enabled', () => {
		global.wc_stripe_settings_params = { is_cs_available: true };
		useIsOCEnabled.mockReturnValue( [ true, jest.fn() ] );
		useManualCapture.mockReturnValue( [ true, jest.fn() ] );

		render( <OptimizedCheckoutFeature /> );

		expect(
			screen.queryByLabelText( ADAPTIVE_PRICING_CHECKBOX_LABEL )
		).not.toBeInTheDocument();
	} );

	it( 'hides the Adaptive Pricing checkbox when checkout sessions are unavailable', () => {
		global.wc_stripe_settings_params = { is_cs_available: false };
		useIsOCEnabled.mockReturnValue( [ true, jest.fn() ] );
		useManualCapture.mockReturnValue( [ false, jest.fn() ] );

		render( <OptimizedCheckoutFeature /> );

		expect(
			screen.queryByLabelText( ADAPTIVE_PRICING_CHECKBOX_LABEL )
		).not.toBeInTheDocument();
	} );

	it( 'shows and hides the Adaptive Pricing checkbox when mocked flags are toggled via rerender', () => {
		visibleSetup();

		const { rerender } = render( <OptimizedCheckoutFeature /> );
		expect(
			screen.getByLabelText( ADAPTIVE_PRICING_CHECKBOX_LABEL )
		).toBeInTheDocument();

		useIsOCEnabled.mockReturnValue( [ false, jest.fn() ] );
		rerender( <OptimizedCheckoutFeature /> );
		expect(
			screen.queryByLabelText( ADAPTIVE_PRICING_CHECKBOX_LABEL )
		).not.toBeInTheDocument();

		global.wc_stripe_settings_params = { is_cs_available: true };
		useIsOCEnabled.mockReturnValue( [ true, jest.fn() ] );
		useManualCapture.mockReturnValue( [ true, jest.fn() ] );
		rerender( <OptimizedCheckoutFeature /> );
		expect(
			screen.queryByLabelText( ADAPTIVE_PRICING_CHECKBOX_LABEL )
		).not.toBeInTheDocument();

		useManualCapture.mockReturnValue( [ false, jest.fn() ] );
		rerender( <OptimizedCheckoutFeature /> );
		expect(
			screen.getByLabelText( ADAPTIVE_PRICING_CHECKBOX_LABEL )
		).toBeInTheDocument();

		global.wc_stripe_settings_params = { is_cs_available: false };
		rerender( <OptimizedCheckoutFeature /> );
		expect(
			screen.queryByLabelText( ADAPTIVE_PRICING_CHECKBOX_LABEL )
		).not.toBeInTheDocument();

		global.wc_stripe_settings_params = { is_cs_available: true };
		rerender( <OptimizedCheckoutFeature /> );
		expect(
			screen.getByLabelText( ADAPTIVE_PRICING_CHECKBOX_LABEL )
		).toBeInTheDocument();
	} );

	it( 'triggers the hook when changing the Adaptive Pricing setting', async () => {
		global.wc_stripe_settings_params = { is_cs_available: true };

		useIsOCEnabled.mockReturnValue( [ true, jest.fn() ] );

		const setAdaptivePricingEnabledMock = jest.fn();
		useIsAdaptivePricingEnabled.mockReturnValue( [
			false,
			setAdaptivePricingEnabledMock,
		] );

		render( <OptimizedCheckoutFeature /> );

		expect( setAdaptivePricingEnabledMock ).not.toHaveBeenCalled();

		await userEvent.click(
			screen.getByLabelText( ADAPTIVE_PRICING_CHECKBOX_LABEL )
		);

		await waitFor( async () => {
			expect( setAdaptivePricingEnabledMock ).toHaveBeenCalledWith(
				true
			);
		} );
	} );
} );
