/**
 * External dependencies
 */
import React from 'react';
import { screen, render, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import DisableUpeConfirmationModal from '../disable-upe-confirmation-modal';
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
	useDispatch: jest.fn(),
	createReduxStore: jest.fn(),
	register: jest.fn(),
	combineReducers: jest.fn(),
} ) );

describe( 'DisableUpeConfirmationModal', () => {
	beforeEach( () => {
		useGetAvailablePaymentMethods.mockReturnValue( [ 'card', 'giropay' ] );
		useEnabledPaymentMethods.mockReturnValue( [ [ 'card' ], jest.fn() ] );
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

	it( 'should not render the list of payment methods when there are multiple payments enabled', () => {
		useEnabledPaymentMethods.mockReturnValue( [
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
