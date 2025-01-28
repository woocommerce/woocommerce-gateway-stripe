import { render } from '@testing-library/react';
import 'wcstripe/express-checkout/compatibility/wc-product-page';
import { applyFilters } from '@wordpress/hooks';

describe( 'ECE WC Product Page compatibility', () => {
	it( 'Filters out cart add item (single variation)', () => {
		function App() {
			return (
				<div className="single_variation_wrap">
					<input type="text" name="product_id" defaultValue="123" />
				</div>
			);
		}
		render( <App /> );

		const cartAddItemData = applyFilters(
			'wcstripe.express-checkout.cart-add-item',
			{ name: 'test', price: 10 }
		);

		expect( cartAddItemData ).toStrictEqual( {
			name: 'test',
			price: 10,
			id: 123,
		} );
	} );
	it( 'Filters out cart add item (multiple variations)', () => {
		function App() {
			return (
				<form className="variations_form">
					<div className="variations">
						<label htmlFor="foo">foo</label>
						<select name="attribute_foo" id="foo">
							<option>bar</option>
						</select>
					</div>
				</form>
			);
		}
		render( <App /> );

		const cartAddItemData = applyFilters(
			'wcstripe.express-checkout.cart-add-item',
			{ name: 'test', price: 10, variation: [] }
		);

		expect( cartAddItemData ).toStrictEqual( {
			name: 'test',
			price: 10,
			variation: [
				{
					attribute: 'foo',
					value: 'bar',
				},
			],
		} );
	} );
} );
