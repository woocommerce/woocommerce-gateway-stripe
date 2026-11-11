import {
	getSelectedVariationAttributes,
	getSelectedVariationId,
	hasVariationSelectionUi,
	observeBlockifiedVariationSelection,
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

// Blockified Add to Cart + Options template: no selects and no
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

describe( 'ECE product page DOM readers', () => {
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	describe.each( [
		[ 'classic', classicMarkup ],
		[ 'blockified', blockifiedMarkup ],
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
			document.body.innerHTML = markup( {
				variationId: '30',
				color: 'blue',
				logo: 'No',
			} );
			expect( getSelectedVariationId() ).toBe( '30' );
		} );

		it( 'treats a variation id left behind by an incomplete selection as stale', () => {
			document.body.innerHTML = markup( {
				variationId: '30',
				color: 'blue',
			} );
			expect( getSelectedVariationId() ).toBeNull();
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

	describe( 'observeBlockifiedVariationSelection', () => {
		const selectorMarkup = `
			<form class="wp-block-add-to-cart-with-options wc-block-add-to-cart-with-options">
				<div class="wp-block-woocommerce-add-to-cart-with-options-variation-selector">
					<button type="button">Blue</button>
					<select name="attribute_logo"><option value="Yes">Yes</option></select>
				</div>
				<div class="single_variation_wrap">
					<input type="hidden" name="variation_id" value="" />
				</div>
			</form>
		`;

		it( 'returns null when the page has no variation selector', () => {
			document.body.innerHTML = classicMarkup( {} );
			expect(
				observeBlockifiedVariationSelection( jest.fn() )
			).toBeNull();
		} );

		it.each( [
			[ 'click', 'button' ],
			[ 'change', 'select' ],
			[ 'keyup', 'button' ],
		] )(
			'notifies on a %s gesture in the variation selector',
			( eventName, target ) => {
				document.body.innerHTML = selectorMarkup;
				const onChange = jest.fn();
				const handle = observeBlockifiedVariationSelection( onChange );

				document
					.querySelector( target )
					.dispatchEvent( new Event( eventName, { bubbles: true } ) );
				expect( onChange ).toHaveBeenCalledTimes( 1 );

				handle.disconnect();
				document
					.querySelector( target )
					.dispatchEvent( new Event( eventName, { bubbles: true } ) );
				expect( onChange ).toHaveBeenCalledTimes( 1 );
			}
		);
	} );
} );
