/**
 * Regex to match transparent rgba() colors by detecting if the alpha channel is 0.
 *
 * @type {RegExp}
 */
const transparentColorRegex = /^rgba\( *[0-9]+, *[0-9]+, *[0-9]+, *0 *\)$/;

/**
 * Determines if the provided color is a transparent rgba() color.
 * This function expects a valid rgba() color defined via getComputedStyle().
 *
 * @param {string} color The color to check.
 * @return {boolean} True if the color is transparent, false otherwise.
 */
export const isTransparentColor = ( color ) => {
	return Boolean( color ) && transparentColorRegex.test( color );
};
