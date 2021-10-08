import { useDispatch } from '@wordpress/data';
import React from 'react';
import { screen, render, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import DisconnectStripeConfirmationModal from '../disconnect-stripe-confirmation-modal';
import { useAccountKeys } from 'wcstripe/data/account-keys/hooks';

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn(),
	useDispatch: jest.fn(),
	createReduxStore: jest.fn(),
	register: jest.fn(),
	combineReducers: jest.fn(),
} ) );
jest.mock( 'wcstripe/data/account-keys/hooks', () => ( {
	useAccountKeys: jest.fn(),
} ) );

describe( 'DisconnectStripeConfirmationModal', () => {
	const windowLocation = window.location;
	let handleCloseMock, saveAccountKeysMock;

	beforeEach( () => {
		handleCloseMock = jest.fn();
		saveAccountKeysMock = jest
			.fn()
			.mockImplementation( () => Promise.resolve() );
		useAccountKeys.mockImplementation( () => ( {
			updateAccountKeys: jest.fn().mockReturnValue( {} ),
			saveAccountKeys: saveAccountKeysMock,
		} ) );
		useDispatch.mockReturnValue( {} );

		delete window.location;
		window.location = {
			reload: jest.fn(),
		};
	} );

	afterEach( () => {
		window.location = windowLocation;
		jest.restoreAllMocks();
	} );

	it( 'should render the message for confirmation', () => {
		render(
			<DisconnectStripeConfirmationModal onClose={ handleCloseMock } />
		);

		expect(
			screen.queryByText( 'Disconnect Stripe account' )
		).toBeInTheDocument();
		expect(
			screen.queryByText(
				'Are you sure you want to disconnect your Stripe account from your WooCommerce store?'
			)
		).toBeInTheDocument();
		expect(
			screen.queryByText(
				'All settings will be cleared and your customers will no longer be able to pay using cards and other payment methods offered by Stripe.'
			)
		).toBeInTheDocument();
	} );

	it( 'should call onClose when the action is cancelled', () => {
		render(
			<DisconnectStripeConfirmationModal onClose={ handleCloseMock } />
		);

		expect( handleCloseMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByRole( 'button', { name: 'Cancel' } ) );

		expect( handleCloseMock ).toHaveBeenCalled();
	} );

	it( 'should disconnect the account and close the modal', async () => {
		render(
			<DisconnectStripeConfirmationModal onClose={ handleCloseMock } />
		);

		expect( handleCloseMock ).not.toHaveBeenCalled();
		expect( saveAccountKeysMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByRole( 'button', { name: 'Disconnect' } ) );

		expect( handleCloseMock ).toHaveBeenCalled();
		expect( saveAccountKeysMock ).toHaveBeenCalled();
		await waitFor( () =>
			expect( window.location.reload ).toHaveBeenCalled()
		);
	} );
} );
