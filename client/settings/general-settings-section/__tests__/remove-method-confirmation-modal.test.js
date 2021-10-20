import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import RemoveMethodConfirmationModal from '../remove-method-confirmation-modal';

describe( 'RemoveMethodConfirmationModal', () => {
	const handleCloseMock = jest.fn();
	const handleRemoveMock = jest.fn();

	it( 'should render the information', () => {
		render(
			<RemoveMethodConfirmationModal
				method="giropay"
				onClose={ handleCloseMock }
				onConfirm={ handleRemoveMock }
			/>
		);

		expect(
			screen.queryByRole( 'heading', {
				name: 'Remove giropay from checkout',
			} )
		).toBeInTheDocument();
	} );

	it( 'should call onClose when the action is cancelled', () => {
		render(
			<RemoveMethodConfirmationModal
				method="giropay"
				onClose={ handleCloseMock }
				onConfirm={ handleRemoveMock }
			/>
		);

		expect( handleCloseMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByRole( 'button', { name: 'Cancel' } ) );

		expect( handleCloseMock ).toHaveBeenCalled();
	} );

	it( 'should call onConfirm when the action is confirmed', () => {
		render(
			<RemoveMethodConfirmationModal
				method="giropay"
				onClose={ handleCloseMock }
				onConfirm={ handleRemoveMock }
			/>
		);

		expect( handleRemoveMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByRole( 'button', { name: 'Remove' } ) );

		expect( handleRemoveMock ).toHaveBeenCalled();
	} );
} );
