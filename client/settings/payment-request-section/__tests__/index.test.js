import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import PaymentRequestSection from '..';
import {
	usePaymentRequestEnabledSettings
} from '../../../data';

jest.mock( '../../../data', () => ( {
	usePaymentRequestEnabledSettings: jest.fn(),
} ) );

const getMockPaymentRequestEnabledSettings = (
	isEnabled,
	updateIsPaymentRequestEnabledHandler
) => [ isEnabled, updateIsPaymentRequestEnabledHandler ];

describe( 'PaymentRequestSection', () => {
	beforeEach( () => {
		usePaymentRequestEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings( false, jest.fn() )
		);
	} );

	it( 'should enable express checkout locations when express checkout is enabled', () => {
		usePaymentRequestEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings( true, jest.fn() )
		);

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
		usePaymentRequestEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings( false, jest.fn() )
		);

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

	it( 'should dispatch enabled status update if express checkout is being toggled', async () => {
		const updateIsPaymentRequestEnabledHandler = jest.fn();
		usePaymentRequestEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings(
				false,
				updateIsPaymentRequestEnabledHandler
			)
		);

		render( <PaymentRequestSection /> );

		userEvent.click( screen.getByText( 'Enable express checkouts' ) );

		expect( updateIsPaymentRequestEnabledHandler ).toHaveBeenCalledWith(
			true
		);
	} );
} );
