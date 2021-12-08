import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PaymentGatewaySection from '..';
import {
	useEnabledPaymentGateway,
	usePaymentGatewayName,
	usePaymentGatewayDescription,
} from '../../../data/payment-gateway/hooks';
import { useGetCapabilities } from 'wcstripe/data/account';

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn().mockReturnValue( { section: 'stripe_ideal' } ),
} ) );

jest.mock( '../../../data/account/hooks', () => ( {
	useAccount: jest.fn().mockReturnValue( { data: {} } ),
	useGetCapabilities: jest.fn().mockReturnValue( {} ),
} ) );

jest.mock( '../../account-details/use-webhook-state-message', () => ( {
	__esModule: true,
	default: jest.fn().mockReturnValue( {
		message: 'webhook is working',
		requestStatus: '',
		refreshMessage: jest.fn(),
	} ),
} ) );

jest.mock( '../../../data/payment-gateway/hooks', () => ( {
	useEnabledPaymentGateway: jest.fn(),
	usePaymentGatewayName: jest.fn(),
	usePaymentGatewayDescription: jest.fn(),
} ) );

jest.mock( '../../loadable-payment-gateway-section', () => ( { children } ) =>
	children
);

describe( 'PaymentGatewaySection', () => {
	beforeEach( () => {
		useEnabledPaymentGateway.mockReturnValue( [ false ] );
		usePaymentGatewayName.mockReturnValue( [ 'iDEAL' ] );
		usePaymentGatewayDescription.mockReturnValue( [
			'You will be redirected to iDEAL',
		] );
	} );

	it( 'should render one checkbox and two inputs', () => {
		render( <PaymentGatewaySection /> );
		expect( screen.getAllByRole( 'checkbox' ).length ).toEqual( 1 );
		expect( screen.getAllByRole( 'textbox' ).length ).toEqual( 2 );
	} );

	it( 'should render "pending" capability status pill', () => {
		useGetCapabilities.mockReturnValue( { ideal_payments: 'pending' } );
		render( <PaymentGatewaySection /> );
		expect(
			screen.queryByText( 'Requires activation' )
		).toBeInTheDocument();
	} );

	it( 'should contain the webhook monitoring status', () => {
		render( <PaymentGatewaySection /> );
		expect(
			screen.queryByText( 'webhook is working' )
		).toBeInTheDocument();
	} );

	it( 'should be possible to enable/disable the payment gateway', () => {
		const enableGatewayMock = jest.fn();
		useEnabledPaymentGateway.mockReturnValue( [
			false,
			enableGatewayMock,
		] );

		render( <PaymentGatewaySection /> );

		const enableCheckbox = screen.getByRole( 'checkbox', {
			name: /Enable iDEAL/,
		} );

		expect( enableGatewayMock ).not.toHaveBeenCalled();
		expect( enableCheckbox ).not.toBeChecked();

		userEvent.click( enableCheckbox );

		expect( enableGatewayMock ).toHaveBeenCalledWith( true );
	} );

	it( 'should be possible to update the gateway name', () => {
		const setGatewayNameMock = jest.fn();
		usePaymentGatewayName.mockReturnValue( [ '', setGatewayNameMock ] );

		render( <PaymentGatewaySection /> );

		const nameInput = screen.getByRole( 'textbox', {
			name: /Name/,
		} );

		expect( setGatewayNameMock ).not.toHaveBeenCalled();
		expect( nameInput.value ).toEqual( '' );

		userEvent.type( nameInput, 'Buy with iDEAL' ); // 14 characters

		expect( setGatewayNameMock ).toHaveBeenCalledTimes( 14 );
	} );

	it( 'should be possible to update the gateway description', () => {
		const setGatewayDescriptionMock = jest.fn();
		usePaymentGatewayDescription.mockReturnValue( [
			'',
			setGatewayDescriptionMock,
		] );

		render( <PaymentGatewaySection /> );

		const descriptionInput = screen.getByRole( 'textbox', {
			name: /Description/,
		} );

		expect( setGatewayDescriptionMock ).not.toHaveBeenCalled();
		expect( descriptionInput.value ).toEqual( '' );

		userEvent.type( descriptionInput, 'You will be redirected to iDEAL' ); // 31 characters

		expect( setGatewayDescriptionMock ).toHaveBeenCalledTimes( 31 );
	} );
} );
