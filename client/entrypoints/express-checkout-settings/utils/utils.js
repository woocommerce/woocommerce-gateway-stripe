/**
 * Whether or not to use Google Pay branded button in Chrome.
 *
 * @return {boolean} Use Google Pay button in Chrome.
 */
export const shouldUseGooglePayBrand = () => {
	const ua = window.navigator.userAgent.toLowerCase();
	const isChrome =
		/chrome/.test( ua ) &&
		! /edge|edg|opr|brave\//.test( ua ) &&
		window.navigator.vendor === 'Google Inc.';
	// newer versions of Brave do not have the userAgent string
	const isBrave = isChrome && window.navigator.brave;
	return isChrome && ! isBrave;
};
