import React from 'react';
import { render, within, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PaymentMethodCheckbox from '..';

jest.mock(
	'wcstripe/components/payment-method-capability-status-pill',
	() => () => null
);

describe( 'PaymentMethodCheckbox', () => {
	it( 'triggers the onChange when clicking the checkbox', () => {
		const handleChange = jest.fn();

		const upeMethods = [
			[ 'bancontact', true ],
			[ 'giropay', false ],
			[ 'ideal', false ],
			[ 'p24', false ],
			[ 'sepa_debit', false ],
			[ 'sofort', false ],
		];

		render(
			<ul>
				{ upeMethods.map( ( [ id, checked ] ) => (
					<PaymentMethodCheckbox
						key={ id }
						onChange={ handleChange }
						checked={ checked }
						id={ id }
					/>
				) ) }
			</ul>
		);

		const paymentMethods = screen.getAllByRole( 'listitem' );
		const bancontact = within( paymentMethods[ 0 ] );
		const giropay = within( paymentMethods[ 1 ] );
		const ideal = within( paymentMethods[ 2 ] );
		const p24 = within( paymentMethods[ 3 ] );
		const sepa = within( paymentMethods[ 4 ] );
		const sofort = within( paymentMethods[ 5 ] );

		expect( bancontact.getByRole( 'checkbox' ) ).toBeChecked();
		expect( giropay.getByRole( 'checkbox' ) ).not.toBeChecked();
		expect( ideal.getByRole( 'checkbox' ) ).not.toBeChecked();
		expect( p24.getByRole( 'checkbox' ) ).not.toBeChecked();
		expect( sepa.getByRole( 'checkbox' ) ).not.toBeChecked();
		expect( sofort.getByRole( 'checkbox' ) ).not.toBeChecked();

		userEvent.click( bancontact.getByRole( 'checkbox' ) );
		userEvent.click( giropay.getByRole( 'checkbox' ) );
		userEvent.click( ideal.getByRole( 'checkbox' ) );
		userEvent.click( p24.getByRole( 'checkbox' ) );
		userEvent.click( sepa.getByRole( 'checkbox' ) );
		userEvent.click( sofort.getByRole( 'checkbox' ) );

		expect( handleChange ).toHaveBeenCalledTimes( upeMethods.length );

		expect( handleChange ).toHaveBeenNthCalledWith(
			1,
			'bancontact',
			false
		);
		expect( handleChange ).toHaveBeenNthCalledWith( 2, 'giropay', true );
		expect( handleChange ).toHaveBeenNthCalledWith( 3, 'ideal', true );
		expect( handleChange ).toHaveBeenNthCalledWith( 4, 'p24', true );
		expect( handleChange ).toHaveBeenNthCalledWith( 5, 'sepa_debit', true );
		expect( handleChange ).toHaveBeenNthCalledWith( 6, 'sofort', true );
	} );
} );
