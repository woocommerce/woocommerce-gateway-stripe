import React from 'react';
import { screen, render } from '@testing-library/react';

import SaveSettingsSection from '..';

describe( 'SaveSettingsSection', () => {
	it( 'should render the save button', () => {
		render( <SaveSettingsSection /> );

		expect( screen.queryByText( 'Save changes' ) ).toBeInTheDocument();
	} );
} );
