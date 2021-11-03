import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import SavePaymentGatewaySection from '..';
import { usePaymentGateway } from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	usePaymentGateway: jest.fn().mockReturnValue( {} ),
} ) );

describe( 'SavePaymentGatewaySection', () => {
	it( 'should render the save button', () => {
		render( <SavePaymentGatewaySection /> );

		expect( screen.queryByText( 'Save changes' ) ).toBeInTheDocument();
	} );

	it( 'disables the button when loading data', () => {
		usePaymentGateway.mockReturnValue( {
			isLoading: true,
		} );

		render( <SavePaymentGatewaySection /> );

		expect( screen.getByText( 'Save changes' ) ).toBeDisabled();
	} );

	it( 'disables the button when saving data', () => {
		usePaymentGateway.mockReturnValue( {
			isSaving: true,
		} );

		render( <SavePaymentGatewaySection /> );

		expect( screen.getByText( 'Save changes' ) ).toBeDisabled();
	} );

	it( 'calls `savePaymentGateway` when the button is clicked', () => {
		const savePaymentGatewayMock = jest.fn();
		usePaymentGateway.mockReturnValue( {
			isSaving: false,
			isLoading: false,
			savePaymentGateway: savePaymentGatewayMock,
		} );

		render( <SavePaymentGatewaySection /> );

		const saveChangesButton = screen.getByText( 'Save changes' );

		expect( savePaymentGatewayMock ).not.toHaveBeenCalled();
		expect( saveChangesButton ).not.toBeDisabled();

		userEvent.click( saveChangesButton );

		expect( savePaymentGatewayMock ).toHaveBeenCalled();
	} );
} );
