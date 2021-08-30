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
import SetupComplete from '../setup-complete-task';
import WizardContext from '../../wizard/wrapper/context';

describe( 'SetupComplete', () => {
	const renderHelper = ( setCompletedMock ) => {
		if ( ! setCompletedMock ) {
			setCompletedMock = jest.fn();
		}
		render(
			<WizardContext.Provider
				value={ {
					completedTasks: {
						'add-payment-methods': { initialMethods: [ 'card' ] },
					},
				} }
			>
				<WizardTaskContext.Provider
					value={ { setCompleted: setCompletedMock } }
				>
					<SetupComplete />
				</WizardTaskContext.Provider>
			</WizardContext.Provider>
		);
	};

	it( 'Clicking "Add payment methods" should redirect back to settings page', async () => {
		const setCompletedMock = jest.fn();
		renderHelper( setCompletedMock );
		expect( setCompletedMock ).not.toHaveBeenCalled();

		expect( screen.getByText( 'Go to payments settings' ).href ).toContain(
			'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments'
		);
	} );

	it( 'should show the 1 extra payment is selected compared to step 1', async () => {
		renderHelper();
		expect(
			screen.getByText(
				'Setup complete! 1 new payment method is now live on your store!'
			)
		).toBeInTheDocument();
	} );

	it( 'should have 2 icons displaying after setup is completed', async () => {
		renderHelper();
		expect( screen.getAllByRole( 'img' ).length ).toBe( 2 );
	} );
} );
