import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ExitSurveyModal, { isCooldownActive, buildSurveyUrl } from '..';
import apiFetch from '@wordpress/api-fetch';

jest.mock( '@wordpress/api-fetch', () => jest.fn( () => Promise.resolve() ) );

const defaultSurveyParams = {
	exit_survey_last_shown: null,
	stripe_account_id: 'acct_test123',
	wc_store_id: 'uuid-abc-123',
	plugin_version: '10.5.3',
	wc_version: '9.9.0',
	wp_version: '6.7.2',
};

describe( 'isCooldownActive', () => {
	it( 'returns false when lastShown is null', () => {
		expect( isCooldownActive( null ) ).toBe( false );
	} );

	it( 'returns false when lastShown is an invalid date', () => {
		expect( isCooldownActive( 'not-a-date' ) ).toBe( false );
	} );

	it( 'returns true when lastShown is within 7 days', () => {
		const recent = new Date();
		recent.setDate( recent.getDate() - 3 );
		expect( isCooldownActive( recent.toISOString() ) ).toBe( true );
	} );

	it( 'returns false when lastShown is older than 7 days', () => {
		const old = new Date();
		old.setDate( old.getDate() - 8 );
		expect( isCooldownActive( old.toISOString() ) ).toBe( false );
	} );

	it( 'returns false when lastShown is exactly 7 days ago', () => {
		const exact = new Date();
		exact.setDate( exact.getDate() - 7 );
		exact.setMilliseconds( exact.getMilliseconds() - 1 );
		expect( isCooldownActive( exact.toISOString() ) ).toBe( false );
	} );
} );

describe( 'buildSurveyUrl', () => {
	it( 'builds the correct URL with all parameters', () => {
		const url = buildSurveyUrl(
			defaultSurveyParams,
			'plugins_page_deactivate'
		);
		const parsed = new URL( url );

		expect( parsed.origin + parsed.pathname ).toBe(
			'https://woocommerce.survey.fm/stripe-exit-survey'
		);
		expect( parsed.searchParams.get( 'iframe' ) ).toBe( '1' );
		expect( parsed.searchParams.get( 'q_2_text' ) ).toBe( 'acct_test123' );
		expect( parsed.searchParams.get( 'q_3_text' ) ).toBe( 'uuid-abc-123' );
		expect( parsed.searchParams.get( 'q_4_text' ) ).toBe( '10.5.3' );
		expect( parsed.searchParams.get( 'q_5_text' ) ).toBe( '9.9.0' );
		expect( parsed.searchParams.get( 'q_6_text' ) ).toBe( '6.7.2' );
		expect( parsed.searchParams.get( 'q_7_text' ) ).toBe(
			'plugins_page_deactivate'
		);
	} );

	it( 'handles the disable trigger', () => {
		const url = buildSurveyUrl( defaultSurveyParams, 'settings_disable' );
		const parsed = new URL( url );
		expect( parsed.searchParams.get( 'q_7_text' ) ).toBe(
			'settings_disable'
		);
	} );

	it( 'handles missing survey params gracefully', () => {
		const url = buildSurveyUrl( {}, 'plugins_page_deactivate' );
		const parsed = new URL( url );
		expect( parsed.searchParams.get( 'q_2_text' ) ).toBe( '' );
		expect( parsed.searchParams.get( 'q_7_text' ) ).toBe(
			'plugins_page_deactivate'
		);
	} );
} );

describe( 'ExitSurveyModal', () => {
	it( 'renders the modal with an iframe', () => {
		render(
			<ExitSurveyModal
				trigger="plugins_page_deactivate"
				surveyParams={ defaultSurveyParams }
				onRequestClose={ jest.fn() }
			/>
		);

		expect( screen.getByTitle( 'Exit Survey' ) ).toBeInTheDocument();
	} );

	it( 'renders the iframe with the correct src URL', () => {
		render(
			<ExitSurveyModal
				trigger="plugins_page_deactivate"
				surveyParams={ defaultSurveyParams }
				onRequestClose={ jest.fn() }
			/>
		);

		const iframe = screen.getByTitle( 'Exit Survey' );
		expect( iframe.src ).toContain(
			'woocommerce.survey.fm/stripe-exit-survey'
		);
		expect( iframe.src ).toContain( 'q_2_text=acct_test123' );
		expect( iframe.src ).toContain( 'q_7_text=plugins_page_deactivate' );
	} );

	it( 'calls dismiss endpoint and onRequestClose when closed', async () => {
		const handleClose = jest.fn();
		render(
			<ExitSurveyModal
				trigger="plugins_page_deactivate"
				surveyParams={ defaultSurveyParams }
				onRequestClose={ handleClose }
			/>
		);

		const closeButton = screen.getByLabelText( 'Close' );
		await userEvent.click( closeButton );

		await waitFor( () => {
			expect( handleClose ).toHaveBeenCalledTimes( 1 );
		} );

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/wc/v3/wc_stripe/exit-survey/dismiss',
				method: 'POST',
			} )
		);
	} );

	it( 'still closes when cooldown persistence fails', async () => {
		apiFetch.mockRejectedValueOnce( new Error( 'network error' ) );
		const handleClose = jest.fn();

		render(
			<ExitSurveyModal
				trigger="plugins_page_deactivate"
				surveyParams={ defaultSurveyParams }
				onRequestClose={ handleClose }
			/>
		);

		await userEvent.click( screen.getByLabelText( 'Close' ) );

		await waitFor( () => {
			expect( handleClose ).toHaveBeenCalledTimes( 1 );
		} );
	} );
} );
