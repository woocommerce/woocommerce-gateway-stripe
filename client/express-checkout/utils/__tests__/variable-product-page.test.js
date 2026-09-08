import {
	getSelectedVariationAttributes,
	getSelectedVariationId,
	hasVariationSelectionUi,
	isAddToCartUnavailable,
	isSelectedVariationUnavailable,
} from 'wcstripe/express-checkout/utils/variable-product-page';

// Classic template: `.variations_form` with attribute selects, plus the
// `.single_variation_wrap` hidden inputs written by wc-add-to-cart-variation.js.
const classicMarkup = ( { variationId = '', color = '', logo = '' } ) => `
	<form class="variations_form cart">
		<table class="variations">
			<tr>
				<td>
					<select
						name="attribute_pa_color"
						data-attribute_name="attribute_pa_color"
					>
						<option value=""></option>
						<option value="blue" ${ color === 'blue' ? 'selected' : '' }>Blue</option>
						<option value="red" ${ color === 'red' ? 'selected' : '' }>Red</option>
					</select>
				</td>
			</tr>
			<tr>
				<td>
					<select name="attribute_logo">
						<option value=""></option>
						<option value="Yes" ${ logo === 'Yes' ? 'selected' : '' }>Yes</option>
						<option value="No" ${ logo === 'No' ? 'selected' : '' }>No</option>
					</select>
				</td>
			</tr>
		</table>
		<div class="single_variation_wrap">
			<input type="hidden" name="product_id" value="12" />
			<input type="hidden" name="variation_id" value="${ variationId }" />
		</div>
	</form>
`;

// Blockified Add to Cart + Options template (WC 10.9+): no selects and no
// `.variations_form`; attribute values live in Interactivity-API-bound
// hidden inputs, next to the same `.single_variation_wrap` compat markup.
const blockifiedMarkup = ( { variationId = '', color = '', logo = '' } ) => `
	<form class="wp-block-add-to-cart-with-options wc-block-add-to-cart-with-options">
		<input type="hidden" name="attribute_pa_color" value="${ color }" />
		<input type="hidden" name="attribute_logo" value="${ logo }" />
		<div class="single_variation_wrap">
			<input type="hidden" name="add-to-cart" value="12" />
			<input type="hidden" name="product_id" value="12" />
			<input type="hidden" name="variation_id" value="${ variationId }" />
		</div>
	</form>
`;

// Blockified template as rendered by WC 10.7/10.8: no hidden attribute
// inputs; pills are radio inputs and dropdowns are selects, both named
// `attribute_*` (VariationSelectorAttributeOptions::render_pills/_dropdown).
const blockifiedLegacyMarkup = ( {
	variationId = '',
	color = '',
	logo = '',
} ) => `
	<form class="wp-block-add-to-cart-with-options wc-block-add-to-cart-with-options">
		<div role="radiogroup">
			<label><input type="radio" name="attribute_pa_color" value="blue" ${
				color === 'blue' ? 'checked' : ''
			} />Blue</label>
			<label><input type="radio" name="attribute_pa_color" value="red" ${
				color === 'red' ? 'checked' : ''
			} />Red</label>
		</div>
		<select name="attribute_logo">
			<option value=""></option>
			<option value="Yes" ${ logo === 'Yes' ? 'selected' : '' }>Yes</option>
			<option value="No" ${ logo === 'No' ? 'selected' : '' }>No</option>
		</select>
		<div class="single_variation_wrap">
			<input type="hidden" name="add-to-cart" value="12" />
			<input type="hidden" name="product_id" value="12" />
			<input type="hidden" name="variation_id" value="${ variationId }" />
		</div>
	</form>
`;

