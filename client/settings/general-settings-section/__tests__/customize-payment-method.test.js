import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import CustomizePaymentMethod from '../customize-payment-method';
import {
	useEnabledPaymentMethodIds,
	useCustomizePaymentMethodSettings,
} from 'wcstripe/data';
import {
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_EPS,
	PAYMENT_METHOD_GIROPAY,
} from 'wcstripe/stripe-utils/constants';

jest.mock( 'wcstripe/data', () => ( {
	useCustomizePaymentMethodSettings: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
} ) );

describe( 'CustomizePaymentMethod', () => {
	const customizePaymentMethodMock = jest
		.fn()
		.mockImplementation( () => Promise.resolve() );

	beforeEach( () => {
		useCustomizePaymentMethodSettings.mockReturnValue( {
			individualPaymentMethodSettings: {
				eps: {
					name: 'EPS',
					description: 'Pay with EPS',
				},
				giropay: {
					name: 'Giropay',
					description: 'Pay with Giropay',
				},
				boleto: {
					name: 'Boleto',
					description: 'Pay with Boleto',
					expiration: 10,
				},
			},
			isCustomizing: false,
			customizePaymentMethod: customizePaymentMethodMock,
		} );
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_CARD, PAYMENT_METHOD_EPS, PAYMENT_METHOD_GIROPAY ],
			jest.fn(),
		] );
	} );

	it( 'should render the title and description', () => {
		render(
			<CustomizePaymentMethod method="giropay" onClose={ jest.fn() } />
		);

		expect( screen.getByLabelText( 'Name' ) ).toHaveValue( 'Giropay' );
		expect( screen.getByLabelText( 'Description' ) ).toHaveValue(
			'Pay with Giropay'
		);
	} );

	it( 'should render the expiration field if it is present for the method', () => {
		render(
			<CustomizePaymentMethod method="boleto" onClose={ jest.fn() } />
		);

		expect( screen.getByLabelText( 'Name' ) ).toHaveValue( 'Boleto' );
		expect( screen.getByLabelText( 'Description' ) ).toHaveValue(
			'Pay with Boleto'
		);
		expect( screen.getByLabelText( 'Expiration' ) ).toHaveValue( '10' );
	} );

	it( 'should call onClose when cancel button is clicked', () => {
		const handleCloseMock = jest.fn();
		render(
			<CustomizePaymentMethod
				method="giropay"
				onClose={ handleCloseMock }
			/>
		);

		expect( handleCloseMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByRole( 'button', { name: 'Cancel' } ) );

		expect( handleCloseMock ).toHaveBeenCalled();
	} );

	it( 'should save data when save changes button is clicked', () => {
		render(
			<CustomizePaymentMethod method="giropay" onClose={ jest.fn() } />
		);

		expect( customizePaymentMethodMock ).not.toHaveBeenCalled();

		userEvent.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);

		expect( customizePaymentMethodMock ).toHaveBeenCalled();
	} );
} );
