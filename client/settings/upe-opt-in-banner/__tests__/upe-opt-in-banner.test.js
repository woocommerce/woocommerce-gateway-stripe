import React from 'react';
import { screen, render } from '@testing-library/react';
import UpeOptInBanner from '..';

describe( 'UpeOptInBanner', () => {
	it( 'should render', () => {
		render(
			<UpeOptInBanner
				title="Title mock"
				description="Description mock"
				Image={ () => null }
			/>
		);

		expect( screen.queryByText( 'Title mock' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Description mock' ) ).toBeInTheDocument();
		expect(
			screen.queryByText( 'Enable in your store' )
		).toBeInTheDocument();
		expect( screen.queryByText( 'Learn more' ) ).toBeInTheDocument();
	} );
} );
