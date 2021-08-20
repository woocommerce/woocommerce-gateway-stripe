/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import { UPESettingsTabPanel } from '../';

it( 'should render two tabs when mounted', () => {
	render( <UPESettingsTabPanel /> );
	expect(
		screen.getByRole( 'tab', { name: /Payment Methods/i } )
	).toBeInTheDocument();
	expect(
		screen.getByRole( 'tab', { name: /Settings/i } )
	).toBeInTheDocument();
} );

it( 'should change tabs when clicking on them', () => {
	render( <UPESettingsTabPanel /> );
	const settingsButton = screen.getByRole( 'tab', { name: /Settings/i } );
	const methodsButton = screen.getByRole( 'tab', {
		name: /Payment Methods/i,
	} );
	userEvent.click( settingsButton );
	expect( settingsButton ).toHaveClass( 'is-active' );
	userEvent.click( methodsButton );
	expect( methodsButton ).toHaveClass( 'is-active' );
} );
