/**
 * External dependencies
 */
import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import PaymentSettingsPanel from '..';

describe( 'PaymentSettingsPanel', () => {
	describe( 'General', () => {
		it( 'should enable stripe when stripe checkbox is clicked', () => {
			render( <PaymentSettingsPanel /> );

			const [ enableStripe, enableTestMode ] = screen.getAllByRole(
				'checkbox'
			);

			expect( enableStripe ).not.toBeChecked();
			expect( enableTestMode ).not.toBeChecked();

			userEvent.click( enableStripe );

			expect( enableStripe ).toBeChecked();
			expect( enableTestMode ).not.toBeChecked();

			userEvent.click( enableTestMode );

			expect( enableStripe ).toBeChecked();
			expect( enableTestMode ).toBeChecked();
		} );
	} );
} );
