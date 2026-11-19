import { randomUUID } from 'crypto';
import { expect, test } from '@playwright/test';
import config from 'config';
import { api, payments } from '../../utils';
import {
	createFreeTrialProduct,
	fillLinkCardDetails,
	fillLinkPaymentDetails,
	fillLinkShippingAddress,
	getLinkPayButton,
	loginToLink,
	openLinkPopup,
	signUpForLink,
} from './utils';

const {
	addProductToCartFromProductPageBySlug,
	addSubscriptionToCart,
	emptyCart,
	waitForOrderReceivedPage,
} = payments;

// The Store API request the express checkout shipping-address handler makes
// when Link reports an address change.
const UPDATE_CUSTOMER = '/wc/store/v1/cart/update-customer';

let virtualProductId;
let physicalProductId;

// Completing a free-trial purchase with Link exercises the real sandbox Link
// enrollment/login flow, which is slow and depends on the external Link
// service. These live in their own Playwright project/CI job so a slow popup
// can't push the shared `default` job over its timeout and cancel every other
// spec.
test.describe( 'express checkout free trial purchases with Link', () => {
	test.beforeAll( async () => {
		virtualProductId = await createFreeTrialProduct( { virtual: true } );
		physicalProductId = await createFreeTrialProduct( { virtual: false } );
	} );

	test.afterAll( async () => {
		if ( virtualProductId ) {
			await api.deletePost.product( virtualProductId );
		}

		if ( physicalProductId ) {
			await api.deletePost.product( physicalProductId );
		}
	} );

	test.beforeEach( async ( { page } ) => {
		await emptyCart( page );
	} );

	test.describe( 'completing the purchase with Link', () => {
		// The returning-account test depends on the Link account the purchase
		// test enrolls, so a failure must retry the whole group.
		test.describe.configure( { mode: 'serial' } );

		// A unique address per run: Link sandbox keeps accounts around, and an
		// already-enrolled email would flip the signup flow into a login flow.
		const linkEmail = `wc-stripe-link-e2e-${ randomUUID() }@example.com`;

		test( 'completes a free trial purchase with a new Link account @blocks @express-checkout @subscriptions', async ( {
			page,
		} ) => {
			test.setTimeout( 240 * 1000 );
			await addSubscriptionToCart( page, virtualProductId );
			await page.goto( '/checkout' );

			const { content: popup, waitForClose } = await openLinkPopup(
				page,
				true
			);
			await signUpForLink(
				popup,
				linkEmail,
				config.get( 'addresses.customer.billing.phone' )
			);
			await fillLinkPaymentDetails(
				popup,
				config.get( 'cards.basic' ),
				config.get( 'addresses.customer.billing' )
			);

			await Promise.all( [
				waitForClose(),
				getLinkPayButton( popup ).click(),
			] );

			await waitForOrderReceivedPage( page );
		} );

		test( 'keeps the Continue button enabled for a returning Link account with a saved payment method @blocks @express-checkout @subscriptions', async ( {
			page,
		} ) => {
			test.setTimeout( 240 * 1000 );
			await addSubscriptionToCart( page, virtualProductId );
			await page.goto( '/checkout' );

			const { content: popup } = await openLinkPopup( page, true );
			await loginToLink(
				popup,
				linkEmail,
				config.get( 'cards.basic.cvc' )
			);

			// The saved-payment-method sheet of a signed-in Link account with
			// a 0-amount trial cart: the sheet must remain actionable, not
			// show a dead disabled Continue button.
			await expect( popup.getByText( /4242/ ).first() ).toBeVisible( {
				timeout: 60 * 1000,
			} );
			await expect( getLinkPayButton( popup ) ).toBeEnabled();
		} );
	} );

	test.describe( 'shipping-required trial cart with a saved Link shipping address', () => {
		// The trial-cart test depends on the shipping address the purchase
		// test saves to the Link account, so a failure must retry the whole
		// group.
		test.describe.configure( { mode: 'serial' } );

		const linkEmail = `wc-stripe-link-e2e-${ randomUUID() }@example.com`;

		test( 'saves a shipping address by completing a regular purchase @blocks @express-checkout', async ( {
			page,
		} ) => {
			test.setTimeout( 240 * 1000 );
			await addProductToCartFromProductPageBySlug( page, 'beanie' );
			await page.goto( '/checkout' );

			const { content: popup, waitForClose } = await openLinkPopup(
				page,
				true
			);
			await signUpForLink(
				popup,
				linkEmail,
				config.get( 'addresses.customer.billing.phone' )
			);
			await fillLinkShippingAddress(
				popup,
				config.get( 'addresses.customer.shipping' )
			);
			await fillLinkCardDetails( popup, config.get( 'cards.basic' ) );

			await Promise.all( [
				waitForClose(),
				getLinkPayButton( popup ).click(),
			] );

			await waitForOrderReceivedPage( page );
		} );

		test( 'accepts the saved shipping address on a free-trial cart @blocks @express-checkout @subscriptions', async ( {
			page,
		} ) => {
			test.setTimeout( 240 * 1000 );
			await addSubscriptionToCart( page, physicalProductId );
			await page.goto( '/checkout' );

			const { content: popup } = await openLinkPopup( page, true );

			// Logging in makes Link evaluate the saved address, which triggers
			// the express checkout shipping-address handler's Store API round
			// trip. Wait for that to land before asserting, otherwise the
			// assertions can pass against the pre-round-trip state (address not
			// yet rejected) and mask a regression.
			const addressEvaluated = page.waitForResponse(
				( response ) => response.url().includes( UPDATE_CUSTOMER ),
				{ timeout: 60 * 1000 }
			);
			await loginToLink(
				popup,
				linkEmail,
				config.get( 'cards.basic.cvc' )
			);
			await addressEvaluated;

			// The deferred free-trial shipping resolves to the "Pending"
			// placeholder rate, so the sheet shows that instead of the address
			// form once the fix accepts the address.
			await expect(
				popup.getByText( 'Pending', { exact: true } )
			).toBeVisible( { timeout: 60 * 1000 } );
			await expect(
				popup.getByText( 'Unavailable for this purchase' )
			).toBeHidden();
			await expect( getLinkPayButton( popup ) ).toBeEnabled();
		} );
	} );
} );
