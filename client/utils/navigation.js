/**
 * Page navigation goes through this module so tests can mock it: the
 * `window.location` object cannot be replaced or spied on in the test
 * environment.
 */

/**
 * Navigates the current page to the given URL.
 *
 * @param {string} url Destination URL, absolute or relative to the current page.
 */
export const navigateTo = ( url ) => {
	window.location.href = url;
};

/**
 * Reloads the current page.
 */
export const reloadPage = () => {
	window.location.reload();
};
