import React from 'react';
import { screen, render } from '@testing-library/react';
import LoadableAccountSection from '../loadable-account-section';
import { useAccountKeys } from 'wcstripe/data/account-keys/hooks';
import { useAccount } from 'wcstripe/data/account/hooks';

jest.mock( 'wcstripe/data/account/hooks', () => ( {
	useAccount: jest.fn(),
} ) );

jest.mock( 'wcstripe/data/account-keys/hooks', () => ( {
	useAccountKeys: jest.fn(),
} ) );

describe( 'LoadableAccountSection', () => {
	it( 'should not render its children when account data is loading', () => {
		useAccount.mockReturnValue( { isLoading: true } );
		useAccountKeys.mockReturnValue( { isLoading: false } );

		render(
			<LoadableAccountSection>
				<span>children</span>
			</LoadableAccountSection>
		);

		expect( screen.queryByText( 'children' ) ).not.toBeInTheDocument();
	} );

	it( 'should not render its children when account keys data is loading', () => {
		useAccount.mockReturnValue( { isLoading: false } );
		useAccountKeys.mockReturnValue( { isLoading: true } );

		render(
			<LoadableAccountSection>
				<span>children</span>
			</LoadableAccountSection>
		);

		expect( screen.queryByText( 'children' ) ).not.toBeInTheDocument();
	} );

	it( 'should render its children when account keys and account data are done loading', () => {
		useAccount.mockReturnValue( { isLoading: false } );
		useAccountKeys.mockReturnValue( { isLoading: false } );

		render(
			<LoadableAccountSection>
				<span>children</span>
			</LoadableAccountSection>
		);

		expect( screen.queryByText( 'children' ) ).toBeInTheDocument();
	} );
} );
