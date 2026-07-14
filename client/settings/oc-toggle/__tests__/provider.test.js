import React, { useEffect, useContext } from 'react';
import { render, waitFor } from '@testing-library/react';
import OCToggleContextProvider from '../provider';
import OCToggleContext from '../context';
import apiFetch from '@wordpress/api-fetch';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( 'wcstripe/tracking', () => ( { recordEvent: jest.fn() } ) );
jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn().mockReturnValue( {
		invalidateResolutionForStoreSelector: () => null,
	} ),
} ) );

describe( 'OCToggleContextProvider', () => {
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
			<OCToggleContextProvider>
				<OCToggleContext.Consumer>
					{ childrenMock }
				</OCToggleContext.Consumer>
			</OCToggleContextProvider>
		);

		expect( childrenMock ).toHaveBeenCalledWith( {
			isOCEnabled: false,
			setIsOCEnabled: expect.any( Function ),
			setIsOCEnabledLocally: expect.any( Function ),
			status: 'resolved',
		} );
		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	it( 'should render the initial state given a default value for isOCEnabled', () => {
		const childrenMock = jest.fn().mockReturnValue( null );
		render(
			<OCToggleContextProvider defaultIsOCEnabled={ true }>
				<OCToggleContext.Consumer>
					{ childrenMock }
				</OCToggleContext.Consumer>
			</OCToggleContextProvider>
		);

		expect( childrenMock ).toHaveBeenCalledWith(
			expect.objectContaining( {
				isOCEnabled: true,
			} )
		);
		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	it( 'should locally update the value for isOCEnabled', () => {
		const childrenMock = jest.fn().mockReturnValue( null );

		const LocallyUpdateOCDisabledFlagMock = () => {
			const { setIsOCEnabledLocally } = useContext( OCToggleContext );
			useEffect( () => {
				setIsOCEnabledLocally( false );
			}, [ setIsOCEnabledLocally ] );

			return null;
		};

		render(
			<OCToggleContextProvider>
				<LocallyUpdateOCDisabledFlagMock />
				<OCToggleContext.Consumer>
					{ childrenMock }
				</OCToggleContext.Consumer>
			</OCToggleContextProvider>
		);

		expect( apiFetch ).not.toHaveBeenCalled();
		expect( childrenMock ).toHaveBeenCalledWith( {
			isOCEnabled: false,
			setIsOCEnabled: expect.any( Function ),
			setIsOCEnabledLocally: expect.any( Function ),
			status: 'resolved',
		} );
	} );

	it( 'should call the API and resolve when setIsOCEnabled has been called', async () => {
		const childrenMock = jest.fn().mockReturnValue( null );

		const UpdateUpeDisabledFlagMock = () => {
			const { setIsOCEnabled } = useContext( OCToggleContext );
			useEffect( () => {
				setIsOCEnabled( false );
			}, [ setIsOCEnabled ] );

			return null;
		};

		render(
			<OCToggleContextProvider defaultIsOCEnabled>
				<UpdateUpeDisabledFlagMock />
				<OCToggleContext.Consumer>
					{ childrenMock }
				</OCToggleContext.Consumer>
			</OCToggleContextProvider>
		);

		expect( childrenMock ).toHaveBeenCalledWith( {
			isOCEnabled: true,
			setIsOCEnabled: expect.any( Function ),
			setIsOCEnabledLocally: expect.any( Function ),
			status: 'resolved',
		} );

		expect( childrenMock ).toHaveBeenCalledWith( {
			isOCEnabled: true,
			setIsOCEnabled: expect.any( Function ),
			setIsOCEnabledLocally: expect.any( Function ),
			status: 'pending',
		} );

		await waitFor( () =>
			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/wc/v3/wc_stripe/oc_setting_toggle',
				method: 'POST',
				// eslint-disable-next-line camelcase
				data: { is_oc_enabled: false },
			} )
		);

		await waitFor( () => expect( apiFetch ).toHaveReturned() );

		expect( childrenMock ).toHaveBeenCalledWith( {
			isOCEnabled: false,
			setIsOCEnabled: expect.any( Function ),
			setIsOCEnabledLocally: expect.any( Function ),
			status: 'resolved',
		} );
	} );
} );
