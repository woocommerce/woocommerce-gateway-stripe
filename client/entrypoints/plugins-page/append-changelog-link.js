/* global wcStripePluginsPageParams */
import { __ } from '@wordpress/i18n';

const LINK_CLASS = 'wc-stripe-view-changelog-link';

/**
 * Returns the paragraph element holding the post-update success message for the given plugin.
 *
 * @param {string} pluginSlug
 * @return {HTMLElement|null} The paragraph element, or null if no update message is rendered.
 */
const getMessageParagraph = ( pluginSlug ) =>
	document.querySelector(
		`tr.plugin-update-tr[data-slug="${ pluginSlug }"] .update-message p`
	);

/**
 * Builds the anchor that opens the plugin info modal on the changelog tab.
 *
 * @param {string} href
 * @param {string} pluginSlug
 * @return {HTMLAnchorElement} The anchor element, ready to append.
 */
const buildChangelogLink = ( href, pluginSlug ) => {
	const link = document.createElement( 'a' );
	link.className = `thickbox open-plugin-details-modal ${ LINK_CLASS }`;
	link.href = href;
	link.dataset.slug = pluginSlug;
	link.textContent = __( 'See what’s new', 'woocommerce-gateway-stripe' );
	return link;
};

/**
 * Appends a "See what's new" changelog link to the
 * "Updated!" message rendered after a manual plugin update.
 *
 * The new link reuses WordPress' built-in plugin information modal,
 * opened directly on the changelog tab.
 *
 * @param {{plugin_slug: string, view_changelog_url: string}} params
 * @return {() => void} Cleanup function that detaches the listener.
 */
export const initAppendChangelogLink = ( params ) => {
	const { jQuery } = window;
	if (
		! jQuery ||
		! params ||
		! params.plugin_slug ||
		! params.view_changelog_url
	) {
		return () => {};
	}

	const handleUpdateSuccess = ( _event, response ) => {
		if ( ! response || response.slug !== params.plugin_slug ) {
			return;
		}

		const messageParagraph = getMessageParagraph( params.plugin_slug );
		if (
			! messageParagraph ||
			messageParagraph.querySelector( `.${ LINK_CLASS }` )
		) {
			return;
		}

		messageParagraph.append( ' ' );
		messageParagraph.append(
			buildChangelogLink( params.view_changelog_url, params.plugin_slug )
		);
	};

	jQuery( document ).on( 'wp-plugin-update-success', handleUpdateSuccess );

	return () => {
		jQuery( document ).off(
			'wp-plugin-update-success',
			handleUpdateSuccess
		);
	};
};

/**
 * Initializes the changelog link using params from the `wcStripePluginsPageParams` global.
 *
 * @return {() => void} Cleanup function that detaches the listener.
 */
export const initAppendChangelogLinkFromGlobals = () => {
	if ( typeof wcStripePluginsPageParams === 'undefined' ) {
		return () => {};
	}
	return initAppendChangelogLink( wcStripePluginsPageParams );
};
