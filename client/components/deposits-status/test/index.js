/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DepositsEnabled from '../';

describe( 'depositsEnabled', () => {
	test( 'renders disabled/unknown status', () => {
		renderdepositsEnabled( false, 20 );
		const disabledText = screen.queryByText( /disabled/i );
		expect( disabledText ).toBeInTheDocument();
	} );

	test( 'renders enabled status', () => {
		renderdepositsEnabled( true, 20 );
		const enabledText = screen.queryByText( /enabled/i );
		expect( enabledText ).toBeInTheDocument();
	} );

	function renderdepositsEnabled( depositsEnabled, iconSize ) {
		return render(
			<DepositsEnabled
				depositsEnabled={ depositsEnabled }
				iconSize={ iconSize }
			/>
		);
	}
} );
