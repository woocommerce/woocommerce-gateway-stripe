import React, { useEffect, useContext } from 'react';
import { render, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import UpeToggleContextProvider from '../provider';
import UpeToggleContext from '../context';
import { recordEvent } from 'wcstripe/tracking';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( 'wcstripe/tracking', () => ( { recordEvent: jest.fn() } ) );
jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn().mockReturnValue( {
		invalidateResolutionForStoreSelector: () => null,
	} ),
} ) );

describe( 'UpeToggleContextProvider', () => {
	afterEach( () => {
		jest.clearAllMocks();

		apiFetch.mockResolvedValue( true );
	} );

	afterAll( () => {
		jest.restoreAllMocks();
	} );

	it( 'should render the initial state', () => {
		const childrenMock = jest.fn().mockReturnValue( null );
		render(
			<UpeToggleContextProvider>
				<UpeToggleContext.Consumer>
					{ childrenMock }
				</UpeToggleContext.Consumer>
			</UpeToggleContextProvider>
		);

		expect( childrenMock ).toHaveBeenCalledWith( {
			isUpeEnabled: false,
			setIsUpeEnabled: expect.any( Function ),
			setIsUpeEnabledLocally: expect.any( Function ),
			status: 'resolved',
		} );
		expect( apiFetch ).not.toHaveBeenCalled();
		expect( recordEvent ).not.toHaveBeenCalled();
	} );

	it( 'should render the initial state given a default value for isUpeEnabled', () => {
		const childrenMock = jest.fn().mockReturnValue( null );
		render(
			<UpeToggleContextProvider defaultIsUpeEnabled={ true }>
				<UpeToggleContext.Consumer>
					{ childrenMock }
				</UpeToggleContext.Consumer>
			</UpeToggleContextProvider>
		);

		expect( childrenMock ).toHaveBeenCalledWith(
			expect.objectContaining( {
				isUpeEnabled: true,
			} )
		);
		expect( apiFetch ).not.toHaveBeenCalled();
		expect( recordEvent ).not.toHaveBeenCalled();
	} );

	it( 'should locally update the value for isUpeEnabled', () => {
		const childrenMock = jest.fn().mockReturnValue( null );

		const LocallyUpdateUpeDisabledFlagMock = () => {
			const { setIsUpeEnabledLocally } = useContext( UpeToggleContext );
			useEffect( () => {
				setIsUpeEnabledLocally( false );
			}, [ setIsUpeEnabledLocally ] );

			return null;
		};

		render(
			<UpeToggleContextProvider defaultIsUpeEnabled={ true }>
				<LocallyUpdateUpeDisabledFlagMock />
				<UpeToggleContext.Consumer>
					{ childrenMock }
				</UpeToggleContext.Consumer>
			</UpeToggleContextProvider>
		);

		expect( apiFetch ).not.toHaveBeenCalled();
		expect( recordEvent ).toHaveBeenCalledWith(
			'wcstripe_legacy_experience_enabled',
			{
				source: 'settings-tab-checkbox',
			}
		);
		expect( childrenMock ).toHaveBeenCalledWith( {
			isUpeEnabled: false,
			setIsUpeEnabled: expect.any( Function ),
			setIsUpeEnabledLocally: expect.any( Function ),
			status: 'resolved',
		} );
	} );

	it( 'should call the API and resolve when setIsUpeEnabled has been called', async () => {
		const childrenMock = jest.fn().mockReturnValue( null );

		const UpdateUpeDisabledFlagMock = () => {
			const { setIsUpeEnabled } = useContext( UpeToggleContext );
			useEffect( () => {
				setIsUpeEnabled( false );
			}, [ setIsUpeEnabled ] );

			return null;
		};

		render(
			<UpeToggleContextProvider defaultIsUpeEnabled>
				<UpdateUpeDisabledFlagMock />
				<UpeToggleContext.Consumer>
					{ childrenMock }
				</UpeToggleContext.Consumer>
			</UpeToggleContextProvider>
		);

		expect( childrenMock ).toHaveBeenCalledWith( {
			isUpeEnabled: true,
			setIsUpeEnabled: expect.any( Function ),
			setIsUpeEnabledLocally: expect.any( Function ),
			status: 'resolved',
		} );

		expect( childrenMock ).toHaveBeenCalledWith( {
			isUpeEnabled: true,
			setIsUpeEnabled: expect.any( Function ),
			setIsUpeEnabledLocally: expect.any( Function ),
			status: 'pending',
		} );

		await waitFor( () =>
			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/wc/v3/wc_stripe/upe_flag_toggle',
				method: 'POST',
				// eslint-disable-next-line camelcase
				data: { is_upe_enabled: false },
			} )
		);

		await waitFor( () => expect( apiFetch ).toHaveReturned() );

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcstripe_legacy_experience_enabled',
			{
				source: 'settings-tab-checkbox',
			}
		);
		expect( childrenMock ).toHaveBeenCalledWith( {
			isUpeEnabled: false,
			setIsUpeEnabled: expect.any( Function ),
			setIsUpeEnabledLocally: expect.any( Function ),
			status: 'resolved',
		} );
	} );
} );
