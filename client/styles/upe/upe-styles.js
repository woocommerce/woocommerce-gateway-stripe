// List of supported CSS properties accepted by UPE elements. Source: https://docs.stripe.com/elements/appearance-api.
const paddingColorProps = [
	'color',
	'padding',
	'paddingTop',
	'paddingRight',
	'paddingBottom',
	'paddingLeft',
];

const computedStylePropertyMaps = {
	'-webkit-font-smoothing': 'WebkitFontSmoothing',
	'-moz-osx-font-smoothing': 'MozOsxFontSmoothing',
};

/**
 * Key to use when reading this property from `getComputedStyle( element )`.
 * If the property is not present in the computed style map, the original string is returned.
 *
 * @param {string} propertyName Appearance property name.
 * @return {string} Computed-style object key for that property.
 */
export const getSourcePropertyName = ( propertyName ) => {
	return computedStylePropertyMaps[ propertyName ] || propertyName;
};

const textFontTransitionProps = [
	'fontFamily',
	'fontSize',
	'lineHeight',
	'letterSpacing',
	'fontWeight',
	'fontVariation',
	'textDecoration',
	'textShadow',
	'textTransform',
	'transition',
	'-webkit-font-smoothing',
	'-moz-osx-font-smoothing',
];
const borderOutlineBackgroundProps = [
	'border',
	'borderTop',
	'borderRight',
	'borderBottom',
	'borderLeft',
	'borderRadius',
	'borderWidth',
	'borderColor',
	'borderStyle',
	'borderTopWidth',
	'borderTopColor',
	'borderTopStyle',
	'borderRightWidth',
	'borderRightColor',
	'borderRightStyle',
	'borderBottomWidth',
	'borderBottomColor',
	'borderBottomStyle',
	'borderLeftWidth',
	'borderLeftColor',
	'borderLeftStyle',
	'borderTopLeftRadius',
	'borderTopRightRadius',
	'borderBottomRightRadius',
	'borderBottomLeftRadius',
	'outline',
	'outlineOffset',
	'backgroundColor',
	'boxShadow',
];
const upeSupportedProperties = {
	'.Label': [ ...paddingColorProps, ...textFontTransitionProps ],
	'.Input': [
		...paddingColorProps,
		...textFontTransitionProps,
		...borderOutlineBackgroundProps,
	],
	'.Error': [
		...paddingColorProps,
		...textFontTransitionProps,
		...borderOutlineBackgroundProps,
	],
	'.Tab': [
		...paddingColorProps,
		...textFontTransitionProps,
		...borderOutlineBackgroundProps,
	],
	'.TabIcon': [ ...paddingColorProps ],
	'.TabLabel': [ ...paddingColorProps, ...textFontTransitionProps ],
	'.Text': [ ...paddingColorProps, ...textFontTransitionProps ],
	'.Block': [
		...paddingColorProps.slice( 1 ), // Remove color
		...borderOutlineBackgroundProps.slice( 1 ), // Remove backgroundColor
	],
};

// Restricted properties allowed to generate the automated theming of UPE.
const restrictedTabProperties = [ 'backgroundColor', 'color', 'fontFamily' ];

const restrictedTabSelectedProperties = [
	'outlineColor',
	'outlineWidth',
	'outlineStyle',
	'backgroundColor',
	'color',
];

const restrictedTabIconSelectedProperties = [ 'color' ];

export const upeRestrictedProperties = {
	'.Label': upeSupportedProperties[ '.Label' ],
	'.Label--floating': [ ...upeSupportedProperties[ '.Label' ], 'transform' ],
	'.Input': [
		...upeSupportedProperties[ '.Input' ],
		'outlineColor',
		'outlineWidth',
		'outlineStyle',
	],
	'.Error': upeSupportedProperties[ '.Error' ],
	'.Tab': [ ...restrictedTabProperties ],
	'.Tab--selected': [
		...restrictedTabSelectedProperties,
		borderOutlineBackgroundProps,
	],
	'.TabIcon': upeSupportedProperties[ '.TabIcon' ],
	'.TabIcon--selected': [ ...restrictedTabIconSelectedProperties ],
	'.TabLabel': upeSupportedProperties[ '.TabLabel' ],
	'.Text': upeSupportedProperties[ '.Text' ],
	'.Block': upeSupportedProperties[ '.Block' ],
};
