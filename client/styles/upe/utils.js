import tinycolor from 'tinycolor2';

export { getElementBackgroundColor } from './get-element-background-color';
export { getPaymentMethodRadioStyles } from './get-payment-method-radio-styles';
export { isTransparentColor } from './is-transparent-color';

/**
 * Generates hover colors from a background color and a text color.
 *
 * @param {string} backgroundColor Background color, Any format accepted by tinyColor library
 * @param {string} color           Text color, any format accepted by tinyColor library
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
 * @param {Object} baseRules UPE appearance rules to use as a base to generate hover colors
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
 * @param {string} outlineWidth Outline width from computed styles.
 * @param {string} outlineStyle Outline width from computed styles.
 * @param {string} outlineColor Outline width from computed styles.
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

// Constants for floating label padding adjustments.
const STRIPE_PADDING_TOP = '4px';
const STRIPE_PADDING_OFFSET = '1px';
const STRIPE_FLOATING_LABEL_MARGIN_TOP = '3px';

/**
 * Modifies the appearance object to include styles for floating label.
 * Adjusts input padding to prevent fields from growing taller when labels
 * are rendered inside the input.
 *
 * @param {Object} appearance          Appearance object to modify.
 * @param {Object} floatingLabelStyles Floating label styles extracted from the DOM.
 * @return {Object} Modified appearance object.
 */
export const handleAppearanceForFloatingLabel = (
	appearance,
	floatingLabelStyles
) => {
	// Add floating label styles.
	appearance.rules[ '.Label--floating' ] = floatingLabelStyles;

	// Update line-height for floating label to account for scaling.
	if (
		appearance.rules[ '.Label--floating' ].transform &&
		appearance.rules[ '.Label--floating' ].transform !== 'none'
	) {
		const transformMatrix =
			appearance.rules[ '.Label--floating' ].transform;
		const matrixValues = transformMatrix.match( /matrix\((.+)\)/ );
		if ( matrixValues && matrixValues[ 1 ] ) {
			const splitMatrixValues = matrixValues[ 1 ].split( /\s*,\s*/ );
			const scaleX = parseFloat( splitMatrixValues[ 0 ] );
			const scaleY = parseFloat( splitMatrixValues[ 3 ] );
			if ( ! Number.isFinite( scaleX ) || ! Number.isFinite( scaleY ) ) {
				delete appearance.rules[ '.Label--floating' ].transform;
				return appearance;
			}
			const scale = ( scaleX + scaleY ) / 2;

			const lineHeight = parseFloat(
				appearance.rules[ '.Label--floating' ].lineHeight
			);
			if ( isNaN( lineHeight ) ) {
				delete appearance.rules[ '.Label--floating' ].transform;
				return appearance;
			}
			const newLineHeight = Math.floor( lineHeight * scale );
			appearance.rules[
				'.Label--floating'
			].lineHeight = `${ newLineHeight }px`;
			appearance.rules[
				'.Label--floating'
			].fontSize = `${ newLineHeight }px`;
		}
		delete appearance.rules[ '.Label--floating' ].transform;
	}

	// Subtract the label's lineHeight from padding-top to account for floating label height.
	// Minus STRIPE_PADDING_TOP which is a constant value added by Stripe to the padding-top.
	// Minus STRIPE_PADDING_OFFSET for each vertical padding to account for unpredictable input height.
	if ( appearance.rules[ '.Input' ].paddingTop ) {
		appearance.rules[
			'.Input'
		].paddingTop = `calc(${ appearance.rules[ '.Input' ].paddingTop } - ${ appearance.rules[ '.Label--floating' ].lineHeight } - ${ STRIPE_PADDING_TOP } - ${ STRIPE_PADDING_OFFSET })`;
	}
	if ( appearance.rules[ '.Input' ].paddingBottom ) {
		const paddingOffset = parseFloat( STRIPE_PADDING_OFFSET );
		const originalPaddingBottom = parseFloat(
			appearance.rules[ '.Input' ].paddingBottom
		);
		appearance.rules[ '.Input' ].paddingBottom = `${
			originalPaddingBottom - paddingOffset
		}px`;

		appearance.rules[ '.Label' ].marginTop = `${ Math.floor(
			( originalPaddingBottom - paddingOffset ) / 3
		) }px`;
	}

	// Add top margin so the floating label doesn't sit flush against the input border.
	appearance.rules[ '.Label--floating' ].marginTop =
		STRIPE_FLOATING_LABEL_MARGIN_TOP;

	return appearance;
};
