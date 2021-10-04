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
