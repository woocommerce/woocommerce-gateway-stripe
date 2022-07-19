import { upeRestrictedProperties } from './upe-styles';
import { generateHoverRules, generateOutlineStyle } from './utils.js';

const dashedToCamelCase = ( string ) => {
	return string.replace( /-([a-z])/g, function ( g ) {
		return g[ 1 ].toUpperCase();
	} );
};

export const getFieldStyles = ( selector, upeElement ) => {
	if ( ! document.querySelector( selector ) ) {
		return {};
	}

	const validProperties = upeRestrictedProperties[ upeElement ];

	const elem = document.querySelector( selector );

	const styles = window.getComputedStyle( elem );

	const filteredStyles = {};

	for ( let i = 0; i < styles.length; i++ ) {
		const camelCase = dashedToCamelCase( styles[ i ] );
		if ( validProperties.includes( camelCase ) ) {
			filteredStyles[ camelCase ] = styles.getPropertyValue(
				styles[ i ]
			);
		}
	}

	if ( upeElement === '.Input' ) {
		const outline = generateOutlineStyle(
			filteredStyles.outlineWidth,
			filteredStyles.outlineStyle,
			filteredStyles.outlineColor
		);
		if ( outline !== '' ) {
			filteredStyles.outline = outline;
		}
		delete filteredStyles.outlineWidth;
		delete filteredStyles.outlineColor;
		delete filteredStyles.outlineStyle;
	}

	return filteredStyles;
};

export const getFontRulesFromPage = () => {
	const fontRules = [],
		sheets = document.styleSheets,
		fontDomains = [
			'fonts.googleapis.com',
			'fonts.gstatic.com',
			'fast.fonts.com',
			'use.typekit.net',
		];
	for ( let i = 0; i < sheets.length; i++ ) {
		if ( ! sheets[ i ].href ) {
			continue;
		}
		const url = new URL( sheets[ i ].href );
		if ( fontDomains.indexOf( url.hostname ) !== -1 ) {
			fontRules.push( {
				cssSrc: sheets[ i ].href,
			} );
		}
	}

	return fontRules;
};

export const getAppearance = () => {
	const upeThemeInputSelector = '#billing_first_name';
	const upeThemeLabelSelector = '.woocommerce-checkout .form-row label';
	const upeThemeSelectedPaymentSelector =
		'.woocommerce-checkout .place-order .button.alt';
	const upeThemeInvalidInputSelector = '#wc-stripe-hidden-invalid-input';
	const upeThemeFocusInputSelector = '#wc-stripe-hidden-input';

	const inputRules = getFieldStyles( upeThemeInputSelector, '.Input' );
	const inputFocusRules = getFieldStyles(
		upeThemeFocusInputSelector,
		'.Input'
	);
	const inputInvalidRules = getFieldStyles(
		upeThemeInvalidInputSelector,
		'.Input'
	);

	const labelRules = getFieldStyles( upeThemeLabelSelector, '.Label' );

	const tabRules = getFieldStyles( upeThemeInputSelector, '.Tab' );
	const selectedTabRules = getFieldStyles(
		upeThemeSelectedPaymentSelector,
		'.Tab--selected'
	);
	const tabHoverRules = generateHoverRules( tabRules );
	const selectedTabHoverRules = generateHoverRules( selectedTabRules );

	const tabIconHoverRules = {
		color: tabHoverRules.color,
	};
	const selectedTabIconRules = {
		color: selectedTabRules.color,
	};
	const selectedTabIconHoverRules = {
		color: selectedTabHoverRules.color,
	};

	const appearance = {
		rules: {
			'.Input': inputRules,
			'.Input:focus': inputFocusRules,
			'.Input--invalid': inputInvalidRules,
			'.Label': labelRules,
			'.Tab': tabRules,
			'.Tab:hover': tabHoverRules,
			'.Tab--selected': selectedTabRules,
			'.Tab--selected:hover': selectedTabHoverRules,
			'.TabIcon:hover': tabIconHoverRules,
			'.TabIcon--selected': selectedTabIconRules,
			'.TabIcon--selected:hover': selectedTabIconHoverRules,
			'.CheckboxInput': {
				backgroundColor: 'var(--colorBackground)',
				borderRadius: 'min(5px, var(--borderRadius))',
				transition:
					'background 0.15s ease, border 0.15s ease, box-shadow 0.15s ease',
				border: '1px solid var(--p-colorBackgroundDeemphasize10)',
			},
			'.CheckboxInput--checked': {
				backgroundColor: 'var(--colorPrimary)	',
				borderColor: 'var(--colorPrimary)',
			},
		},
	};

	return appearance;
};
