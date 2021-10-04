import React from 'react';
import { render, screen } from '@testing-library/react';
import SectionStatus from '..';

describe( 'SectionStatus', () => {
	test( 'renders enabled status', () => {
		renderSectionStatus( true );
		const enabledText = screen.getByText( /enabled/i );
		expect( enabledText ).toBeInTheDocument();
	} );

	test( 'renders disabled status', () => {
		renderSectionStatus( false );
		const disabledText = screen.getByText( /disabled/i );
		expect( disabledText ).toBeInTheDocument();
	} );

	function renderSectionStatus( isEnabled ) {
		return render( <SectionStatus isEnabled={ isEnabled } /> );
	}
} );
