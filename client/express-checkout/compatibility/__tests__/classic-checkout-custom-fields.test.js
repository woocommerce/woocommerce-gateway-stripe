import { applyFilters } from '@wordpress/hooks';
import { getExpressCheckoutData } from 'wcstripe/express-checkout/utils';
import 'wcstripe/express-checkout/compatibility/classic-checkout-custom-fields';

jest.mock( 'wcstripe/express-checkout/utils', () => ( {
	getExpressCheckoutData: jest.fn(),
} ) );

const renderCheckoutForm = ( innerHTML ) => {
	document.body.innerHTML = `<form name="checkout">${ innerHTML }</form>`;
};

const applyExtensionDataFilter = () =>
	applyFilters(
		'wcstripe.express-checkout.cart-place-order-extension-data',
		{}
	);

describe( 'Classic checkout custom fields compatibility', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		getExpressCheckoutData.mockReset();
	} );

	it( 'passes through the extension data when no custom fields are configured', () => {
		getExpressCheckoutData.mockReturnValue( null );
		renderCheckoutForm( '<input name="my_field" value="value" />' );

		expect( applyExtensionDataFilter() ).toStrictEqual( {} );
	} );

	it( 'passes through the extension data when there is no classic checkout form', () => {
		getExpressCheckoutData.mockReturnValue( { my_field: {} } );

		expect( applyExtensionDataFilter() ).toStrictEqual( {} );
	} );

	it( 'collects only the configured fields from the checkout form', () => {
		getExpressCheckoutData.mockReturnValue( { my_field: {} } );
		renderCheckoutForm(
			'<input name="my_field" value="collected" />' +
				'<input name="unconfigured_field" value="ignored" />'
		);

		expect( applyExtensionDataFilter() ).toStrictEqual( {
			'wc-stripe/express-checkout': {
				custom_checkout_data: JSON.stringify( {
					my_field: 'collected',
				} ),
			},
		} );
	} );

	it( 'collects every value of a multi-select field into an array', () => {
		getExpressCheckoutData.mockReturnValue( { my_multi_field: {} } );
		renderCheckoutForm(
			'<input name="my_multi_field[]" value="first" />' +
				'<input name="my_multi_field[]" value="second" />'
		);

		expect( applyExtensionDataFilter() ).toStrictEqual( {
			'wc-stripe/express-checkout': {
				custom_checkout_data: JSON.stringify( {
					my_multi_field: [ 'first', 'second' ],
				} ),
			},
		} );
	} );
} );
