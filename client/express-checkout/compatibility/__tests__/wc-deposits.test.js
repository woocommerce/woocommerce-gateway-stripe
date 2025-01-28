import { applyFilters } from '@wordpress/hooks';
import { render } from '@testing-library/react';
import { CheckboxControl } from '@wordpress/components';
import 'wcstripe/express-checkout/compatibility/wc-deposits';

describe( 'ECE WC Deposits compatibility', () => {
	describe( 'cart add item filter', () => {
		it( 'filters out deposit data', () => {
			function App() {
				return (
					<CheckboxControl
						name="wc_deposit_option"
						value="test"
						checked={ true }
					/>
				);
			}
			render( <App /> );

			const productData = {
				name: 'test',
				price: 10,
			};

			const cartAddItemData = applyFilters(
				'wcstripe.express-checkout.cart-add-item',
				productData
			);

			expect( cartAddItemData ).toStrictEqual( {
				name: 'test',
				price: 10,
				wc_deposit_option: 'test',
			} );
		} );
	} );
} );
