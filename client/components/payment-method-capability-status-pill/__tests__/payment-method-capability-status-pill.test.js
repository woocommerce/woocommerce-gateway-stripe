import React from 'react';
import { screen, render } from '@testing-library/react';
import PaymentMethodCapabilityStatusPill from '..';
import { useGetCapabilities } from 'wcstripe/data/account';

jest.mock( 'wcstripe/data/account', () => ( {
	useGetCapabilities: jest.fn(),
} ) );

describe( 'PaymentMethodCapabilityStatusPill', () => {
	beforeEach( () => {
		useGetCapabilities.mockReturnValue( {} );
	} );

	it( 'should render for "pending" statuses', () => {
		useGetCapabilities.mockReturnValue( {
			ideal_payments: 'pending',
			card_payments: 'active',
		} );
		render(
			<PaymentMethodCapabilityStatusPill id="ideal" label="ideal" />
		);

		expect( screen.queryByText( 'Pending approval' ) ).toBeInTheDocument();
	} );

	it( 'should render for "inactive" statuses', () => {
		useGetCapabilities.mockReturnValue( {
			ideal_payments: 'inactive',
			card_payments: 'active',
		} );
		render(
			<PaymentMethodCapabilityStatusPill id="ideal" label="ideal" />
		);

		expect(
			screen.queryByText( 'Requires activation' )
		).toBeInTheDocument();
	} );

	it( 'should not render when the capability is "active"', () => {
		useGetCapabilities.mockReturnValue( {
			ideal_payments: 'active',
			card_payments: 'pending',
		} );
		const { container } = render(
			<PaymentMethodCapabilityStatusPill id="ideal" label="ideal" />
		);

		expect( container.firstChild ).toBeNull();
	} );

	it( 'should not render when the capability not present', () => {
		useGetCapabilities.mockReturnValue( { card_payments: 'active' } );
		const { container } = render(
			<PaymentMethodCapabilityStatusPill id="ideal" label="ideal" />
		);

		expect( container.firstChild ).toBeNull();
	} );
} );
