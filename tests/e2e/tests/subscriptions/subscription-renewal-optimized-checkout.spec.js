import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { api, payments, products, user } from '../../utils';

const {
	emptyCart,
	clickAddToCartButton,
	setupOptimizedCheckout,
	fillOCDetails,
	clickPlaceOrder,
} = payments;

let productId;

const relatedOrdersRow = '.woocommerce-orders-table--orders tbody tr';

/**
 * Locate the real (non-draft) related-order rows for a subscription.
 *
 * We need to ignore draft order, as they can be created when
 * visiting the blocks checkout page.
 *
 * @param {import('@playwright/test').Page} page Playwright page fixture.
 * @returns {import('@playwright/test').Locator} Locator for non-draft order rows.
 */
const renewalOrderRows = ( page ) =>
	page.locator( relatedOrdersRow ).filter( { hasNotText: 'Draft' } );

test.describe( 'Optimized Checkout subscription renewal tests @subscriptions', () => {
	test.beforeAll( async () => {
		productId = await api.create.product( products.subscriptionData() );
	} );

	test.afterAll( async () => {
		await api.deletePost.product( productId );
	} );

	/**
	 * Select the saved payment token and submit the renewal on the given
	 * checkout surface.
	 *
	 * @param {import('@playwright/test').Page} page         Playwright page fixture.
	 * @param {string}                          checkoutType 'blocks' or 'shortcode'.
	 */
	async function completeRenewal( page, checkoutType ) {
		// Note that the selectors and UX are different for blocks and shortcode.
		if ( checkoutType === 'blocks' ) {
			await page.click(
				'input[id^="radio-control-wc-payment-method-saved-tokens-"]'
			);
			await page
				.locator( 'text=Renew subscription' )
				.dispatchEvent( 'click' );
		} else {
			const savedTokenRadio = page.locator(
				'.woocommerce-SavedPaymentMethods-token input[id^="wc-stripe-payment-token-"]'
			);
			await expect( savedTokenRadio ).toBeVisible();
			await savedTokenRadio.click();
			// Classic checkout submit (label is relabelled "Renew
			// subscription" by WC Subscriptions, but the id is stable).
			await expect( page.locator( '#place_order' ) ).toBeEnabled();
			await page.locator( '#place_order' ).click();
		}

		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	}

	/**
	 * Purchase a subscription through the OCS element, then renew it on the
	 * given checkout surface.
	 *
	 * A unique customer per test keeps the initial purchase free of a
	 * pre-existing saved token (which would collapse the new-card OCS
	 * element). Logging in is required so the renewal can reuse the token.
	 *
	 * @param {import('@playwright/test').Page} page         Playwright page fixture.
	 * @param {string}                          checkoutType 'blocks' or 'shortcode'.
	 */
	async function purchaseAndRenew( page, checkoutType ) {
		const randomString = randomUUID();
		const username =
			randomString + '.' + config.get( 'users.customer.username' );

		await api.create.customer( {
			...config.get( 'users.customer' ),
			...config.get( 'addresses.customer' ),
			email: randomString + '+' + config.get( 'users.customer.email' ),
			username,
		} );

		await test.step( 'customer login', async () => {
			await user.login(
				page,
				username,
				config.get( 'users.customer.password' )
			);
		} );

		await test.step( 'customer purchases a subscription through Optimized Checkout', async () => {
			// The initial purchase goes through the OCS payment element,
			// which auto-saves the payment token used by the renewal below.
			await emptyCart( page );
			await page.goto( `?p=${ productId }` );
			await clickAddToCartButton( page );

			await setupOptimizedCheckout( page, checkoutType, {
				skipCartSetup: true,
			} );
			await fillOCDetails(
				page,
				config.get( 'cards.basic' ),
				checkoutType
			);

			await clickPlaceOrder( page );
			await page.waitForURL( '**/checkout/order-received/**' );
			await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
				'Order received'
			);
		} );

		await test.step( 'customer renews the subscription', async () => {
			await page.goto( `/my-account` );
			await page.click( 'text=My Subscription' );

			// Only the initial purchase order is present.
			await expect( renewalOrderRows( page ) ).toHaveCount( 1 );

			// "Renew now" adds the renewal to the cart and redirects to the
			// checkout. Navigate to the desired checkout surface to renew
			// there (the redirect always lands on the blocks checkout).
			await page.click( 'text=Renew now' );
			await page.waitForURL( '**/checkout/' );
			if ( checkoutType === 'shortcode' ) {
				await page.goto( '/checkout-shortcode/' );
			}

			await completeRenewal( page, checkoutType );
		} );

		await test.step( 'renewal appears in the related orders table', async () => {
			await page.goto( `/my-account` );
			await page.click( 'text=My Subscription' );

			// Initial purchase + renewal.
			await expect( renewalOrderRows( page ) ).toHaveCount( 2 );
		} );
	}

	test( 'customer can renew an Optimized Checkout subscription @smoke @blocks', async ( {
		page,
	} ) => {
		await purchaseAndRenew( page, 'blocks' );
	} );

	test( 'customer can renew an Optimized Checkout subscription @smoke @shortcode', async ( {
		page,
	} ) => {
		await purchaseAndRenew( page, 'shortcode' );
	} );
} );
