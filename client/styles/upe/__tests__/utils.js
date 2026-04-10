import tinycolor from 'tinycolor2';
import * as upeUtils from '../utils';

describe( 'UPE Utilities to generate UPE styles', () => {
	it( 'generateHoverColors returns new darker background and colors are readable', () => {
		const hoverColors = upeUtils.generateHoverColors(
			'#333333', //rgb(51,51,51) Storefront place order button background color.
			'#ffffff'
		);
		expect( hoverColors ).toEqual( {
			backgroundColor: 'rgb(33, 33, 33)', // A darker color
			color: 'rgb(255, 255, 255)',
		} );
		expect(
			tinycolor.isReadable(
				hoverColors.backgroundColor,
				hoverColors.color
			)
		).toBe( true );
	} );

	it( 'generateHoverColors returns lighter background when brigthness < 50 and colors are readable', () => {
		const hoverColors = upeUtils.generateHoverColors(
			'rgb(40, 48, 61)', // 2021 place order button background color.
			'rgb(209, 228, 221)'
		);
		expect( hoverColors ).toEqual( {
			backgroundColor: 'rgb(54, 65, 83)', // A lighter color
			color: 'rgb(209, 228, 221)',
		} );
		expect(
			tinycolor.isReadable(
				hoverColors.backgroundColor,
				hoverColors.color
			)
		).toBe( true );
	} );

	it( 'generateHoverColors returns readable colors using fallbacks', () => {
		let hoverColors = upeUtils.generateHoverColors(
			'#333333',
			'#333333' // Unreadable
		);
		expect( hoverColors ).toEqual( {
			backgroundColor: 'rgb(33, 33, 33)',
			color: 'rgb(255, 255, 255)', //Returns white
		} );
		expect(
			tinycolor.isReadable(
				hoverColors.backgroundColor,
				hoverColors.color
			)
		).toBe( true );

		hoverColors = upeUtils.generateHoverColors(
			'rgb(40, 48, 61)',
			'rgb(40, 48, 61)' // Unreadable
		);
		expect( hoverColors ).toEqual( {
			backgroundColor: 'rgb(54, 65, 83)',
			color: 'rgb(255, 255, 255)', //Returns white
		} );
		expect(
			tinycolor.isReadable(
				hoverColors.backgroundColor,
				hoverColors.color
			)
		).toBe( true );

		hoverColors = upeUtils.generateHoverColors(
			'rgb(209, 228, 221)',
			'rgb(209, 228, 221)' // Unreadable
		);
		expect( hoverColors ).toEqual( {
			backgroundColor: 'rgb(186, 215, 204)',
			color: 'rgb(0, 0, 0)', //Returns black
		} );
		expect(
			tinycolor.isReadable(
				hoverColors.backgroundColor,
				hoverColors.color
			)
		).toBe( true );
	} );

	it( 'generateHoverColors returns empty colors if provided colors are not valid', () => {
		const hoverColors = upeUtils.generateHoverColors(
			'notacolor',
			'rgb(209, 228, 221)'
		);
		expect( hoverColors ).toEqual( {
			backgroundColor: '',
			color: '',
		} );
	} );
} );

