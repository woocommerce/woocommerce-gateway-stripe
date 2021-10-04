import React from 'react';
import { screen, render } from '@testing-library/react';
import SettingsSection from '..';

describe( 'SettingsSection', () => {
	it( 'should render its children', () => {
		render(
			<SettingsSection>
				<span>Children mock</span>
			</SettingsSection>
		);

		expect( screen.queryByText( 'Children mock' ) ).toBeInTheDocument();
	} );

	it( 'should accept a Description component', () => {
		render(
			<SettingsSection Description={ () => 'Description mock' }>
				<span>Children mock</span>
			</SettingsSection>
		);

		expect( screen.queryByText( 'Children mock' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Description mock' ) ).toBeInTheDocument();
	} );
} );
