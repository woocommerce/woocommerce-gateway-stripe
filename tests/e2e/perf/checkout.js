import { browser } from 'k6/browser';
import { Trend } from 'k6/metrics';

const URL = 'http://localhost:8088';

const config = JSON.parse( open( 'default.json' ) );

function get( obj, path ) {
	return path
		.split( '.' )
		.reduce( ( o, p ) => ( o ? o[ p ] : undefined ), obj );
}

export const options = {
	scenarios: {
		ui: {
			executor: 'shared-iterations',
			options: {
				browser: {
					type: 'chromium',
				},
			},
		},
	},
};

export async function gotoCheckout( page, billingDetails = null ) {
	await page.goto( URL + '/checkout-shortcode/' );

	// Fill in the billing details if provided
}

async function emptyCart( page ) {
	await page.goto( URL + '/cart' );

	// Remove products if they exist
	if ( null !== ( await page.$$( '.remove' ) ) ) {
		let products = await page.$$( '.remove' );
		while ( products && 0 < products.length ) {
			for ( const product of products ) {
				await product.click();
			}
			products = await page.$$( '.remove' );
		}
	}

	// Remove coupons if they exist
	if ( null !== ( await page.$( '.woocommerce-remove-coupon' ) ) ) {
		await page.click( '.woocommerce-remove-coupon' );
	}
}

async function setupCart(
	page,
	lineItems = [ [ get( config, 'products.simple.name' ), 1 ] ]
) {
	const cartItemsCounter = '.cart-contents .count';

	await page.goto( URL + '/shop/' );

	// Add items to the cart
	for ( const line of lineItems ) {
		let [ productTitle, qty ] = line;

		while ( qty-- ) {
			const addToCartXPath =
				`//li[contains(@class, "type-product") and a/h2[contains(text(), "${ productTitle }")]]` +
				'//a[contains(@class, "add_to_cart_button") and contains(@class, "ajax_add_to_cart")';
			await page.waitForSelector( `xpath=${ addToCartXPath }]` );
			await page.click( `xpath=${ addToCartXPath }]` );
			await page.waitForSelector(
				`xpath=${ addToCartXPath } and contains(@class, "added")]`
			);
		}
	}
}

/**
 * Fills in the credit card details on the shortcode checkout page.
 * @param {Page} page Playwright page fixture.
 * @param {Object} card The CC info in the format provided on the test-data.
 */
async function fillCreditCardDetailsShortcode( page, card ) {
	const frameHandle = await page.waitForSelector(
		'.payment_method_stripe #wc-stripe-upe-form .wc-stripe-upe-element iframe'
	);

	await page
		.locator(
			'.payment_method_stripe #wc-stripe-upe-form .wc-stripe-upe-element iframe'
		)
		.scrollIntoViewIfNeeded();

	const stripeFrame = await frameHandle.contentFrame();

	await stripeFrame.fill( '[name="number"]', card.number );
	await stripeFrame.fill(
		'[name="expiry"]',
		card.expires.month + card.expires.year
	);
	await stripeFrame.fill( '[name="cvc"]', card.cvc );
}

const trend = new Trend( 'total_action_time', true );

export default async function () {
	const page = await browser.newPage();

	try {
		await page.goto( 'http://localhost:8088' );
		await page.evaluate( () => window.performance.mark( 'page-visit' ) );

		await emptyCart( page );
		await setupCart( page );
		await gotoCheckout( page, get( config, 'addresses.customer.billing' ) );
		// await fillCreditCardDetailsShortcode( page, get( config, 'cards.basic' ) );

		// const totalActionTime = await page.evaluate(
		// 	() =>
		// 		JSON.parse(
		// 			JSON.stringify(
		// 				window.performance.getEntriesByName(
		// 					'total_action_time'
		// 				)
		// 			)
		// 		)[ 0 ].duration
		// );

		// trend.add( totalActionTime );
	} finally {
		await page.close();
	}
}
