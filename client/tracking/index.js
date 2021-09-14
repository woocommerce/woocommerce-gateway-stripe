/**
 * External dependencies
 */
import domReady from '@wordpress/dom-ready';

const LIBRARY_MOCK = {
	isEnabled: false,
	recordEvent: () => null,
};

/**
 * Returns the tracking library from the global event.
 *
 * @return {Object} The tracking library.
 */
function getLibrary() {
	if ( window.wc && window.wc.tracks && window.wc.tracks.recordEvent ) {
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
	return getLibrary().isEnabled;
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

		getLibrary().recordEvent( eventName, eventProperties );
	} );
}
