import { useDispatch } from '@wordpress/data';
import userEvent from '@testing-library/user-event';
import React from 'react';
import { screen, render } from '@testing-library/react';
import LegacyExperienceTransitionNotice from '..';
import { recordEvent } from 'wcstripe/tracking';

jest.mock( '@wordpress/data' );

const noticesDispatch = {
	createErrorNotice: jest.fn(),
	createSuccessNotice: jest.fn(),
};

jest.mock( 'wcstripe/tracking', () => ( {
	recordEvent: jest.fn(),
} ) );

useDispatch.mockImplementation( ( storeName ) => {
	if ( storeName === 'core/notices' ) {
		return noticesDispatch;
	}

	return {};
} );

describe( 'LegacyExperienceTransitionNotice', () => {
	it( 'should render notice if the updated experience is not enabled', () => {
		render(
			<LegacyExperienceTransitionNotice
				isUpeEnabled={ false }
				setIsUpeEnabled={ jest.fn() }
			/>
		);

		expect(
			screen.queryByTestId( 'legacy-exp-title' )
		).toBeInTheDocument();
	} );

	it( 'should not render notice if the updated experience is enabled', () => {
		render(
			<LegacyExperienceTransitionNotice
				isUpeEnabled={ true }
				setIsUpeEnabled={ jest.fn() }
			/>
		);

		expect(
			screen.queryByTestId( 'legacy-exp-title' )
		).not.toBeInTheDocument();
	} );

	it( 'should enable UPE when clicking on the CTA button', () => {
		const setIsUpeEnabledMock = jest.fn().mockResolvedValue( true );

		render(
			<LegacyExperienceTransitionNotice
				isUpeEnabled={ false }
				setIsUpeEnabled={ setIsUpeEnabledMock }
			/>
		);

		userEvent.click( screen.queryByTestId( 'disable-legacy-button' ) );
		expect( setIsUpeEnabledMock ).toHaveBeenCalled();
	} );

	it( 'should display a success message when clicking on the CTA button', () => {
		render(
			<LegacyExperienceTransitionNotice
				isUpeEnabled={ false }
				setIsUpeEnabled={ jest.fn() }
			/>
		);

		userEvent.click( screen.queryByTestId( 'disable-legacy-button' ) );

		expect( noticesDispatch.createSuccessNotice ).toHaveBeenCalledWith(
			'New checkout experience enabled'
		);
	} );

	it( 'should record a Track event when clicking on the CTA button', () => {
		render(
			<LegacyExperienceTransitionNotice
				isUpeEnabled={ false }
				setIsUpeEnabled={ jest.fn() }
			/>
		);

		userEvent.click( screen.queryByTestId( 'disable-legacy-button' ) );

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcstripe_legacy_experience_disabled',
			{
				source: 'payment-methods-tab-notice',
			}
		);
	} );
} );
