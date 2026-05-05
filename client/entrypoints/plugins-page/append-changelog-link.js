/* global wcStripePluginsPageParams */
import { __ } from '@wordpress/i18n';

const LINK_CLASS = 'wc-stripe-view-changelog-link';

const getMessageParagraph = ( pluginSlug ) =>
	document.querySelector(
		`tr.plugin-update-tr[data-slug="${ pluginSlug }"] .update-message p`
	);

const buildChangelogLink = ( href, pluginSlug ) => {
	const link = document.createElement( 'a' );
	link.className = `thickbox open-plugin-details-modal ${ LINK_CLASS }`;
	link.href = href;
	link.dataset.slug = pluginSlug;
	link.textContent = __(
		'Click here to see what’s new in the extension.',
		'woocommerce-gateway-stripe'
	);
	return link;
};

/**
 * Appends a "Click here to see what's new" changelog link to the
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

export const initAppendChangelogLinkFromGlobals = () => {
	if ( typeof wcStripePluginsPageParams === 'undefined' ) {
		return () => {};
	}
	return initAppendChangelogLink( wcStripePluginsPageParams );
};
