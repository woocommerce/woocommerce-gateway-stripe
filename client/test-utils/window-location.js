const DEFAULT_URL = 'http://localhost';

/**
 * Sets the page URL without navigating, so code under test reads the given
 * origin, protocol, path, query, and hash from `window.location`.
 *
 * `window.location` cannot be replaced or redefined in jsdom, so this goes
 * through the `jsdom` instance exposed by `tests/js/jest-environment.js`.
 *
 * @param {string} url Absolute URL.
 */
export const setWindowLocation = ( url ) => {
	global.jsdom.reconfigure( { url } );
};

export const resetWindowLocation = () => setWindowLocation( DEFAULT_URL );
