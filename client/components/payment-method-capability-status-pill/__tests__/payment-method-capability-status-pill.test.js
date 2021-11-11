import React from 'react';
import { screen, render } from '@testing-library/react';
import PaymentMethodCapabilityStatusPill from '..';
import { useGetCapabilities } from 'wcstripe/data/account';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';

jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn().mockReturnValue( {} ),
	useGetCapabilities: jest.fn(),
} ) );

describe( 'PaymentMethodCapabilityStatusPill', () => {
	beforeEach( () => {
		useGetCapabilities.mockReturnValue( {} );
	} );

	it( 'should render the "Pending activation" text', () => {
		useGetCapabilities.mockReturnValue( {
			giropay_payments: 'pending',
			card_payments: 'active',
		} );
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<PaymentMethodCapabilityStatusPill
					id="giropay"
					label="giropay"
				/>
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByText( 'Pending activation' )
		).toBeInTheDocument();
	} );

	it( 'should not render when the capability is "active"', () => {
		useGetCapabilities.mockReturnValue( {
			giropay_payments: 'active',
			card_payments: 'pending',
		} );
		const { container } = render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<PaymentMethodCapabilityStatusPill
					id="giropay"
					label="giropay"
				/>
			</UpeToggleContext.Provider>
		);

		expect( container.firstChild ).toBeNull();
	} );

	it( 'should not render when the capability not present', () => {
		useGetCapabilities.mockReturnValue( { card_payments: 'active' } );
		const { container } = render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<PaymentMethodCapabilityStatusPill
					id="giropay"
					label="giropay"
				/>
			</UpeToggleContext.Provider>
		);

		expect( container.firstChild ).toBeNull();
	} );
} );
