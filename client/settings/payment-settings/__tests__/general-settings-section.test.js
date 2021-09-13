/**
 * External dependencies
 */
import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import GeneralSettingsSection from '../general-settings-section';

describe( 'GeneralSettingsSection', () => {
	it( 'should enable stripe when stripe checkbox is clicked', () => {
		render( <GeneralSettingsSection /> );

		expect( screen.getByLabelText( 'Enable Stripe' ) ).not.toBeChecked();
		expect( screen.getByLabelText( 'Enable test mode' ) ).not.toBeChecked();

		userEvent.click( screen.getByLabelText( 'Enable Stripe' ) );

		expect( screen.getByLabelText( 'Enable Stripe' ) ).toBeChecked();
		expect( screen.getByLabelText( 'Enable test mode' ) ).not.toBeChecked();

		userEvent.click( screen.getByLabelText( 'Enable test mode' ) );

		expect( screen.getByLabelText( 'Enable Stripe' ) ).toBeChecked();
		expect( screen.getByLabelText( 'Enable test mode' ) ).toBeChecked();
	} );
} );
