// List of supported CSS properties accepted by UPE elements. Source: https://docs.stripe.com/elements/appearance-api.
const paddingColorProps = [
	'color',
	'padding',
	'paddingTop',
	'paddingRight',
	'paddingBottom',
	'paddingLeft',
];

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
	'-webkit-font-smoothing',
	'-moz-osx-font-smoothing',
	'transition',
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
};
