/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentsStatus from '..';

describe( 'PaymentsStatus', () => {
	test( 'renders enabled status', () => {
		renderPaymentsStatus( true );
		const enabledText = screen.getByText( /enabled/i );
		expect( enabledText ).toBeInTheDocument();
	} );

	test( 'renders disabled status', () => {
		renderPaymentsStatus( false );
		const disabledText = screen.getByText( /disabled/i );
		expect( disabledText ).toBeInTheDocument();
	} );

	function renderPaymentsStatus( paymentsEnabled ) {
		return render( <PaymentsStatus isEnabled={ paymentsEnabled } /> );
	}
} );
