/** @format */
/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AccountStatus from '..';

describe( 'AccountStatus', () => {
	const renderAccountStatus = ( accountStatus ) => {
		return render( <AccountStatus accountStatus={ accountStatus } /> );
	};

	test( 'renders enabled payments and deposits on account', () => {
		renderAccountStatus( {
			paymentsEnabled: true,
			depositsEnabled: true,
			accountLink: 'https://stripe.com/support',
		} );
		// @todo expect the description to not show up
		// @todo expect enabled to show up for payments
		const warningDescription = screen.queryByText(
			/Payments and deposits may be disabled for this account until missing business information is updated/i
		);
		expect( warningDescription ).not.toBeInTheDocument();
	} );

	test( 'renders disabled deposits and payments on account', () => {
		renderAccountStatus( {
			paymentsEnabled: false,
			depositsEnabled: false,
			accountLink: 'https://stripe.com/support',
		} );
		// @todo expect the description to show up
		const warningDescription = screen.getByText(
			/Payments and deposits may be disabled for this account until missing business information is updated/i
		);
		expect( warningDescription ).toBeInTheDocument();
	} );
} );
