import * as upeStyles from '..';

describe( 'Getting styles for automated theming', () => {
	const mockElement = document.createElement( 'input' );
	const mockCSStyleDeclaration = {
		length: 6,
		0: 'color',
		1: 'backgroundColor',
		2: 'fontFamily',
		3: 'unsuportedProperty',
		4: 'outlineColor',
		5: 'outlineWidth',
		getPropertyValue: ( propertyName ) => {
			const cssProperties = {
				fontFamily:
					'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
				color: 'rgb(109, 109, 109)',
				backgroundColor: 'rgba(0, 0, 0, 0)',
				unsuportedProperty: 'some value',
				outlineColor: 'rgb(150, 88, 138)',
				outlineWidth: '1px',
			};
			return cssProperties[ propertyName ];
		},
	};

	it( 'getFieldStyles returns correct styles for inputs', () => {
		jest.spyOn( document, 'querySelector' ).mockImplementation( () => {
			return mockElement;
		} );
		jest.spyOn( window, 'getComputedStyle' ).mockImplementation( () => {
			return mockCSStyleDeclaration;
		} );

		const fieldStyles = upeStyles.getFieldStyles(
			'.woocommerce-checkout .form-row input',
			'.Input'
		);
		expect( fieldStyles ).toEqual( {
			backgroundColor: 'rgba(0, 0, 0, 0)',
			color: 'rgb(109, 109, 109)',
			fontFamily:
				'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
			outline: '1px solid rgb(150, 88, 138)',
		} );
	} );

	it( 'getFieldStyles returns empty object if it can not find the element', () => {
		jest.spyOn( document, 'querySelector' ).mockImplementation( () => {
			return undefined;
		} );

		const fieldStyles = upeStyles.getFieldStyles(
			'.i-do-not-exist',
			'.Input'
		);
		expect( fieldStyles ).toEqual( {} );
	} );

	it( 'getFontRulesFromPage returns font rules from allowed font providers', () => {
		const mockStyleSheets = {
			length: 3,
			0: {
				href:
					'https://not-supported-fonts-domain.com/style.css?ver=1.1.1',
			},
			1: { href: null },
			2: {
				href:
					// eslint-disable-next-line max-len
					'https://fonts.googleapis.com/css?family=Source+Sans+Pro%3A400%2C300%2C300italic%2C400italic%2C600%2C700%2C900&subset=latin%2Clatin-ext&ver=3.6.0',
			},
		};
		jest.spyOn( document, 'styleSheets', 'get' ).mockReturnValue(
			mockStyleSheets
		);

		const fontRules = upeStyles.getFontRulesFromPage();
		expect( fontRules ).toEqual( [
			{
				cssSrc:
					// eslint-disable-next-line max-len
					'https://fonts.googleapis.com/css?family=Source+Sans+Pro%3A400%2C300%2C300italic%2C400italic%2C600%2C700%2C900&subset=latin%2Clatin-ext&ver=3.6.0',
			},
		] );
	} );

	it( 'getFontRulesFromPage returns empty array if there are no fonts from allowed providers', () => {
		const mockStyleSheets = {
			length: 2,
			0: {
				href:
					'https://not-supported-fonts-domain.com/style.css?ver=1.1.1',
			},
			1: { href: null },
		};
		jest.spyOn( document, 'styleSheets', 'get' ).mockReturnValue(
			mockStyleSheets
		);

		const fontRules = upeStyles.getFontRulesFromPage();
		expect( fontRules ).toEqual( [] );
	} );

	it( 'getAppearance returns the object with filtered CSS rules for UPE theming', () => {
		jest.spyOn( document, 'querySelector' ).mockImplementation( () => {
			return mockElement;
		} );
		jest.spyOn( window, 'getComputedStyle' ).mockImplementation( () => {
			return mockCSStyleDeclaration;
		} );

		const appearance = upeStyles.getAppearance();
		expect( appearance ).toEqual( {
			rules: {
				'.Input': {
					backgroundColor: 'rgba(0, 0, 0, 0)',
					color: 'rgb(109, 109, 109)',
					fontFamily:
						'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
					outline: '1px solid rgb(150, 88, 138)',
				},
				'.Input--invalid': {
					backgroundColor: 'rgba(0, 0, 0, 0)',
					color: 'rgb(109, 109, 109)',
					fontFamily:
						'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
					outline: '1px solid rgb(150, 88, 138)',
				},
				'.Input:focus': {
					backgroundColor: 'rgba(0, 0, 0, 0)',
					color: 'rgb(109, 109, 109)',
					fontFamily:
						'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
					outline: '1px solid rgb(150, 88, 138)',
				},
				'.Label': {
					color: 'rgb(109, 109, 109)',
					fontFamily:
						'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
				},
				'.Tab': {
					backgroundColor: 'rgba(0, 0, 0, 0)',
					color: 'rgb(109, 109, 109)',
					fontFamily:
						'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
				},
				'.Tab:hover': {
					backgroundColor: 'rgba(18, 18, 18, 0)',
					color: 'rgb(255, 255, 255)',
					fontFamily:
						'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
				},
				'.Tab--selected': {
					backgroundColor: 'rgba(0, 0, 0, 0)',
					color: 'rgb(109, 109, 109)',
					outline: '1px solid rgb(150, 88, 138)',
				},
				'.TabIcon:hover': {
					color: 'rgb(255, 255, 255)',
				},
				'.TabIcon--selected': {
					color: 'rgb(109, 109, 109)',
				},
				'.Text': {
					color: 'rgb(109, 109, 109)',
					fontFamily:
						'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
				},
				'.Text--redirect': {
					color: 'rgb(109, 109, 109)',
					fontFamily:
						'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
				},
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
		} );
	} );
} );
