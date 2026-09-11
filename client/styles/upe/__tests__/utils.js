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

	// Real values from a stock Blocks checkout at a 16px root font size:
	// input padding 24px top / 8px bottom, label line-height 22px, scale 0.82.
	const BLOCKS_INPUT_PADDING_TOP = '24px';
	const BLOCKS_INPUT_PADDING_BOTTOM = '8px';
	const BLOCKS_FLOATING_LABEL = {
		transform: 'matrix(0.82, 0, 0, 0.82, 0, 0)',
		lineHeight: '22px',
		color: 'rgb(100, 100, 100)',
	};

	it( 'splits the floating label compensation evenly across the input', () => {
		const appearance = makeAppearance( {
			paddingTop: BLOCKS_INPUT_PADDING_TOP,
			paddingBottom: BLOCKS_INPUT_PADDING_BOTTOM,
		} );

		const result = upeUtils.handleAppearanceForFloatingLabel( appearance, {
			...BLOCKS_FLOATING_LABEL,
		} );

		// Stripe centers field content — including the card brand icons — inside
		// .Input, so an uneven split leaves them sitting off-center.
		expect( result.rules[ '.Input' ].paddingTop ).toBe(
			result.rules[ '.Input' ].paddingBottom
		);

		// floor(22 * 0.82) = 18px reserved for the label, plus Stripe's 4px and
		// 1px of offset per side: (24 + 8 - 18 - 4 - 2) / 2 = 4px each.
		expect( result.rules[ '.Input' ].paddingTop ).toBe( '4px' );
		expect( result.rules[ '.Input' ].paddingBottom ).toBe( '4px' );
	} );

	it( 'preserves the total vertical padding so the field height is unchanged', () => {
		const appearance = makeAppearance( {
			paddingTop: BLOCKS_INPUT_PADDING_TOP,
			paddingBottom: BLOCKS_INPUT_PADDING_BOTTOM,
		} );

		const result = upeUtils.handleAppearanceForFloatingLabel( appearance, {
			...BLOCKS_FLOATING_LABEL,
		} );

		const total =
			parseFloat( result.rules[ '.Input' ].paddingTop ) +
			parseFloat( result.rules[ '.Input' ].paddingBottom );

		// Same total the top-heavy split reserved: 1px + 7px.
		expect( total ).toBe( 8 );
	} );

	it( 'leaves the resting label unnudged once padding is balanced', () => {
		const appearance = makeAppearance( {
			paddingTop: BLOCKS_INPUT_PADDING_TOP,
			paddingBottom: BLOCKS_INPUT_PADDING_BOTTOM,
		} );

		const result = upeUtils.handleAppearanceForFloatingLabel( appearance, {
			...BLOCKS_FLOATING_LABEL,
		} );

		// The margin existed only to pull the label back toward center against
		// a top-heavy input; with even padding it would push it low instead.
		expect( 'marginTop' in result.rules[ '.Label' ] ).toBe( false );
	} );

	it( 'clamps to zero rather than emitting negative padding', () => {
		const appearance = makeAppearance( {
			paddingTop: '8px',
			paddingBottom: '8px',
		} );

		const result = upeUtils.handleAppearanceForFloatingLabel( appearance, {
			...BLOCKS_FLOATING_LABEL,
		} );

		// 8 + 8 - 18 - 4 - 2 is negative; a negative padding would be dropped by
		// Stripe and let the field grow taller than the theme's inputs.
		expect( result.rules[ '.Input' ].paddingTop ).toBe( '0px' );
		expect( result.rules[ '.Input' ].paddingBottom ).toBe( '0px' );
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

		// (10 + 12 - 15 - 4 - 2) / 2 = 0.5px on each side.
		expect( result.rules[ '.Input' ].paddingTop ).toBe( '0.5px' );
		expect( result.rules[ '.Input' ].paddingBottom ).toBe( '0.5px' );

		// Balanced padding centers the resting label without a nudge.
		expect( 'marginTop' in result.rules[ '.Label' ] ).toBe( false );

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
		// Padding adjustments still run using the original lineHeight, and
		// 10 + 12 - 20 - 4 - 2 is negative, so both sides clamp to zero.
		expect( result.rules[ '.Input' ].paddingTop ).toBe( '0px' );
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

	// When a value can't be resolved, both padding overrides come off
	// together — a partial adjustment is worse than none.
	describe( 'when an operand cannot be resolved', () => {
		// One object, so a partial adjustment shows all properties at once.
		// The resting label must not receive a margin nudge on fallback paths.
		const overridesOn = ( result ) => ( {
			paddingTop: 'paddingTop' in result.rules[ '.Input' ],
			paddingBottom: 'paddingBottom' in result.rules[ '.Input' ],
			legacyLabelNudge: 'marginTop' in result.rules[ '.Label' ],
		} );

		const NONE = {
			paddingTop: false,
			paddingBottom: false,
			legacyLabelNudge: false,
		};

		it( 'drops the overrides when the label styles are empty', () => {
			// A missed selector gives {} label styles while the input padding
			// is still valid. Without the guard this emits `calc(... - undefined ...)`.
			const appearance = makeAppearance( {
				paddingTop: BLOCKS_INPUT_PADDING_TOP,
				paddingBottom: BLOCKS_INPUT_PADDING_BOTTOM,
			} );

			const result = upeUtils.handleAppearanceForFloatingLabel(
				appearance,
				{}
			);

			expect( overridesOn( result ) ).toEqual( NONE );
		} );

		it( 'drops the overrides when the input styles are empty', () => {
			const appearance = makeAppearance();
			delete appearance.rules[ '.Input' ].paddingTop;
			delete appearance.rules[ '.Input' ].paddingBottom;

			const result = upeUtils.handleAppearanceForFloatingLabel(
				appearance,
				{ ...BLOCKS_FLOATING_LABEL }
			);

			expect( overridesOn( result ) ).toEqual( NONE );
		} );

		it( 'drops the overrides when lineHeight is a keyword and no transform scales it', () => {
			// No transform means no early return — `normal` reaches the padding math.
			const appearance = makeAppearance();

			const result = upeUtils.handleAppearanceForFloatingLabel(
				appearance,
				{ lineHeight: 'normal', color: 'rgb(100, 100, 100)' }
			);

			expect( overridesOn( result ) ).toEqual( NONE );
		} );

		it( 'drops the overrides when the matrix scale is not finite', () => {
			const appearance = makeAppearance();

			const result = upeUtils.handleAppearanceForFloatingLabel(
				appearance,
				{
					transform: 'matrix(foo, 0, 0, 0.75, 0, -10)',
					lineHeight: '20px',
				}
			);

			expect( overridesOn( result ) ).toEqual( NONE );
			expect( result.rules[ '.Label--floating' ] ).not.toHaveProperty(
				'transform'
			);
			// The label still floats, so it keeps its border offset.
			expect( result.rules[ '.Label--floating' ].marginTop ).toBe(
				'3px'
			);
		} );

		it( 'drops the overrides when lineHeight is a keyword and a transform scales it', () => {
			const appearance = makeAppearance();

			const result = upeUtils.handleAppearanceForFloatingLabel(
				appearance,
				{
					transform: 'matrix(0.75, 0, 0, 0.75, 0, -10)',
					lineHeight: 'normal',
				}
			);

			expect( overridesOn( result ) ).toEqual( NONE );
			expect( result.rules[ '.Label--floating' ] ).not.toHaveProperty(
				'transform'
			);
		} );

		it( 'drops the overrides when the transform is not a 2d matrix', () => {
			// Computed transforms are always none, matrix() or matrix3d(). A
			// 3d matrix may scale the label, but this code can't read it.
			const appearance = makeAppearance();

			const result = upeUtils.handleAppearanceForFloatingLabel(
				appearance,
				{
					transform:
						'matrix3d(0.82, 0, 0, 0, 0, 0.82, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1)',
					lineHeight: '20px',
				}
			);

			expect( overridesOn( result ) ).toEqual( NONE );
			expect( result.rules[ '.Label--floating' ] ).not.toHaveProperty(
				'transform'
			);
			// The unreadable transform is discarded, not applied unscaled.
			expect( result.rules[ '.Label--floating' ].lineHeight ).toBe(
				'20px'
			);
		} );

		it( 'drops the overrides when a padding is not in px', () => {
			const appearance = makeAppearance( { paddingTop: '1.5rem' } );

			const result = upeUtils.handleAppearanceForFloatingLabel(
				appearance,
				{ lineHeight: '20px' }
			);

			expect( overridesOn( result ) ).toEqual( NONE );
		} );

		it( 'drops the overrides when paddingTop is absent', () => {
			const appearance = makeAppearance();
			delete appearance.rules[ '.Input' ].paddingTop;

			const result = upeUtils.handleAppearanceForFloatingLabel(
				appearance,
				{ lineHeight: '20px' }
			);

			expect( overridesOn( result ) ).toEqual( NONE );
		} );

		it( 'drops the overrides when paddingBottom is absent', () => {
			const appearance = makeAppearance();
			delete appearance.rules[ '.Input' ].paddingBottom;

			const result = upeUtils.handleAppearanceForFloatingLabel(
				appearance,
				{ lineHeight: '20px' }
			);

			expect( overridesOn( result ) ).toEqual( NONE );
		} );

		it( 'never emits an unusable length', () => {
			// Stripe silently drops values it can't parse, so a bad one is
			// invisible in production. Anything emitted must be a plain px length.
			const cases = [
				[ 'empty label styles', { paddingTop: '24px' }, {} ],
				[ 'keyword lineHeight', {}, { lineHeight: 'normal' } ],
				[
					'calc() padding',
					{ paddingTop: 'calc(1rem + 2px)' },
					{ lineHeight: '20px' },
				],
				[
					'var() padding',
					{ paddingBottom: 'var(--pad)' },
					{ lineHeight: '20px' },
				],
				[
					'rem padding',
					{ paddingTop: '1.5rem' },
					{ lineHeight: '20px' },
				],
			];

			const violations = [];
			cases.forEach( ( [ name, inputOverrides, floatingStyles ] ) => {
				const result = upeUtils.handleAppearanceForFloatingLabel(
					makeAppearance( inputOverrides ),
					floatingStyles
				);

				Object.entries( {
					paddingTop: result.rules[ '.Input' ].paddingTop,
					paddingBottom: result.rules[ '.Input' ].paddingBottom,
					labelMarginTop: result.rules[ '.Label' ].marginTop,
					floatingLabelMarginTop:
						result.rules[ '.Label--floating' ].marginTop,
				} ).forEach( ( [ prop, value ] ) => {
					if (
						value !== undefined &&
						! /^\d+(\.\d+)?px$/.test( value )
					) {
						violations.push( `${ name } → ${ prop }: ${ value }` );
					}
				} );
			} );

			expect( violations ).toEqual( [] );
		} );
	} );
} );
