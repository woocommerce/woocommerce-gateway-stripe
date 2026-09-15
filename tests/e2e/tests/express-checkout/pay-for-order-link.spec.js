import { randomUUID } from 'crypto';
import { expect, test } from '@playwright/test';
import config from 'config';
import { admin, api, payments } from '../../utils';
import { fillLinkPaymentDetails, openLinkPopup, signUpForLink } from './utils';

const { waitForOrderReceivedPage } = payments;

// The order-pay Store API route overwrites the order's saved addresses with
// the wallet payload before validating them, so express checkout on the Pay
// for Order page must ask the wallet for a phone whenever the checkout phone
// field is required — otherwise every wallet payment fails with "Phone is
// required" and the saved billing phone is wiped (STRIPE-1449).
//
// This toggles the store-wide phone-field setting, so it runs in its own
// Playwright project and CI job, away from every other spec.
test.describe( 'express checkout on the pay-for-order page with a required phone field', () => {
	let previousPhoneField;
	let productId;

	test.beforeAll( async ( { browser } ) => {
		previousPhoneField = await admin.updateCheckoutPhoneFieldVisibility(
			browser,
			'required'
		);

		productId = await api.create.product( {
			name: 'Pay for Order Beanie',
			type: 'simple',
			regular_price: '19.99',
		} );
	} );

	test.afterAll( async ( { browser } ) => {
		if ( previousPhoneField ) {
			await admin.updateCheckoutPhoneFieldVisibility(
				browser,
				previousPhoneField
			);
		}

		if ( productId ) {
			await api.deletePost.product( productId );
		}
	} );

	test( 'pays for an order with a saved billing phone using Link @express-checkout', async ( {
		page,
	} ) => {
		test.setTimeout( 240 * 1000 );

		const billing = config.get( 'addresses.customer.billing' );
		const shipping = config.get( 'addresses.customer.shipping' );

		// Mirrors the reported setup: a pending order created in admin with a
		// full billing address including a phone, and no shipping phone — the
		// route validates the shipping phone too, so the client must backfill
		// it rather than send the saved (empty) value.
		const order = await api.create.order( {
			status: 'pending',
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
			shipping: {
				first_name: shipping.first_name,
				last_name: shipping.last_name,
				address_1: shipping.address_1,
				city: shipping.city,
				state: shipping.state_iso,
				postcode: shipping.postcode,
				country: shipping.country_iso,
			},
			line_items: [ { product_id: productId, quantity: 1 } ],
		} );

		await page.goto(
			`/checkout/order-pay/${ order.id }/?pay_for_order=true&key=${ order.order_key }`
		);

		const popup = await openLinkPopup( page, false );
		await signUpForLink(
			popup,
			`wc-stripe-link-e2e-${ randomUUID() }@example.com`,
			billing.phone
		);
		await fillLinkPaymentDetails(
			popup,
			config.get( 'cards.basic' ),
			billing
		);

		await Promise.all( [
			popup.waitForEvent( 'close', { timeout: 90 * 1000 } ),
			popup.getByTestId( 'pay-button' ).click(),
		] );

		await waitForOrderReceivedPage( page );

		const paidOrder = await api.get.order( order.id );
		expect( [ 'processing', 'completed' ] ).toContain( paidOrder.status );
		// The bug also replaced the saved billing phone with an empty value,
		// so the paid order must still carry one.
		expect( paidOrder.billing.phone ).not.toBe( '' );
	} );
} );
