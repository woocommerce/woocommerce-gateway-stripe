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
import EnableUpePreviewTask from '../enable-upe-preview-task';

describe( 'EnableUpePreviewTask', () => {
	it( 'should enable the UPE flag when clicking the "Enable" button', async () => {
		const setCompletedMock = jest.fn();

		render(
			<WizardTaskContext.Provider
				value={ { setCompleted: setCompletedMock } }
			>
				<EnableUpePreviewTask />
			</WizardTaskContext.Provider>
		);

		expect( setCompletedMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByText( 'Enable' ) );

		expect( setCompletedMock ).toHaveBeenCalledWith(
			true,
			'add-payment-methods'
		);
	} );
} );
