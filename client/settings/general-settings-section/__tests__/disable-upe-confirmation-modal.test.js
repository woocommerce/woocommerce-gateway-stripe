import { useDispatch } from '@wordpress/data';
import React from 'react';
import { screen, render, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import DisableUpeConfirmationModal from '../disable-upe-confirmation-modal';
import UpeToggleContext from '../../upe-toggle/context';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
} from 'wcstripe/data';
import { useGetCapabilities } from 'wcstripe/data/account';

jest.mock( 'wcstripe/data', () => ( {
	useGetAvailablePaymentMethodIds: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
} ) );
jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
	createReduxStore: jest.fn(),
	register: jest.fn(),
	combineReducers: jest.fn(),
} ) );
jest.mock( 'wcstripe/data/account', () => ( {
	useGetCapabilities: jest.fn(),
} ) );

describe( 'DisableUpeConfirmationModal', () => {
	beforeEach( () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'giropay',
		] );
		useGetCapabilities.mockReturnValue( {
			card_payments: 'active',
			giropay_payments: 'active',
		} );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card' ], jest.fn() ] );
		useDispatch.mockReturnValue( {} );
	} );

	it( 'should not render the list of payment methods when only card is enabled', () => {
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<DisableUpeConfirmationModal />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByText( /Payment methods that require/ )
		).not.toBeInTheDocument();
	} );

	it( 'should not render payment methods that are not part of the account capabilities', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'giropay' ],
			jest.fn(),
		] );

		useGetCapabilities.mockReturnValue( {
			card_payments: 'active',
		} );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<DisableUpeConfirmationModal />
			</UpeToggleContext.Provider>
		);

		expect( screen.queryByText( /giropay/ ) ).not.toBeInTheDocument();
	} );

	it( 'should render the list of payment methods when there are multiple payments enabled', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'giropay' ],
			jest.fn(),
		] );
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<DisableUpeConfirmationModal />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByText( /Payment methods that require/ )
		).toBeInTheDocument();
	} );

	it( 'should call onClose when the action is cancelled', () => {
		const handleCloseMock = jest.fn();
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<DisableUpeConfirmationModal onClose={ handleCloseMock } />
			</UpeToggleContext.Provider>
		);

		expect( handleCloseMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByRole( 'button', { name: 'Cancel' } ) );

		expect( handleCloseMock ).toHaveBeenCalled();
	} );

	it( 'should allow to disable UPE and close the modal', async () => {
		const setIsUpeEnabledMock = jest.fn().mockResolvedValue( true );
		useDispatch.mockReturnValue( {
			createErrorNotice: () => null,
			createSuccessNotice: () => null,
		} );
		const handleCloseMock = jest.fn();
		render(
			<UpeToggleContext.Provider
				value={ {
					isUpeEnabled: false,
					setIsUpeEnabled: setIsUpeEnabledMock,
				} }
			>
				<DisableUpeConfirmationModal onClose={ handleCloseMock } />
			</UpeToggleContext.Provider>
		);

		expect( handleCloseMock ).not.toHaveBeenCalled();
		expect( setIsUpeEnabledMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByRole( 'button', { name: 'Disable' } ) );

		await waitFor( () => expect( setIsUpeEnabledMock ).toHaveBeenCalled() );

		expect( handleCloseMock ).toHaveBeenCalled();
	} );
} );