describe( 'ECE product page DOM readers', () => {
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	describe.each( [
		[ 'classic', classicMarkup ],
		[ 'blockified (WC 10.9+)', blockifiedMarkup ],
		[ 'blockified (WC 10.7/10.8)', blockifiedLegacyMarkup ],
	] )( '%s template', ( _label, markup ) => {
		it( 'detects the variation selection UI', () => {
			document.body.innerHTML = markup( {} );
			expect( hasVariationSelectionUi() ).toBe( true );
		} );

		it( 'returns no variation id before a variation is resolved', () => {
			document.body.innerHTML = markup( {} );
			expect( getSelectedVariationId() ).toBeNull();
		} );

		it( 'returns no variation id when the input holds "0"', () => {
			document.body.innerHTML = markup( { variationId: '0' } );
			expect( getSelectedVariationId() ).toBeNull();
		} );

		it( 'returns the variation id once resolved', () => {
			document.body.innerHTML = markup( { variationId: '30' } );
			expect( getSelectedVariationId() ).toBe( '30' );
		} );

		it( 'reads an empty selection', () => {
			document.body.innerHTML = markup( {} );
			expect( getSelectedVariationAttributes() ).toStrictEqual( {
				count: 2,
				chosenCount: 0,
				data: {
					attribute_pa_color: '',
					attribute_logo: '',
				},
			} );
		} );

		it( 'reads a partial selection', () => {
			document.body.innerHTML = markup( { color: 'blue' } );
			expect( getSelectedVariationAttributes() ).toStrictEqual( {
				count: 2,
				chosenCount: 1,
				data: {
					attribute_pa_color: 'blue',
					attribute_logo: '',
				},
			} );
		} );

		it( 'reads a complete selection', () => {
			document.body.innerHTML = markup( {
				color: 'blue',
				logo: 'No',
			} );
			expect( getSelectedVariationAttributes() ).toStrictEqual( {
				count: 2,
				chosenCount: 2,
				data: {
					attribute_pa_color: 'blue',
					attribute_logo: 'No',
				},
			} );
		} );
	} );

	it( 'reports no variation UI on non-variable product pages', () => {
		document.body.innerHTML = '<form class="cart"></form>';
		expect( hasVariationSelectionUi() ).toBe( false );
		expect( getSelectedVariationId() ).toBeNull();
		expect( getSelectedVariationAttributes() ).toStrictEqual( {
			count: 0,
			chosenCount: 0,
			data: {},
		} );
	} );

	it( 'detects the variation UI on customized templates missing the compat wrapper', () => {
		document.body.innerHTML = '<form class="variations_form cart"></form>';
		expect( hasVariationSelectionUi() ).toBe( true );
	} );

	describe( 'isAddToCartUnavailable', () => {
		const button = ( classes ) =>
			`<button class="single_add_to_cart_button ${ classes }">Add to cart</button>`;

		it( 'blocks when the add-to-cart button is disabled', () => {
			document.body.innerHTML = button( 'disabled' );
			expect( isAddToCartUnavailable() ).toBe( true );
		} );

		it( 'allows simple products with an enabled button', () => {
			document.body.innerHTML = button( '' );
			expect( isAddToCartUnavailable() ).toBe( false );
		} );

		it( 'blocks variable products until a variation is resolved, even without button classes', () => {
			document.body.innerHTML = button( '' ) + blockifiedMarkup( {} );
			expect( isAddToCartUnavailable() ).toBe( true );
		} );

		it( 'allows variable products once a variation is resolved', () => {
			document.body.innerHTML =
				button( '' ) + blockifiedMarkup( { variationId: '30' } );
			expect( isAddToCartUnavailable() ).toBe( false );
		} );

		it( 'blocks without crashing when the button is missing', () => {
			document.body.innerHTML = blockifiedMarkup( {} );
			expect( isAddToCartUnavailable() ).toBe( true );
		} );

		it( 'blocks when the blockified form reports itself invalid, regardless of variation state', () => {
			document.body.innerHTML = blockifiedMarkup( {
				variationId: '30',
			} ).replace(
				'wc-block-add-to-cart-with-options',
				'wc-block-add-to-cart-with-options is-invalid'
			);
			expect( isAddToCartUnavailable() ).toBe( true );
		} );
	} );

	describe( 'isSelectedVariationUnavailable', () => {
		it( 'reflects the unavailable-combination marker on the classic template', () => {
			// The marker is written by the classic script, which only runs
			// alongside a `.variations_form` — the fixture must include it or
			// the reader correctly takes the blockified branch.
			document.body.innerHTML =
				'<form class="variations_form cart"><button class="single_add_to_cart_button disabled wc-variation-is-unavailable"></button></form>';
			expect( isSelectedVariationUnavailable() ).toBe( true );
		} );

		it( 'infers an unavailable combination on the blockified template (complete selection, no resolved variation)', () => {
			document.body.innerHTML = blockifiedMarkup( {
				color: 'blue',
				logo: 'No',
			} );
			expect( isSelectedVariationUnavailable() ).toBe( true );
		} );

		it( 'does not flag an incomplete blockified selection', () => {
			document.body.innerHTML = blockifiedMarkup( { color: 'blue' } );
			expect( isSelectedVariationUnavailable() ).toBe( false );
		} );

		it( 'does not flag a resolved blockified selection', () => {
			document.body.innerHTML = blockifiedMarkup( {
				color: 'blue',
				logo: 'No',
				variationId: '30',
			} );
			expect( isSelectedVariationUnavailable() ).toBe( false );
		} );

		it( 'defers to the classic marker on the classic template (no inference)', () => {
			// Complete selection, empty variation_id, but no marker class:
			// mid-resolution state on classic must not be flagged.
			document.body.innerHTML = classicMarkup( {
				color: 'blue',
				logo: 'No',
			} );
			expect( isSelectedVariationUnavailable() ).toBe( false );
		} );

		it( 'is false without the marker or without the button', () => {
			document.body.innerHTML =
				'<button class="single_add_to_cart_button"></button>';
			expect( isSelectedVariationUnavailable() ).toBe( false );
			document.body.innerHTML = '';
			expect( isSelectedVariationUnavailable() ).toBe( false );
		} );
	} );
} );
