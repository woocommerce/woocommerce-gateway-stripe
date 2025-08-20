import { render, screen } from '@testing-library/react';
import React from 'react';
import userEvent from '@testing-library/user-event';
import OptimizedCheckoutFeature from 'wcstripe/settings/advanced-settings-section/optimized-checkout-feature';
import { useIsOCEnabled, useIsUpeEnabled, useOCLayout } from 'wcstripe/data';

jest.useFakeTimers();

jest.mock( 'wcstripe/data', () => ( {
	useIsUpeEnabled: jest.fn(),
	useIsOCEnabled: jest.fn(),
	useOCLayout: jest.fn(),
} ) );

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn().mockReturnValue( {} ),
} ) );

describe( 'Optimized Checkout Element feature setting', () => {
	beforeEach( () => {
		useIsUpeEnabled.mockReturnValue( [ true, jest.fn() ] );
		useIsOCEnabled.mockReturnValue( [ false, jest.fn() ] );
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

	it( 'should disable the OC setting on click', () => {
		const setIsOCEnabledMock = jest.fn();
		useIsOCEnabled.mockReturnValue( [ true, setIsOCEnabledMock ] );

		render( <OptimizedCheckoutFeature /> );

		const OCCheckbox = screen.getByTestId(
			'optimized-checkout-element-checkbox'
		);

		userEvent.click( OCCheckbox );

		expect( setIsOCEnabledMock ).toHaveBeenCalled();
	} );

	it( 'should be disabled when UPE is disabled', () => {
		useIsUpeEnabled.mockReturnValue( [ false, jest.fn() ] );

		render( <OptimizedCheckoutFeature /> );

		const checkbox = screen.getByTestId(
			'optimized-checkout-element-checkbox'
		);

		userEvent.click( checkbox );

		jest.runAllTimers();

		expect( checkbox ).toBeDisabled();
		expect( checkbox ).not.toBeChecked();
	} );

	it( 'layout setting should be available when OC is enabled', () => {
		useIsOCEnabled.mockReturnValue( [ true, jest.fn() ] );

		render( <OptimizedCheckoutFeature /> );

		const label = screen.getByText( 'Layout' );
		expect( label ).toBeInTheDocument();

		const help = screen.getByText(
			'Choose between a vertical accordion layout and a horizontal tabs layout to display payment methods.'
		);
		expect( help ).toBeInTheDocument();
	} );

	it( 'triggers the hook when changing the layout setting', () => {
		useIsOCEnabled.mockReturnValue( [ true, jest.fn() ] );

		const setLayoutMock = jest.fn();
		useOCLayout.mockReturnValue( [ 'accordion', setLayoutMock ] );

		render( <OptimizedCheckoutFeature /> );

		expect( setLayoutMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByLabelText( 'Tabs' ) );
		expect( setLayoutMock ).toHaveBeenCalledWith( 'tabs' );
	} );
} );
