import stripe from 'stripe';
import { test, expect } from '@playwright/test';
import config from 'config';
import { api, payments } from '../../utils';

const {
	emptyCart,
	setupProductCheckout,
	setupCheckout,
	fillCardDetails,
} = payments;

test( 'merchant can issue a full refund @smoke', async ( { browser } ) => {
	let orderId, stripeChargeId, stripeRefundId;

	const adminContext = await browser.newContext( {
		storageState: process.env.ADMINSTATE,
	} );
	const adminPage = await adminContext.newPage();

	const userContext = await browser.newContext();
	const userPage = await userContext.newPage();

	await test.step( 'customer checkout with Stripe', async () => {
		await emptyCart( userPage );

		await setupProductCheckout( userPage );
		await setupCheckout(
			userPage,
			config.get( 'addresses.customer.billing' )
		);

		await fillCardDetails( userPage, config.get( 'cards.basic' ) );
		await userPage.locator( 'text=Place order' ).click();
		await userPage.waitForNavigation();

		await expect( userPage.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);

		const orderUrl = await userPage.url();
		orderId = orderUrl.split( 'order-received/' )[ 1 ].split( '/?' )[ 0 ];

		await userPage.close();
	} );

	await test.step(
		'merchant issue a full refund in the dashboard',
		async () => {
			await adminPage.goto(
				`/wp-admin/post.php?post=${ orderId }&action=edit`
			);

			const order = await api.get.order( orderId );

			// Ensure this isn't already refunded.
			await expect( adminPage.locator( '.order_notes' ) ).not.toHaveText(
				/Refunded .* – Refund ID: .* – Reason:.*/
			);

			stripeChargeId = await adminPage
				.locator( '.woocommerce-order-data__meta a' )
				.innerText();

			await adminPage
				.locator( '#woocommerce-order-items button.refund-items' )
				.click();
			await adminPage.locator( '#refund_amount' ).type( order.total );

			adminPage.on( 'dialog', ( dialog ) => dialog.accept() );
			await adminPage
				.locator( '.refund-actions .button.do-api-refund' )
				.filter( { hasText: /Refund.*via Stripe/ } )
				.click();

			await adminPage.waitForNavigation();

			// Ensure the order status is updated.
			await expect( adminPage.locator( '#order_status' ) ).toHaveValue(
				'wc-refunded'
			);

			// Ensure the refund note is present.
			await expect( adminPage.locator( '.order_notes' ) ).toHaveText(
				/Refunded .* – Refund ID: .* – Reason:.*/
			);

			stripeRefundId = await adminPage
				.locator( '.order_notes' )
				.filter( {
					hasText: /Refunded .* – Refund ID: .* – Reason:.*/,
				} )
				.innerText()
				.then(
					( text ) =>
						text.match( /(?<=Refund ID: ).*?(?= – Reason)/ )[ 0 ]
				);
		}
	);

	await test.step( 'check Stripe payment status ', async () => {
		const stripeClient = stripe( process.env.STRIPE_SECRET_KEY );

		const charge = await stripeClient.charges.retrieve( stripeChargeId );

		expect( charge.refunded ).toBeTruthy();
		expect( charge.refunds.data[ 0 ].id ).toBe( stripeRefundId );
	} );
} );