describe( 'handleAppearanceForFloatingLabel', () => {
	const makeAppearance = ( inputOverrides = {}, labelOverrides = {} ) => ( {
		rules: {
			'.Input': {
				paddingTop: '10px',
				paddingBottom: '12px',
				color: 'rgb(0, 0, 0)',
				...inputOverrides,
			},
			'.Label': {
				color: 'rgb(0, 0, 0)',
				...labelOverrides,
			},
		},
	} );

	it( 'adjusts padding and label styles with a valid transform matrix', () => {
		const appearance = makeAppearance();
		const floatingStyles = {
			transform: 'matrix(0.75, 0, 0, 0.75, 0, -10)',
			lineHeight: '20px',
			color: 'rgb(100, 100, 100)',
		};

		const result = upeUtils.handleAppearanceForFloatingLabel(
			appearance,
			floatingStyles
		);

		// Scale = (0.75 + 0.75) / 2 = 0.75, newLineHeight = floor(20 * 0.75) = 15
		expect( result.rules[ '.Label--floating' ].lineHeight ).toBe( '15px' );
		expect( result.rules[ '.Label--floating' ].fontSize ).toBe( '15px' );
		expect( result.rules[ '.Label--floating' ] ).not.toHaveProperty(
			'transform'
		);

		// paddingTop = calc(10px - 15px - 4px - 1px)
		expect( result.rules[ '.Input' ].paddingTop ).toBe(
			'calc(10px - 15px - 4px - 1px)'
		);

		// paddingBottom = 12 - 1 = 11px
		expect( result.rules[ '.Input' ].paddingBottom ).toBe( '11px' );

		// Label marginTop = floor((12 - 1) / 3) = 3px
		expect( result.rules[ '.Label' ].marginTop ).toBe( '3px' );

		// Floating label marginTop set to 3px for border spacing.
		expect( result.rules[ '.Label--floating' ].marginTop ).toBe( '3px' );
	} );

	it( 'parses matrix transform without spaces between values', () => {
		const appearance = makeAppearance();
		const floatingStyles = {
			transform: 'matrix(0.75,0,0,0.75,0,-10)',
			lineHeight: '20px',
			color: 'rgb(100, 100, 100)',
		};

		const result = upeUtils.handleAppearanceForFloatingLabel(
			appearance,
			floatingStyles
		);

		// Same result as the spaced variant.
		expect( result.rules[ '.Label--floating' ].lineHeight ).toBe( '15px' );
		expect( result.rules[ '.Label--floating' ].fontSize ).toBe( '15px' );
		expect( result.rules[ '.Label--floating' ] ).not.toHaveProperty(
			'transform'
		);
	} );

	it( 'returns early when matrix scale components are non-finite', () => {
		const appearance = makeAppearance();
		const floatingStyles = {
			transform: 'matrix(foo, 0, 0, 0.75, 0, -10)',
			lineHeight: '20px',
		};

		const result = upeUtils.handleAppearanceForFloatingLabel(
			appearance,
			floatingStyles
		);

		// Early return path: transform removed, no padding/margin adjustments applied.
		expect( result.rules[ '.Label--floating' ] ).not.toHaveProperty(
			'transform'
		);
		expect( result.rules[ '.Input' ].paddingTop ).toBe( '10px' );
		expect( result.rules[ '.Input' ].paddingBottom ).toBe( '12px' );
		expect( 'marginTop' in result.rules[ '.Label' ] ).toBe( false );
	} );

	it( 'skips transform processing when transform is absent', () => {
		const appearance = makeAppearance();
		const floatingStyles = {
			lineHeight: '20px',
			color: 'rgb(100, 100, 100)',
		};

		const result = upeUtils.handleAppearanceForFloatingLabel(
			appearance,
			floatingStyles
		);

		expect( result.rules[ '.Label--floating' ].lineHeight ).toBe( '20px' );
		expect( result.rules[ '.Label--floating' ] ).not.toHaveProperty(
			'fontSize'
		);
		// Padding adjustments still run using original lineHeight.
		expect( result.rules[ '.Input' ].paddingTop ).toBe(
			'calc(10px - 20px - 4px - 1px)'
		);
	} );

	it( 'skips transform processing when transform is none', () => {
		const appearance = makeAppearance();
		const floatingStyles = {
			transform: 'none',
			lineHeight: '20px',
		};

		const result = upeUtils.handleAppearanceForFloatingLabel(
			appearance,
			floatingStyles
		);

		// Transform block skipped — lineHeight unchanged.
		expect( result.rules[ '.Label--floating' ].lineHeight ).toBe( '20px' );
		expect( result.rules[ '.Label--floating' ].transform ).toBe( 'none' );
	} );

	it( 'skips matrix block when transform is not a matrix', () => {
		const appearance = makeAppearance();
		const floatingStyles = {
			transform: 'translateY(-10px)',
			lineHeight: '20px',
		};

		const result = upeUtils.handleAppearanceForFloatingLabel(
			appearance,
			floatingStyles
		);

		// Matrix regex doesn't match — transform deleted but lineHeight unchanged.
		expect( result.rules[ '.Label--floating' ] ).not.toHaveProperty(
			'transform'
		);
		expect( result.rules[ '.Label--floating' ].lineHeight ).toBe( '20px' );
	} );

	it( 'returns early when lineHeight is NaN', () => {
		const appearance = makeAppearance();
		const floatingStyles = {
			transform: 'matrix(0.75, 0, 0, 0.75, 0, -10)',
			lineHeight: 'normal',
		};

		const result = upeUtils.handleAppearanceForFloatingLabel(
			appearance,
			floatingStyles
		);

		// NaN guard triggers early return — padding unchanged.
		expect( result.rules[ '.Input' ].paddingTop ).toBe( '10px' );
		expect( result.rules[ '.Label--floating' ] ).not.toHaveProperty(
			'transform'
		);
	} );

	it( 'skips paddingTop adjustment when paddingTop is absent', () => {
		const appearance = makeAppearance();
		delete appearance.rules[ '.Input' ].paddingTop;
		const floatingStyles = {
			lineHeight: '20px',
		};

		const result = upeUtils.handleAppearanceForFloatingLabel(
			appearance,
			floatingStyles
		);

		expect( 'paddingTop' in result.rules[ '.Input' ] ).toBe( false );
		// paddingBottom adjustment still runs.
		expect( result.rules[ '.Input' ].paddingBottom ).toBe( '11px' );
	} );

	it( 'skips paddingBottom adjustment when paddingBottom is absent', () => {
		const appearance = makeAppearance();
		delete appearance.rules[ '.Input' ].paddingBottom;
		const floatingStyles = {
			lineHeight: '20px',
		};

		const result = upeUtils.handleAppearanceForFloatingLabel(
			appearance,
			floatingStyles
		);

		expect( 'paddingBottom' in result.rules[ '.Input' ] ).toBe( false );
		// paddingTop adjustment still runs.
		expect( result.rules[ '.Input' ].paddingTop ).toBe(
			'calc(10px - 20px - 4px - 1px)'
		);
		// Label marginTop not adjusted without paddingBottom.
		expect( 'marginTop' in result.rules[ '.Label' ] ).toBe( false );
	} );
} );
