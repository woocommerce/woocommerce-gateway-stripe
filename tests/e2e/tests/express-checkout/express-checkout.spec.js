import { test, expect } from '@playwright/test';
import { payments } from '../../utils';
import { assertLinkModalLoads, getLinkButton, openLinkPopup } from './utils';

const { clickAddToCartButton, getCartTotal } = payments;

const addProductToCart = async ( page, productSlug = 'beanie' ) => {
	// Add a product to the cart
	await page.goto( `/product/${ productSlug }` );

	await clickAddToCartButton( page );
};

/**
 * Select a variation attribute: a native `<select>` on classic themes, a radio
 * chip in the "Add to Cart with Options" block on block themes.
 *
 * @param {import('@playwright/test').Page} page           Playwright page.
 * @param {string}                          attributeLabel The attribute's label (e.g. 'color').
 * @param {string}                          value          The option to pick (e.g. 'Blue').
 */
const selectVariation = async ( page, attributeLabel, value ) => {
	const dropdown = page.getByLabel( attributeLabel, { exact: true } );
	const isSelect =
		( await dropdown.count() ) > 0 &&
		( await dropdown
			.first()
			.evaluate( ( el ) => el.tagName === 'SELECT' ) );
	if ( isSelect ) {
		await dropdown.selectOption( value );
	} else {
		// Scoped to the block so a same-named radio elsewhere can't match.
		await page
			.locator(
				'.wp-block-woocommerce-add-to-cart-with-options-variation-selector'
			)
			.getByRole( 'radio', { name: value, exact: true } )
			.click();
	}
};

const testLink = async ( page, navigateTo, isBlockPage = false ) => {
	await page.goto( navigateTo );
	await assertLinkModalLoads( page, isBlockPage );
};

