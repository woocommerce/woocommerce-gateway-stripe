/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DepositsStatus from '..';

describe( 'depositsEnabled', () => {
	test( 'renders disabled status', () => {
		renderDepositsStatus( false );
		const disabledText = screen.queryByText( /disabled/i );
		expect( disabledText ).toBeInTheDocument();
	} );

	test( 'renders enabled status', () => {
		renderDepositsStatus( true );
		const enabledText = screen.queryByText( /enabled/i );
		expect( enabledText ).toBeInTheDocument();
	} );

	function renderDepositsStatus( depositsEnabled ) {
		return render( <DepositsStatus isEnabled={ depositsEnabled } /> );
	}
} );
