import { test, expect } from '@playwright/test';
import config from 'config';
import { api, payments } from '../../../utils';

const { waitForOrderReceivedPage } = payments;

let productId;

test.beforeAll( async () => {
	await api.update.paymentGateway( 'cod', { enabled: true } );
	productId = await api.create.product( {
		name: 'Pay for order non-Stripe gateway product',
		type: 'simple',
		regular_price: '10',
		virtual: true,
	} );
} );

test.afterAll( async () => {
	await api.update.paymentGateway( 'cod', { enabled: false } );
	if ( productId ) {
		await api.deletePost.product( productId );
	}
} );

// Stripe's submit handler must leave the shared form alone when another gateway is selected.
test( 'customer can pay for an order with a non-Stripe gateway @shortcode', async ( {
	page,
} ) => {
	const billing = config.get( 'addresses.customer.billing' );
	const order = await api.create.order( {
		status: 'pending',
		set_paid: false,
		billing: {
			first_name: billing.first_name,
			last_name: billing.last_name,
			address_1: billing.address_1,
			city: billing.city,
			state: billing.state_iso,
			postcode: billing.postcode,
			country: billing.country_iso,
			email: billing.email,
			phone: billing.phone,
		},
		line_items: [ { product_id: productId, quantity: 1 } ],
	} );

	await page.goto(
		`/checkout/order-pay/${ order.id }/?pay_for_order=true&key=${ order.order_key }`
	);

	await page.locator( 'label[for="payment_method_cod"]' ).click();
	await page.locator( '#place_order' ).click();

	await waitForOrderReceivedPage( page );
	expect( ( await api.get.order( order.id ) ).status ).toBe( 'processing' );
} );
