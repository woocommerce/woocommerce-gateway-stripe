import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import PaymentRequestSection from '..';

describe( 'PaymentRequestSection', () => {
	it( 'should enable express checkout locations when express checkout is enabled', () => {
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

	it( 'should disable express checkout locations when express checkout is enabled', () => {
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
} );
