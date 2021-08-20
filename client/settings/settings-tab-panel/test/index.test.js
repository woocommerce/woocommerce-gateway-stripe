/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * Internal dependencies
 */
import { UPESettingsTabPanel } from '../';

it( 'should render two tabs when mounting', () => {
	render( <UPESettingsTabPanel /> );
	expect(
		screen.getByRole( 'tab', { name: /Payment Methods/i } )
	).toBeInTheDocument();
	expect(
		screen.getByRole( 'tab', { name: /Settings/i } )
	).toBeInTheDocument();
} );
