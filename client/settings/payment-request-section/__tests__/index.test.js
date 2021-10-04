import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PaymentRequestSection from '..';
import {
	usePaymentRequestEnabledSettings,
	usePaymentRequestLocations,
} from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	usePaymentRequestEnabledSettings: jest.fn(),
	usePaymentRequestLocations: jest.fn(),
} ) );

const getMockPaymentRequestLocations = (
	isCheckoutEnabled,
	isProductPageEnabled,
	isCartEnabled,
	updatePaymentRequestLocationsHandler
) => [
	[
		isCheckoutEnabled && 'checkout',
		isProductPageEnabled && 'product',
		isCartEnabled && 'cart',
	].filter( Boolean ),
	updatePaymentRequestLocationsHandler,
];

describe( 'PaymentRequestSection', () => {
	beforeEach( () => {
		usePaymentRequestEnabledSettings.mockReturnValue( [
			false,
			jest.fn(),
		] );
		usePaymentRequestLocations.mockReturnValue(
			getMockPaymentRequestLocations( true, true, true, jest.fn() )
		);
	} );

	it( 'should enable express checkout locations when express checkout is enabled', () => {
		usePaymentRequestEnabledSettings.mockReturnValue( [ true, jest.fn() ] );

		render( <PaymentRequestSection /> );

		const [
			expressCheckoutCheckbox,
			checkoutCheckbox,
			productPageCheckbox,
			cartCheckbox,
		] = screen.getAllByRole( 'checkbox' );

		expect( expressCheckoutCheckbox ).toBeChecked();
		expect( checkoutCheckbox ).not.toBeDisabled();
		expect( checkoutCheckbox ).toBeChecked();
		expect( productPageCheckbox ).not.toBeDisabled();
		expect( productPageCheckbox ).toBeChecked();
		expect( cartCheckbox ).not.toBeDisabled();
		expect( cartCheckbox ).toBeChecked();
	} );

	it( 'should disable express checkout locations when express checkout is disabled', () => {
		usePaymentRequestEnabledSettings.mockReturnValue( [
			false,
			jest.fn(),
		] );

		render( <PaymentRequestSection /> );

		const [
			expressCheckoutCheckbox,
			checkoutCheckbox,
			productPageCheckbox,
			cartCheckbox,
		] = screen.getAllByRole( 'checkbox' );

		userEvent.click( expressCheckoutCheckbox );

		expect( expressCheckoutCheckbox ).not.toBeChecked();
		expect( checkoutCheckbox ).toBeDisabled();
		expect( checkoutCheckbox ).not.toBeChecked();
		expect( productPageCheckbox ).toBeDisabled();
		expect( productPageCheckbox ).not.toBeChecked();
		expect( cartCheckbox ).toBeDisabled();
		expect( cartCheckbox ).not.toBeChecked();
	} );

	it( 'should dispatch enabled status update if express checkout is being toggled', () => {
		const updateIsPaymentRequestEnabledHandler = jest.fn();
		usePaymentRequestEnabledSettings.mockReturnValue( [
			false,
			updateIsPaymentRequestEnabledHandler,
		] );

		render( <PaymentRequestSection /> );

		userEvent.click( screen.getByText( 'Enable express checkouts' ) );

		expect( updateIsPaymentRequestEnabledHandler ).toHaveBeenCalledWith(
			true
		);
	} );

	it( 'should trigger an action to save the checked locations when un-checking the location checkboxes', () => {
		const updatePaymentRequestLocationsHandler = jest.fn();
		usePaymentRequestEnabledSettings.mockReturnValue( [ true, jest.fn() ] );
		usePaymentRequestLocations.mockReturnValue(
			getMockPaymentRequestLocations(
				true,
				true,
				true,
				updatePaymentRequestLocationsHandler
			)
		);

		render( <PaymentRequestSection /> );

		// Uncheck each checkbox, and verify them what kind of action should have been called
		userEvent.click( screen.getByText( 'Product page' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout', 'cart' ] );

		userEvent.click( screen.getByText( 'Checkout' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'product', 'cart' ] );

		userEvent.click( screen.getByText( 'Cart' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout', 'product' ] );
	} );

	it( 'should trigger an action to save the checked locations when checking the location checkboxes', () => {
		const updatePaymentRequestLocationsHandler = jest.fn();
		usePaymentRequestEnabledSettings.mockReturnValue( [ true, jest.fn() ] );
		usePaymentRequestLocations.mockReturnValue(
			getMockPaymentRequestLocations(
				false,
				false,
				false,
				updatePaymentRequestLocationsHandler
			)
		);

		render( <PaymentRequestSection /> );

		userEvent.click( screen.getByText( 'Cart' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'cart' ] );

		userEvent.click( screen.getByText( 'Product page' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'product' ] );

		userEvent.click( screen.getByText( 'Checkout' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout' ] );
	} );
} );
