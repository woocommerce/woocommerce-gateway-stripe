import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PaymentGatewaySection from '..';
import {
	useEnabledGateway,
	useGatewayName,
	useGatewayDescription,
} from '../hooks';

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn().mockReturnValue( { section: 'stripe_alipay' } ),
} ) );

jest.mock( '../../../data/account/hooks', () => ( {
	useAccount: jest.fn().mockReturnValue( { data: {} } ),
} ) );

jest.mock( '../../account-details/use-webhook-state-message', () => ( {
	__esModule: true,
	default: jest.fn().mockReturnValue( {
		message: 'webhook is working',
		requestStatus: '',
		refreshMessage: jest.fn(),
	} ),
} ) );

jest.mock( '../hooks', () => ( {
	useEnabledGateway: jest.fn(),
	useGatewayName: jest.fn(),
	useGatewayDescription: jest.fn(),
} ) );

describe( 'PaymentGatewaySection', () => {
	beforeEach( () => {
		useEnabledGateway.mockReturnValue( [ false ] );
		useGatewayName.mockReturnValue( [ 'Alipay' ] );
		useGatewayDescription.mockReturnValue( [
			'You will be redirected to Alipay',
		] );
	} );

	it( 'should render one checkbox and two inputs', () => {
		render( <PaymentGatewaySection /> );
		expect( screen.getAllByRole( 'checkbox' ).length ).toEqual( 1 );
		expect( screen.getAllByRole( 'textbox' ).length ).toEqual( 2 );
	} );

	it( 'should contain the webhook monitoring status', () => {
		render( <PaymentGatewaySection /> );
		expect(
			screen.queryByText( 'webhook is working' )
		).toBeInTheDocument();
	} );

	it( 'should be possible to enable/disable the payment gateway', () => {
		const enableGatewayMock = jest.fn();
		useEnabledGateway.mockReturnValue( [ false, enableGatewayMock ] );

		render( <PaymentGatewaySection /> );

		const enableCheckbox = screen.getByRole( 'checkbox', {
			name: /Enable Alipay/,
		} );

		expect( enableGatewayMock ).not.toHaveBeenCalled();
		expect( enableCheckbox ).not.toBeChecked();

		userEvent.click( enableCheckbox );

		expect( enableGatewayMock ).toHaveBeenCalledWith( true );
	} );

	it( 'should be possible to update the gateway name', () => {
		const setGatewayNameMock = jest.fn();
		useGatewayName.mockReturnValue( [ '', setGatewayNameMock ] );

		render( <PaymentGatewaySection /> );

		const nameInput = screen.getByRole( 'textbox', {
			name: /Name/,
		} );

		expect( setGatewayNameMock ).not.toHaveBeenCalled();
		expect( nameInput.value ).toEqual( '' );

		userEvent.type( nameInput, 'Buy with Alipay' ); // 15 characters

		expect( setGatewayNameMock ).toHaveBeenCalledTimes( 15 );
	} );

	it( 'should be possible to update the gateway description', () => {
		const setGatewayDescriptionMock = jest.fn();
		useGatewayDescription.mockReturnValue( [
			'',
			setGatewayDescriptionMock,
		] );

		render( <PaymentGatewaySection /> );

		const descriptionInput = screen.getByRole( 'textbox', {
			name: /Description/,
		} );

		expect( setGatewayDescriptionMock ).not.toHaveBeenCalled();
		expect( descriptionInput.value ).toEqual( '' );

		userEvent.type( descriptionInput, 'You will be redirected to Alipay' ); // 32 characters

		expect( setGatewayDescriptionMock ).toHaveBeenCalledTimes( 32 );
	} );
} );
