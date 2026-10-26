import React from 'react';
import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PMCSyncNotice from '../pmc-sync-notice';
import { useSettings } from '../../data';
import { useAccount } from '../../data/account';
import { useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

jest.mock( '../../data', () => ( { useSettings: jest.fn() } ) );
jest.mock( '../../data/account', () => ( { useAccount: jest.fn() } ) );
jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const local = {
	upe_checkout_experience_accepted_payments: [ 'card' ],
	express_checkout: 'no',
};
const review = {
	differs: true,
	revision: 'revision',
	configuration_id: 'pmc_example',
	testmode: true,
	local,
	stripe: {
		upe_checkout_experience_accepted_payments: [ 'card', 'klarna' ],
		express_checkout: 'yes',
	},
	wallets: { apple_pay: false, google_pay: true },
};
const settings = {
	is_pmc_enabled: false,
	enabled_payment_method_ids: [ 'card' ],
	available_payment_method_ids: [ 'card', 'klarna' ],
	is_express_checkout_enabled: false,
};
const updateSettings = jest.fn();

beforeEach( () => {
	jest.clearAllMocks();
	useSettings.mockReturnValue( {
		settings,
		isLoading: false,
		isSaving: false,
	} );
	useAccount.mockReturnValue( { data: { account: { id: 'acct_example' } } } );
	useDispatch.mockReturnValue( { updateSettings } );
	apiFetch.mockResolvedValue( review );
} );

it( 'does not compare during active sync', () => {
	useSettings.mockReturnValue( {
		settings: { ...settings, is_pmc_enabled: true },
	} );
	render( <PMCSyncNotice onResolved={ jest.fn() } /> );
	expect( apiFetch ).not.toHaveBeenCalled();
} );

it.each( [
	[ 'missing account data', {} ],
	[ 'a disconnected account', { data: { account: null } } ],
	[ 'an empty account', { data: { account: {} } } ],
	[
		'an account that is still loading',
		{ data: { account: { id: 'acct_example' } }, isLoading: true },
	],
] )( 'does not compare or show a notice for %s', ( label, account ) => {
	useAccount.mockReturnValue( account );
	const { container } = render( <PMCSyncNotice onResolved={ jest.fn() } /> );
	expect( apiFetch ).not.toHaveBeenCalled();
	expect( container ).toBeEmptyDOMElement();
} );

it( 'hides an existing comparison error when the account disconnects', async () => {
	apiFetch.mockRejectedValueOnce( { message: 'Unable to compare.' } );
	const onResolved = jest.fn();
	const { container, rerender } = render(
		<PMCSyncNotice onResolved={ onResolved } />
	);
	expect(
		await screen.findByText( 'Unable to compare.', { selector: 'p' } )
	).toBeInTheDocument();

	useAccount.mockReturnValue( { data: { account: null } } );
	rerender( <PMCSyncNotice onResolved={ onResolved } /> );
	expect( container ).toBeEmptyDOMElement();
	expect( apiFetch ).toHaveBeenCalledTimes( 1 );
} );

it( 'ignores a pending comparison after the account disconnects', async () => {
	let finishComparison;
	apiFetch.mockReturnValueOnce(
		new Promise( ( resolve ) => {
			finishComparison = resolve;
		} )
	);
	const onResolved = jest.fn();
	const { container, rerender } = render(
		<PMCSyncNotice onResolved={ onResolved } />
	);

	useAccount.mockReturnValue( { data: { account: null } } );
	rerender( <PMCSyncNotice onResolved={ onResolved } /> );
	await act( async () => {
		finishComparison( { ...review, differs: false, stripe: local } );
	} );
	expect( container ).toBeEmptyDOMElement();
	expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	expect( updateSettings ).not.toHaveBeenCalled();
} );

it.each( [
	[ 'Use WooCommerce', 'woocommerce' ],
	[ 'Use Stripe', 'stripe' ],
] )(
	'%s confirms the displayed revision and refreshes the form',
	async ( label, source ) => {
		const saved = { ...settings, is_pmc_enabled: true };
		const onResolved = jest.fn();
		apiFetch.mockResolvedValueOnce( review ).mockResolvedValueOnce( saved );
		render( <PMCSyncNotice onResolved={ onResolved } /> );
		await userEvent.click(
			await screen.findByRole( 'button', { name: label } )
		);
		await waitFor( () =>
			expect( updateSettings ).toHaveBeenCalledWith( saved )
		);
		expect( apiFetch ).toHaveBeenNthCalledWith(
			2,
			expect.objectContaining( {
				method: 'POST',
				data: { source, revision: 'revision' },
			} )
		);
		expect( onResolved ).toHaveBeenCalledWith( saved );
	}
);

it( 'verifies and resumes automatically when the saved selections match', async () => {
	const saved = { ...settings, is_pmc_enabled: true };
	apiFetch
		.mockResolvedValueOnce( { ...review, differs: false, stripe: local } )
		.mockResolvedValueOnce( saved );
	render( <PMCSyncNotice onResolved={ jest.fn() } /> );
	await waitFor( () =>
		expect( updateSettings ).toHaveBeenCalledWith( saved )
	);
	expect( apiFetch ).toHaveBeenNthCalledWith(
		2,
		expect.objectContaining( {
			method: 'POST',
			data: { source: 'stripe', revision: 'revision' },
		} )
	);
} );

it( 'requires unsaved changes to be saved first', async () => {
	render( <PMCSyncNotice onResolved={ jest.fn() } hasUnsavedChanges /> );
	expect(
		await screen.findByRole( 'button', { name: 'Use Stripe' } )
	).toBeDisabled();
	expect(
		screen.getByRole( 'button', { name: 'Use WooCommerce' } )
	).toBeDisabled();
} );

it( 'requires a new comparison after a rejected confirmation', async () => {
	apiFetch
		.mockResolvedValueOnce( review )
		.mockRejectedValueOnce( { message: 'Payment settings changed.' } );
	render( <PMCSyncNotice onResolved={ jest.fn() } /> );
	await userEvent.click(
		await screen.findByRole( 'button', { name: 'Use WooCommerce' } )
	);
	expect(
		await screen.findByText( 'Payment settings changed.', {
			selector: 'p',
		} )
	).toBeInTheDocument();
	expect(
		screen.queryByRole( 'button', { name: 'Use WooCommerce' } )
	).not.toBeInTheDocument();
	expect(
		screen.getByRole( 'button', { name: 'Refresh comparison' } )
	).toBeEnabled();
	expect( updateSettings ).not.toHaveBeenCalled();
} );
