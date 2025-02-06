import tinycolor from 'tinycolor2';

/**
 * Generates hover colors from a background color and a text color.
 *
 * @param {string}  backgroundColor Background color, Any format accepted by tinyColor library
 * @param {string}  color Text color, any format accepted by tinyColor library
 * @return {Object} Object with new background color and text color.
 */
export const generateHoverColors = ( backgroundColor, color ) => {
	const hoverColors = {
		backgroundColor,
		color,
	};

	const tinyBackgroundColor = tinycolor( backgroundColor );
	const tinyTextColor = tinycolor( color );

	// If colors are not valid return empty strings.
	if ( ! tinyBackgroundColor.isValid() || ! tinyTextColor.isValid() ) {
		return {
			backgroundColor: '',
			color: '',
		};
	}

	// Darken if brightness > 50 (Storefront Button 51 ), else lighten
	const newBackgroundColor =
		tinyBackgroundColor.getBrightness() > 50
			? tinycolor( tinyBackgroundColor ).darken( 7 )
			: tinycolor( tinyBackgroundColor ).lighten( 7 );

	// Returns provided color if readable, otherwise black or white.
	const mostReadableColor = tinycolor.mostReadable(
		newBackgroundColor,
		[ tinyTextColor ],
		{ includeFallbackColors: true }
	);

	hoverColors.backgroundColor = newBackgroundColor.toRgbString();
	hoverColors.color = mostReadableColor.toRgbString();

	return hoverColors;
};

/**
 * Generates hover rules for UPE using a set of appearance rules as a basis.
 *
 * @param {Object}  baseRules UPE appearance rules to use as a base to generate hover colors
 * @return {Object} Object with generated hover rules.
 */
export const generateHoverRules = ( baseRules ) => {
	// If there are no colors, return the same rules as we can not generate hover colors.
	if ( ! baseRules.backgroundColor || ! baseRules.color ) {
		return baseRules;
	}

	const hoverColors = generateHoverColors(
		baseRules.backgroundColor,
		baseRules.color
	);

	const hoverRules = Object.assign( {}, baseRules );

	hoverRules.backgroundColor = hoverColors.backgroundColor;
	hoverRules.color = hoverColors.color;

	return hoverRules;
};

/**
 * Generates outline style for UPE using outline width, style and color.
 * UPE does not accept the individual properties, we need to concat them.
 *
 * @param {string}  outlineWidth Outline width from computed styles.
 * @param {string}  outlineStyle Outline width from computed styles.
 * @param {string}  outlineColor Outline width from computed styles.
 * @return {string} Object with generated hover rules.
 */

export const generateOutlineStyle = (
	outlineWidth,
	outlineStyle = 'solid',
	outlineColor
) => {
	return outlineWidth && outlineColor
		? [ outlineWidth, outlineStyle, outlineColor ].join( ' ' )
		: '';
};

/**
 * Searches through array of CSS selectors and returns first visible background color.
 *
 * @param {Array} selectors List of CSS selectors to check.
 * @return {string} CSS color value.
 */
export const getBackgroundColor = ( selectors ) => {
	const defaultColor = '#ffffff';
	let color = null;
	let i = 0;
	while ( ! color && i < selectors.length ) {
		const element = document.querySelector( selectors[ i ] );
		if ( ! element ) {
			i++;
			continue;
		}

		const bgColor = window.getComputedStyle( element ).backgroundColor;
		// If backgroundColor property present and alpha > 0.
		if ( bgColor && tinycolor( bgColor ).getAlpha() > 0 ) {
			color = bgColor;
		}
		i++;
	}
	return color || defaultColor;
};

/**
 * Determines whether background color is light or dark.
 *
 * @param {string} color CSS color value.
 * @return {boolean} True, if background is light; false, if background is dark.
 */
export const isColorLight = ( color ) => {
	return tinycolor( color ).isLight();
};
