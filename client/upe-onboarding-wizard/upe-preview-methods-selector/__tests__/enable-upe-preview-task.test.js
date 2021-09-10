/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../../wizard/task/context';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';

import EnableUpePreviewTask from '../enable-upe-preview-task';

describe( 'EnableUpePreviewTask', () => {
	it( 'should enable the UPE flag when clicking the "Enable" button', async () => {
		const setCompletedMock = jest.fn();
		const setIsUpeEnabledMock = jest.fn().mockResolvedValue( true );

		render(
			<UpeToggleContext.Provider
				value={ {
					isUpeEnabled: false,
					setIsUpeEnabled: setIsUpeEnabledMock,
				} }
			>
				<WizardTaskContext.Provider
					value={ { setCompleted: setCompletedMock } }
				>
					<EnableUpePreviewTask />
				</WizardTaskContext.Provider>
			</UpeToggleContext.Provider>
		);

		expect( setCompletedMock ).not.toHaveBeenCalled();
		expect( setIsUpeEnabledMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByText( 'Enable' ) );

		await waitFor( () =>
			expect( setIsUpeEnabledMock ).toHaveBeenCalledWith( true )
		);
		expect( setCompletedMock ).toHaveBeenCalledWith(
			true,
			'add-payment-methods'
		);
	} );
} );
