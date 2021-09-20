import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import GeneralSettingsSection from '..';
import UpeToggleContext from '../../upe-toggle/context';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
} from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useGetAvailablePaymentMethodIds: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
} ) );
jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn().mockReturnValue( {} ),
	createReduxStore: jest.fn(),
	register: jest.fn(),
	combineReducers: jest.fn(),
} ) );
jest.mock( '../../loadable-settings-section', () => ( { children } ) =>
	children
);

describe( 'GeneralSettingsSection', () => {
	beforeEach( () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'card' ] );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card' ], jest.fn() ] );
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
		expect(
			screen.queryByText( 'Get more payment methods' )
		).not.toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', {
				name: 'Disable the new Payment Experience',
			} )
		).not.toBeInTheDocument();
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
		expect(
			screen.queryByText( 'Get more payment methods' )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', {
				name: 'Disable the new Payment Experience',
			} )
		).toBeInTheDocument();
	} );

	it( 'should allow to enable a payment method when UPE is enabled', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'giropay',
			'sofort',
			'sepa_debit',
		] );
		const updateEnabledMethodsMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
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
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'giropay',
			'sofort',
			'sepa_debit',
		] );
		const updateEnabledMethodsMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
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
