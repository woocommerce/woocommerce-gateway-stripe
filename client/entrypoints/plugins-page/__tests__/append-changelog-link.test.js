import jQuery from 'jquery';
import { initAppendChangelogLink } from '../append-changelog-link';

const PLUGIN_SLUG = 'woocommerce-gateway-stripe';
const CHANGELOG_URL =
	'http://example.com/wp-admin/plugin-install.php?tab=plugin-information&plugin=woocommerce-gateway-stripe&section=changelog&TB_iframe=true&width=600&height=550';

const renderUpdateRow = ( slug = PLUGIN_SLUG ) => {
	const row = document.createElement( 'tr' );
	row.className = 'plugin-update-tr';
	row.dataset.slug = slug;
	row.innerHTML =
		'<td><div class="update-message notice"><p>Updated!</p></div></td>';
	document.body.appendChild( row );
	return row;
};

const triggerSuccess = ( response ) => {
	jQuery( document ).trigger( 'wp-plugin-update-success', [ response ] );
};

describe( 'initAppendChangelogLink', () => {
	let cleanup;

	beforeEach( () => {
		window.jQuery = jQuery;
	} );

	afterEach( () => {
		if ( cleanup ) {
			cleanup();
			cleanup = null;
		}
		document.body.innerHTML = '';
		delete window.jQuery;
	} );

	it( 'appends the changelog link to the Updated! message for our plugin', () => {
		const row = renderUpdateRow();
		cleanup = initAppendChangelogLink( {
			plugin_slug: PLUGIN_SLUG,
			view_changelog_url: CHANGELOG_URL,
		} );

		triggerSuccess( { slug: PLUGIN_SLUG } );

		const link = row.querySelector( '.wc-stripe-view-changelog-link' );
		expect( link ).not.toBeNull();
		expect( link.getAttribute( 'href' ) ).toBe( CHANGELOG_URL );
		expect( link.classList.contains( 'thickbox' ) ).toBe( true );
		expect( link.classList.contains( 'open-plugin-details-modal' ) ).toBe(
			true
		);
		expect( link.textContent ).toMatch( /see what.*new/i );
	} );

	it( 'ignores update events for other plugins', () => {
		const row = renderUpdateRow( 'some-other-plugin' );
		cleanup = initAppendChangelogLink( {
			plugin_slug: PLUGIN_SLUG,
			view_changelog_url: CHANGELOG_URL,
		} );

		triggerSuccess( { slug: 'some-other-plugin' } );

		expect(
			row.querySelector( '.wc-stripe-view-changelog-link' )
		).toBeNull();
	} );

	it( 'does not append the link twice if the success event fires repeatedly', () => {
		const row = renderUpdateRow();
		cleanup = initAppendChangelogLink( {
			plugin_slug: PLUGIN_SLUG,
			view_changelog_url: CHANGELOG_URL,
		} );

		triggerSuccess( { slug: PLUGIN_SLUG } );
		triggerSuccess( { slug: PLUGIN_SLUG } );

		expect(
			row.querySelectorAll( '.wc-stripe-view-changelog-link' )
		).toHaveLength( 1 );
	} );

	it( 'is a no-op when jQuery is unavailable', () => {
		delete window.jQuery;

		expect( () =>
			initAppendChangelogLink( {
				plugin_slug: PLUGIN_SLUG,
				view_changelog_url: CHANGELOG_URL,
			} )
		).not.toThrow();
	} );

	it( 'is a no-op when params are missing', () => {
		renderUpdateRow();
		cleanup = initAppendChangelogLink( undefined );

		triggerSuccess( { slug: PLUGIN_SLUG } );

		expect(
			document.querySelector( '.wc-stripe-view-changelog-link' )
		).toBeNull();
	} );

	it( 'is a no-op when view_changelog_url is missing so the link is never rendered with href="undefined"', () => {
		renderUpdateRow();
		cleanup = initAppendChangelogLink( { plugin_slug: PLUGIN_SLUG } );

		triggerSuccess( { slug: PLUGIN_SLUG } );

		expect(
			document.querySelector( '.wc-stripe-view-changelog-link' )
		).toBeNull();
	} );

	it( 'cleanup detaches the listener', () => {
		const row = renderUpdateRow();
		const detach = initAppendChangelogLink( {
			plugin_slug: PLUGIN_SLUG,
			view_changelog_url: CHANGELOG_URL,
		} );

		detach();
		triggerSuccess( { slug: PLUGIN_SLUG } );

		expect(
			row.querySelector( '.wc-stripe-view-changelog-link' )
		).toBeNull();
	} );
} );
