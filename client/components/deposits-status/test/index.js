/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DepositsStatus from '../';

describe( 'DepositsStatus', () => {
	test( 'renders disabled status', () => {
		renderDepositsStatus( 'disabled', 20 );
		const disabledText = screen.queryByText( /disabled/i );
		expect( disabledText ).toBeInTheDocument();
	} );

	test( 'renders daily status', () => {
		renderDepositsStatus( 'daily', 20 );
		const dailyText = screen.queryByText( /daily/i );
		expect( dailyText ).toBeInTheDocument();
	} );

	test( 'renders weekly status', () => {
		renderDepositsStatus( 'weekly', 20 );
		const weeklyText = screen.queryByText( /weekly/i );
		expect( weeklyText ).toBeInTheDocument();
	} );

	test( 'renders monthly status', () => {
		renderDepositsStatus( 'monthly', 20 );
		const monthlyText = screen.queryByText( /monthly/i );
		expect( monthlyText ).toBeInTheDocument();
	} );

	test( 'renders manual status', () => {
		renderDepositsStatus( 'manual', 20 );
		const manualText = screen.queryByText( /temporarily suspended/i );
		expect( manualText ).toBeInTheDocument();
	} );

	test( 'renders unknown status', () => {
		renderDepositsStatus( 'foobar', 20 );
		const unknownText = screen.queryByText( /unknown/i );
		expect( unknownText ).toBeInTheDocument();
	} );

	function renderDepositsStatus( depositsStatus, iconSize ) {
		return render(
			<DepositsStatus
				depositsStatus={ depositsStatus }
				iconSize={ iconSize }
			/>
		);
	}
} );
