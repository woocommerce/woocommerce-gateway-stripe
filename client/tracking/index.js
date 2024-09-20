/* global wc_stripe_settings_params */
import domReady from '@wordpress/dom-ready';

const LIBRARY_MOCK = {
	recordEvent: () => null,
};

/**
 * Returns the tracking library from the global event.
 *
 * @return {Object} The tracking library.
 */
function getLibrary() {
	if (
		window.wc &&
		window.wc.tracks &&
		window.wc.tracks.recordEvent &&
		typeof window.wc.tracks.recordEvent === 'function'
	) {
		return window.wc.tracks;
	}

	if ( window.wcTracks && window.wcTracks.recordEvent ) {
		return window.wcTracks;
	}

	return LIBRARY_MOCK;
}

/**
 * Checks if site tracking is enabled.
 *
 * @return {boolean} True if site tracking is enabled.
 */
function isEnabled() {
	return window.wcTracks && window.wcTracks.isEnabled;
}

/**
 * Records site event.
 *
 * @param {string}  eventName       Name of the event.
 * @param {Object?} eventProperties Event properties.
 */
export function recordEvent( eventName, eventProperties ) {
	// Wc-admin track script could be enqueued after our plugin, wrap in domReady
	// to make sure we're not too early.
	domReady( () => {
		if ( ! isEnabled() ) {
			return;
		}

		// Add default properties to every event.
		Object.assign( eventProperties, {
			// The value for test mode is localized from the server on page load,
			// thus it will only be updated after reloading the page.
			is_test_mode: wc_stripe_settings_params.is_test_mode ? 'yes' : 'no',
			stripe_version: wc_stripe_settings_params.plugin_version,
		} );

		getLibrary().recordEvent( eventName, eventProperties );
	} );
}
