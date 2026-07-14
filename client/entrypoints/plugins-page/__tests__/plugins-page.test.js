import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PluginsPageApp from '../plugins-page-app';

jest.mock( '@wordpress/api-fetch', () => jest.fn( () => Promise.resolve() ) );

const mockParams = {
	exit_survey_last_shown: null,
	stripe_account_id: 'acct_test',
	wc_store_id: 'uuid-test',
	plugin_version: '10.5.3',
	wc_version: '9.9.0',
	wp_version: '6.7.2',
};

describe( 'PluginsPageApp', () => {
	let deactivateLink;

	beforeEach( () => {
		deactivateLink = document.createElement( 'a' );
		deactivateLink.id = 'deactivate-woocommerce-gateway-stripe';
		deactivateLink.href =
			'http://example.com/wp-admin/plugins.php?action=deactivate&plugin=woocommerce-gateway-stripe';
		deactivateLink.textContent = 'Deactivate';
		document.body.appendChild( deactivateLink );

		global.wcStripePluginsPageParams = { ...mockParams };
	} );

	afterEach( () => {
		deactivateLink.remove();
		delete global.wcStripePluginsPageParams;
	} );

	it( 'shows the exit survey when deactivate link is clicked and cooldown is inactive', async () => {
		render( <PluginsPageApp /> );

		await userEvent.click( deactivateLink );

		expect( screen.getByTitle( 'Exit Survey' ) ).toBeInTheDocument();
	} );

	it( 'does not show the exit survey when cooldown is active', async () => {
		const recent = new Date();
		recent.setDate( recent.getDate() - 1 );
		global.wcStripePluginsPageParams = {
			...mockParams,
			exit_survey_last_shown: recent.toISOString(),
		};

		render( <PluginsPageApp /> );

		await userEvent.click( deactivateLink );

		expect( screen.queryByTitle( 'Exit Survey' ) ).not.toBeInTheDocument();
	} );

	it( 'dismisses the survey when close is clicked', async () => {
		render( <PluginsPageApp /> );

		await userEvent.click( deactivateLink );
		expect( screen.getByTitle( 'Exit Survey' ) ).toBeInTheDocument();

		await userEvent.click( screen.getByLabelText( 'Close' ) );

		await waitFor( () => {
			expect(
				screen.queryByTitle( 'Exit Survey' )
			).not.toBeInTheDocument();
		} );
	} );

	it( 'renders nothing when deactivate link is not in the DOM', () => {
		deactivateLink.remove();

		const { container } = render( <PluginsPageApp /> );

		expect( container.innerHTML ).toBe( '' );
	} );

	it( 'does not intercept when localized params are missing', async () => {
		delete global.wcStripePluginsPageParams;

		render( <PluginsPageApp /> );

		await userEvent.click( deactivateLink );

		expect( screen.queryByTitle( 'Exit Survey' ) ).not.toBeInTheDocument();
	} );
} );
