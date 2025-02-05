import { applyFilters } from '@wordpress/hooks';
import { render } from '@testing-library/react';
import 'wcstripe/express-checkout/compatibility/wc-product-page';

describe( 'ECE product page compatibility', () => {
	describe( 'filters out data when adding item to the cart', () => {
		it( 'single variation form is present', () => {
			function App() {
				return (
					<div className="single_variation_wrap">
						<input name="product_id" defaultValue="123" />
					</div>
				);
			}
			render( <App /> );

			const cartAddItemData = applyFilters(
				'wcstripe.express-checkout.cart-add-item',
				{}
			);

			expect( cartAddItemData ).toStrictEqual( { id: 123 } );
		} );
	} );
} );
