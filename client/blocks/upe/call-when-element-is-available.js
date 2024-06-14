/**
 * Call a function when an element is available in the DOM.
 *
 * @param {string} selector The selector to look for.
 * @param {Function} callable fThe function to call when the element is available.
 */
export function callWhenElementIsAvailable( selector, callable ) {
	const checkoutBlock = document.querySelector(
		'[data-block-name="woocommerce/checkout"]'
	);

	if ( ! checkoutBlock ) {
		return;
	}

	const observer = new MutationObserver( ( mutationList, obs ) => {
		if ( document.querySelector( selector ) ) {
			// Tokens found, run the function and disconnect the observer.
			callable();
			obs.disconnect();
		}
	} );

	observer.observe( checkoutBlock, {
		childList: true,
		subtree: true,
	} );
}
