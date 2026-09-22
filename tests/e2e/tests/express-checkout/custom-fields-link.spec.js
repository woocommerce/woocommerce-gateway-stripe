import { randomUUID } from 'crypto';
import { expect, test } from '@playwright/test';
import config from 'config';
import { api, payments } from '../../utils';
import {
	fillLinkCardDetails,
	fillLinkShippingAddress,
	openLinkPopup,
	signUpForLink,
} from './utils';

const { clickAddToCartButton, emptyCart, waitForOrderReceivedPage } = payments;

// Set by wc-stripe-e2e-checkout-fields.php: requests carrying the cookie get
// a required classic-API billing field ("E2E custom field").
const CUSTOM_FIELD_COOKIE = 'wc_stripe_e2e_required_custom_field';
const CUSTOM_FIELD_KEY = 'billing_e2e_custom_field';
const CUSTOM_FIELD_LABEL = 'E2E custom field';

test.describe( 'express checkout with a required classic custom checkout field', () => {
	test.beforeEach( async ( { context, page, baseURL } ) => {
		await context.addCookies( [
			{ name: CUSTOM_FIELD_COOKIE, value: '1', url: baseURL },
		] );
		await emptyCart( page );
	} );

	const addProductToCart = async ( page ) => {
		await page.goto( '/product/beanie' );
		await clickAddToCartButton( page );
		await expect(
			page.getByText( 'has been added to your cart' )
		).toBeVisible();
	};

	const payWithNewLinkAccount = async ( page, isBlockPage ) => {
		const popup = await openLinkPopup( page, isBlockPage );
		// A unique address per run: Link sandbox keeps accounts around, and an
		// already-enrolled email would flip the signup flow into a login flow.
		await signUpForLink(
			popup,
			`wc-stripe-link-e2e-${ randomUUID() }@example.com`,
			config.get( 'addresses.customer.billing.phone' )
		);
		await fillLinkShippingAddress(
			popup,
			config.get( 'addresses.customer.shipping' )
		);
		await fillLinkCardDetails( popup, config.get( 'cards.basic' ) );

		await Promise.all( [
			popup.waitForEvent( 'close', { timeout: 90 * 1000 } ),
			popup.getByTestId( 'pay-button' ).click(),
		] );
	};

	test( 'completes the purchase on the block checkout, which cannot render the field @blocks @express-checkout', async ( {
		page,
	} ) => {
		test.setTimeout( 240 * 1000 );
		await addProductToCart( page );
		await page.goto( '/checkout' );

		// The checkout block builds its form from the Additional Checkout
		// Fields API, so the classic-API field must not appear on it.
		await expect( page.locator( `#${ CUSTOM_FIELD_KEY }` ) ).toBeHidden();

		await payWithNewLinkAccount( page, true );
		await waitForOrderReceivedPage( page );
	} );

	test( 'refuses the purchase on the classic checkout while the field is empty @express-checkout', async ( {
		page,
	} ) => {
		test.setTimeout( 240 * 1000 );
		await addProductToCart( page );
		await page.goto( '/checkout-shortcode' );

		await expect( page.locator( `#${ CUSTOM_FIELD_KEY }` ) ).toBeVisible();

		await payWithNewLinkAccount( page, false );

		await expect(
			page.getByText( `${ CUSTOM_FIELD_LABEL } is a required field.` )
		).toBeVisible( { timeout: 30 * 1000 } );
		expect( page.url() ).not.toContain( 'order-received' );
	} );

	test( 'records the field value on the order placed from the classic checkout @express-checkout', async ( {
		page,
	} ) => {
		test.setTimeout( 240 * 1000 );
		const fieldValue = `e2e-value-${ randomUUID() }`;
		await addProductToCart( page );
		await page.goto( '/checkout-shortcode' );

		await page.locator( `#${ CUSTOM_FIELD_KEY }` ).fill( fieldValue );

		await payWithNewLinkAccount( page, false );
		await waitForOrderReceivedPage( page );

		const orderId = page.url().match( /order-received\/(\d+)/ )[ 1 ];
		const order = await api.get.order( orderId );
		expect(
			order.meta_data.find( ( meta ) => meta.key === CUSTOM_FIELD_KEY )
				?.value
		).toBe( fieldValue );
	} );
} );
