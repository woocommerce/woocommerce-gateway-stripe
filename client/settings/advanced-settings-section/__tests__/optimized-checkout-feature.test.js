import { render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import userEvent from '@testing-library/user-event';
import OptimizedCheckoutFeature from 'wcstripe/settings/advanced-settings-section/optimized-checkout-feature';
import {
	useIsOCEnabled,
	useIsAdaptivePricingEnabled,
	useOCLayout,
} from 'wcstripe/data';

jest.useFakeTimers();

jest.mock( 'wcstripe/data', () => ( {
	useIsOCEnabled: jest.fn(),
	useIsAdaptivePricingEnabled: jest.fn(),
	useOCLayout: jest.fn(),
} ) );

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn().mockReturnValue( {} ),
} ) );

describe( 'Optimized Checkout Element feature setting', () => {
	beforeEach( () => {
		global.wc_stripe_settings_params = { is_cs_available: false };

		useIsOCEnabled.mockReturnValue( [ false, jest.fn() ] );
		useIsAdaptivePricingEnabled.mockReturnValue( [ false, jest.fn() ] );
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

	it( 'Adaptive pricing and layout settings should be available when OC is enabled and checkout sessions is available', () => {
		global.wc_stripe_settings_params = { is_cs_available: true };

		useIsOCEnabled.mockReturnValue( [ true, jest.fn() ] );

		render( <OptimizedCheckoutFeature /> );

		// Layout settings.
		expect( screen.getByText( 'Layout' ) ).toBeInTheDocument();
		expect(
			screen.getByText(
				'Choose between a vertical accordion layout and a horizontal tabs layout to display payment methods.'
			)
		).toBeInTheDocument();

		// Adaptive pricing settings.
		expect(
			screen.getByText(
				'Let customers pay in their local currency with Adaptive Pricing.'
			)
		).toBeInTheDocument();
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
			screen.getByLabelText(
				'Let customers pay in their local currency with Adaptive Pricing.'
			)
		);

		await waitFor( async () => {
			expect( setAdaptivePricingEnabledMock ).toHaveBeenCalledWith(
				true
			);
		} );
	} );
} );