test.describe( 'customer can use Link express checkout', () => {
	test( 'inside the product page', async ( { page } ) =>
		await testLink( page, '/product/beanie' ) );

	test( 'inside the cart page (classic)', async ( { page } ) => {
		await addProductToCart( page );
		await testLink( page, '/cart-shortcode', false );
	} );

	test( 'inside the checkout page (classic)', async ( { page } ) => {
		await addProductToCart( page );
		await testLink( page, '/checkout-shortcode', false );
	} );

	test( 'waits for a Classic container added after document readiness', async ( {
		page,
	} ) => {
		await addProductToCart( page );
		await page.addInitScript( () => {
			if ( ! location.pathname.includes( '/checkout-shortcode' ) ) {
				return;
			}
			const observer = new MutationObserver( () => {
				const root = document.getElementById(
					'wc-stripe-express-checkout-element'
				);
				if ( root ) {
					window.lateCheckoutContainer = root;
					root.remove();
					observer.disconnect();
				}
			} );
			observer.observe( document, { childList: true, subtree: true } );
		} );
		await page.goto( '/checkout-shortcode' );
		await page.evaluate(
			() => new Promise( ( resolve ) => window.jQuery( resolve ) )
		);
		await expect(
			page.locator( '#wc-stripe-express-checkout-element' )
		).toHaveCount( 0 );
		await page.evaluate( () =>
			document
				.querySelector( 'form.checkout' )
				.prepend( window.lateCheckoutContainer )
		);
		await expect( await getLinkButton( page ) ).toBeVisible();
	} );

	for ( const atomicMoves of [ true, false ] ) {
		test( `rebuilds wallet wrappers with ${
			atomicMoves ? 'preserved' : 'remounted'
		} SDK frames`, async ( { page } ) => {
			if ( ! atomicMoves ) {
				await page.addInitScript( () => {
					delete Element.prototype.moveBefore;
				} );
			}
			await addProductToCart( page );
			const initialUpdate = page.waitForResponse(
				( response ) =>
					response.url().includes( 'wc-ajax=update_order_review' ) &&
					response.ok()
			);
			await page.goto( '/checkout-shortcode' );
			await initialUpdate;
			await expect( await getLinkButton( page ) ).toBeVisible();
			const slot = await page
				.locator( '#wc-stripe-express-checkout-element-link' )
				.elementHandle();
			const frame = await page
				.locator( '#wc-stripe-express-checkout-element-link iframe' )
				.first()
				.elementHandle();
			await frame.evaluate( ( element ) => {
				window.walletFrameReloads = 0;
				element.addEventListener( 'load', () => {
					window.walletFrameReloads++;
				} );
			} );
			expect(
				await page.evaluate( () => typeof Element.prototype.moveBefore )
			).toBe( atomicMoves ? 'function' : 'undefined' );
			const response = page.waitForResponse(
				( r ) =>
					r.url().includes( 'wc-ajax=update_order_review' ) && r.ok()
			);
			await page.locator( '#ship-to-different-address-checkbox' ).check();
			await response;
			await expect
				.poll( () =>
					slot.evaluate( ( element ) => element.isConnected )
				)
				.toBe( false );
			await expect( await getLinkButton( page ) ).toBeVisible();
			if ( atomicMoves ) {
				expect(
					await frame.evaluate( ( element ) => element.isConnected )
				).toBe( true );
				expect(
					await page.evaluate( () => window.walletFrameReloads )
				).toBe( 0 );
			} else {
				// Stripe can retain the iframe node while reloading its document.
				await expect
					.poll(
						async () =>
							! ( await frame.evaluate(
								( element ) => element.isConnected
							) ) ||
							( await page.evaluate(
								() => window.walletFrameReloads > 0
							) )
					)
					.toBe( true );
			}
			await assertLinkModalLoads( page, false );
		} );
	}

	test( 'discovers Link before the first classic checkout recalculation finishes', async ( {
		page,
	} ) => {
		await addProductToCart( page );
		let releaseCheckoutUpdate;
		const checkoutUpdate = new Promise( ( resolve ) => {
			releaseCheckoutUpdate = resolve;
		} );
		// Holding the response makes early discovery independent of server speed.
		await page.route(
			'**/*wc-ajax=update_order_review*',
			async ( route ) => {
				await checkoutUpdate;
				await route.continue();
			}
		);
		const updateRequest = page.waitForRequest( ( request ) =>
			request.url().includes( 'wc-ajax=update_order_review' )
		);
		try {
			await page.goto( '/checkout-shortcode' );
			await updateRequest;
			const linkButton = await getLinkButton( page );
			await expect( linkButton ).toBeVisible();
		} finally {
			releaseCheckoutUpdate();
		}
	} );

	test( 'recovers express checkout after update_order_review fails', async ( {
		page,
	} ) => {
		await addProductToCart( page );
		const initialUpdate = page.waitForResponse(
			( response ) =>
				response.url().includes( 'wc-ajax=update_order_review' ) &&
				response.ok()
		);
		await page.goto( '/checkout-shortcode' );
		await initialUpdate;
		await expect( await getLinkButton( page ) ).toBeVisible();
		await page.route(
			'**/*wc-ajax=update_order_review*',
			( route ) => route.abort( 'failed' ),
			{ times: 1 }
		);
		const failedUpdate = page.waitForEvent( 'requestfailed', {
			predicate: ( request ) =>
				request.url().includes( 'wc-ajax=update_order_review' ),
		} );
		// Use a recalculation control because address text may not mark totals dirty.
		const shippingAddress = page.locator(
			'#ship-to-different-address-checkbox'
		);
		await shippingAddress.setChecked(
			! ( await shippingAddress.isChecked() )
		);
		await failedUpdate;
		const error = page.locator(
			'#wc-stripe-express-checkout-refresh-error'
		);
		await expect( error ).toBeVisible();
		await expect(
			page.locator( '#wc-stripe-express-checkout-element' )
		).toBeHidden();
		const update = page.waitForResponse(
			( response ) =>
				response.url().includes( 'wc-ajax=update_order_review' ) &&
				response.ok()
		);
		await error
			.getByRole( 'button', { name: 'Retry', exact: true } )
			.click();
		await update;
		await expect( error ).toHaveCount( 0 );
		await expect( await getLinkButton( page ) ).toBeVisible();
		await assertLinkModalLoads( page );
	} );

	test( 'inside the cart page (block)', async ( { page } ) => {
		await addProductToCart( page );
		await testLink( page, '/cart', true );
	} );

	test( 'after quantity changes in the cart page (block)', async ( {
		page,
	} ) => {
		await addProductToCart( page );
		await page.goto( '/cart' );

		const linkButton = await getLinkButton( page, true );
		await expect( linkButton ).toBeVisible();
		await expect( linkButton ).toBeEnabled();

		const initialTotal = await getCartTotal( page );
		const quantityInput = page.locator(
			'.wc-block-components-quantity-selector__input'
		);
		const displayedTotal = page
			.locator(
				'.wc-block-components-totals-footer-item .wc-block-components-totals-item__value'
			)
			.last();
		await expect( quantityInput ).toHaveValue( '1' );
		await expect( displayedTotal ).toContainText( initialTotal );

		for ( const quantity of [ '2', '1' ] ) {
			await quantityInput.fill( quantity );
			await quantityInput.blur();
			if ( quantity === '2' ) {
				await expect
					.poll( () => getCartTotal( page ) )
					.not.toBe( initialTotal );
			} else {
				await expect
					.poll( () => getCartTotal( page ) )
					.toBe( initialTotal );
			}
			await expect( displayedTotal ).toContainText(
				await getCartTotal( page )
			);
			await expect( quantityInput ).toBeEnabled();
			await expect( quantityInput ).toHaveValue( quantity );
			await expect( linkButton ).toBeVisible();
			await expect( linkButton ).toBeEnabled();
		}

		// Reloading would reset availability and hide a stale-cache failure.
		const popup = await openLinkPopup( page, true, { maxRetries: 0 } );
		await popup.close();
	} );

	test( 'inside the checkout page (block)', async ( { page } ) => {
		await addProductToCart( page );
		await testLink( page, '/checkout', true );
	} );
} );

test.describe( 'express checkout and variable products', () => {
	test( 'is hidden when no product variation is selected', async ( {
		page,
	} ) => {
		await page.goto( '/product/hoodie' );

		// We want to wait for the express checkout element to be loaded,
		// before asserting that it is hidden. Immedidately asserting that it is hidden
		// might cause the test to pass only because the element is not yet loaded.
		const linkContainer = page.locator(
			'#wc-stripe-express-checkout-element-link iframe[name^="__privateStripeFrame"]'
		);
		await expect( linkContainer ).toHaveCount( 1 );
		await expect( linkContainer ).toBeHidden();
	} );

	test( 'is visible when a product variation is selected', async ( {
		page,
	} ) => {
		await page.goto( '/product/hoodie' );

		const isBlockified =
			( await page
				.locator(
					'.wp-block-woocommerce-add-to-cart-with-options-variation-selector'
				)
				.count() ) > 0;

		await selectVariation( page, 'color', 'Blue' );
		await selectVariation( page, 'Logo', 'Yes' );
		const linkButton = await getLinkButton( page );

		if ( isBlockified ) {
			// The "Add to Cart with Options" block does not fire the jQuery
			// variation events express checkout listens for, so the button
			// never renders there (issue #5811). Pinned so it turns red once
			// PR #5868 lands; flip this to toBeVisible then.
			await expect( linkButton ).toBeHidden();
			return;
		}
		await expect( linkButton ).toBeVisible();
	} );
} );
