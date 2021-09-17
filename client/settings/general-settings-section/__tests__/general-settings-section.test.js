import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import GeneralSettingsSection from '..';
import UpeToggleContext from '../../upe-toggle/context';
import {
	useEnabledPaymentMethods,
	useGetAvailablePaymentMethods,
} from '../data-mock';

jest.mock( '../data-mock', () => ( {
	useGetAvailablePaymentMethods: jest.fn(),
	useEnabledPaymentMethods: jest.fn(),
} ) );
jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn().mockReturnValue( {} ),
	createReduxStore: jest.fn(),
	register: jest.fn(),
	combineReducers: jest.fn(),
} ) );

describe( 'GeneralSettingsSection', () => {
	beforeEach( () => {
		useGetAvailablePaymentMethods.mockReturnValue( [ 'card' ] );
		useEnabledPaymentMethods.mockReturnValue( [ [ 'card' ], jest.fn() ] );
	} );

	it( 'should render the card information and the opt-in banner with action elements if UPE is disabled', () => {
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByText( 'Credit card / debit card' )
		).toBeInTheDocument();
		expect(
			screen.queryByText(
				'Let your customers pay with major credit and debit cards without leaving your store.'
			)
		).toBeInTheDocument();
		expect( screen.queryByTestId( 'opt-in-banner' ) ).toBeInTheDocument();
	} );

	it( 'should not render the opt-in banner if UPE is enabled', () => {
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByTestId( 'opt-in-banner' )
		).not.toBeInTheDocument();
	} );

	it( 'should allow to enable a payment method when UPE is enabled', () => {
		useGetAvailablePaymentMethods.mockReturnValue( [
			'card',
			'giropay',
			'sofort',
			'sepa_debit',
		] );
		const updateEnabledMethodsMock = jest.fn();
		useEnabledPaymentMethods.mockReturnValue( [
			[ 'card' ],
			updateEnabledMethodsMock,
		] );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		const giropayCheckbox = screen.getByRole( 'checkbox', {
			name: /giropay/,
		} );

		expect( updateEnabledMethodsMock ).not.toHaveBeenCalled();
		expect( giropayCheckbox ).not.toBeChecked();

		userEvent.click( giropayCheckbox );

		expect( updateEnabledMethodsMock ).toHaveBeenCalledWith( [
			'card',
			'giropay',
		] );
	} );

	it( 'should allow to disable a payment method when UPE is enabled', () => {
		useGetAvailablePaymentMethods.mockReturnValue( [
			'card',
			'giropay',
			'sofort',
			'sepa_debit',
		] );
		const updateEnabledMethodsMock = jest.fn();
		useEnabledPaymentMethods.mockReturnValue( [
			[ 'card' ],
			updateEnabledMethodsMock,
		] );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		const cardCheckbox = screen.getByRole( 'checkbox', {
			name: /Credit card/,
		} );

		expect( updateEnabledMethodsMock ).not.toHaveBeenCalled();
		expect( cardCheckbox ).toBeChecked();

		userEvent.click( cardCheckbox );

		expect( updateEnabledMethodsMock ).toHaveBeenCalledWith( [] );
	} );

	it( 'should display a modal to allow to disable UPE', () => {
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByText( /Without the new payments experience/ )
		).not.toBeInTheDocument();

		userEvent.click(
			screen.getByRole( 'button', {
				name: 'Disable the new Payment Experience',
			} )
		);
		userEvent.click(
			screen.getByRole( 'menuitem', {
				name: 'Disable',
			} )
		);

		expect(
			screen.queryByText( /Without the new payments experience/ )
		).toBeInTheDocument();
	} );
} );
