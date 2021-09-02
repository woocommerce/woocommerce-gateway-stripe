/** @format */
/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentsStatus from '../';

describe( 'PaymentsStatus', () => {
	test( 'renders enabled status', () => {
		renderPaymentsStatus( 1, 20 );
		const enabledText = screen.getByText( /enabled/i );
		expect( enabledText ).toBeInTheDocument();
	} );

	test( 'renders disabled status', () => {
		renderPaymentsStatus( 0, 20 );
		const disabledText = screen.getByText( /disabled/i );
		expect( disabledText ).toBeInTheDocument();
	} );

	function renderPaymentsStatus( paymentsEnabled, iconSize ) {
		return render(
			<PaymentsStatus
				paymentsEnabled={ paymentsEnabled }
				iconSize={ iconSize }
			/>
		);
	}
} );
