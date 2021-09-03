/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../../wizard/task/context';
import AddPaymentMethodsTask from '../add-payment-methods-task';

describe( 'AddPaymentMethodsTask', () => {
	it( 'should proceed to step 3 by clicking "Add payment methods" button', async () => {
		const setCompletedMock = jest.fn();

		render(
			<WizardTaskContext.Provider
				value={ { setCompleted: setCompletedMock } }
			>
				<AddPaymentMethodsTask />
			</WizardTaskContext.Provider>
		);

		expect( setCompletedMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByText( 'Add payment methods' ) );

		expect( setCompletedMock ).toHaveBeenCalledWith(
			true,
			'setup-complete'
		);
	} );

	it( 'should have "Credit card/debit card" check as default payment method', async () => {
		const setCompletedMock = jest.fn();
		render(
			<WizardTaskContext.Provider
				value={ { setCompleted: setCompletedMock } }
			>
				<AddPaymentMethodsTask />
			</WizardTaskContext.Provider>
		);

		expect(
			screen.getByRole( 'checkbox', { name: 'Credit card / debit card' } )
		).toBeChecked();
	} );

	it( 'should click the payment method text and check its checkbox', async () => {
		const paymentMethodNames = [
			'giropay',
			'Sofort',
			'Direct debit payment',
		];

		const setCompletedMock = jest.fn();
		render(
			<WizardTaskContext.Provider
				value={ { setCompleted: setCompletedMock } }
			>
				<AddPaymentMethodsTask />
			</WizardTaskContext.Provider>
		);

		paymentMethodNames.forEach( function ( paymentMethodName ) {
			userEvent.click( screen.getByLabelText( paymentMethodName ) );
		} );

		paymentMethodNames.forEach( function ( paymentMethodName ) {
			expect(
				screen.getByRole( 'checkbox', { name: paymentMethodName } )
			).toBeChecked();
		} );
	} );
} );
