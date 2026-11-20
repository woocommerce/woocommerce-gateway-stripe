import { getSharedStripeInstance } from '../../../stripe-utils/shared-stripe-instance';

jest.mock( '../../../stripe-utils/shared-stripe-instance' );
jest.mock( '../../../stripe-utils/get-stripe-dev-widget-options', () => ( {
	getStripeDevWidgetOptions: () => ( {} ),
} ) );

it( 'passes the localized minor-unit amount to Stripe as a number', () => {
	document.body.innerHTML = '<div class="wc-stripe-bnpl-messaging"></div>';
	window.wc_stripe_upe_params = { key: 'pk_test_example' };
	window.wc_stripe_payment_method_messaging = {
		amount: '29900',
		currency: 'RON',
		paymentMethodTypes: [ 'klarna' ],
	};
	const create = jest.fn( () => ( { mount: jest.fn() } ) );
	getSharedStripeInstance.mockReturnValue( {
		elements: () => ( { create } ),
	} );

	require( '../index' );

	expect( create ).toHaveBeenCalledWith(
		'paymentMethodMessaging',
		expect.objectContaining( { amount: 29900 } )
	);
} );
