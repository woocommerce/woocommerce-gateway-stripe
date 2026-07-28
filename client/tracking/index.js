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

		// Only localized on Stripe admin screens; guard so callers elsewhere
		// (e.g. plugins page) don't throw ReferenceError.
		const settingsParams =
			// eslint-disable-next-line camelcase
			typeof wc_stripe_settings_params !== 'undefined'
				? wc_stripe_settings_params // eslint-disable-line camelcase
				: null;
		if ( settingsParams ) {
			Object.assign( eventProperties, {
				is_test_mode: settingsParams.is_test_mode ? 'yes' : 'no',
				stripe_version: settingsParams.plugin_version,
			} );
		}

		getLibrary().recordEvent( eventName, eventProperties );
	} );
}
