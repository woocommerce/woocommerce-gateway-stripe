import { test, expect } from '@playwright/test';
import { payments } from '../../utils';
import { assertLinkModalLoads, getLinkButton, openLinkPopup } from './utils';

const {
	addProductToCartFromProductPageBySlug,
	getCartTotal,
	selectProductVariation,
} = payments;

const addProductToCart = async ( page, productSlug = 'beanie' ) => {
	await addProductToCartFromProductPageBySlug( page, productSlug );
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
		const { close } = await openLinkPopup( page, true, { maxRetries: 0 } );
		await close();
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
		await selectProductVariation( page, 'color', 'Blue' );
		await selectProductVariation( page, 'Logo', 'Yes' );
		const linkButton = await getLinkButton( page );
		await expect( linkButton ).toBeVisible();
	} );

	test( 'becomes visible when a variation is selected after the buttons have loaded', async ( {
		page,
	} ) => {
		await page.goto( '/product/hoodie' );

		// Once Stripe has rendered the button in its still-hidden frame, a selection
		// is only seen through the form's change signals.
		const linkFrame = page.locator(
			'#wc-stripe-express-checkout-element-link iframe[name^="__privateStripeFrame"]'
		);
		await expect(
			linkFrame.contentFrame().getByRole( 'button', {
				name: /Pay (securely )?with Link/,
				includeHidden: true,
			} )
		).toBeAttached();
		await expect( linkFrame ).toBeHidden();

		await selectProductVariation( page, 'color', 'Blue' );
		await selectProductVariation( page, 'Logo', 'Yes' );
		await expect( await getLinkButton( page ) ).toBeVisible();
	} );

	test( 'refreshes for a new quantity, not when the quantity field is left unchanged', async ( {
		page,
	} ) => {
		const isRefresh = ( request ) =>
			request.url().includes( 'wc_stripe_get_selected_product_data' );
		const refreshedQuantities = [];
		page.on( 'request', ( request ) => {
			if ( isRefresh( request ) ) {
				refreshedQuantities.push(
					new URLSearchParams( request.postData() ).get( 'qty' )
				);
			}
		} );

		await page.goto( '/product/hoodie' );
		await selectProductVariation( page, 'color', 'Blue' );
		const selectionRefresh = page.waitForResponse( ( response ) =>
			isRefresh( response.request() )
		);
		await selectProductVariation( page, 'Logo', 'Yes' );
		await selectionRefresh;
		await expect( await getLinkButton( page ) ).toBeVisible();
		const selectionRefreshes = [ ...refreshedQuantities ];
		expect( selectionRefreshes ).toContain( '1' );

		// The blockified form reports a quantity change on every blur.
		const quantityInput = page.getByRole( 'spinbutton', {
			name: 'Product quantity',
		} );
		await quantityInput.focus();
		await quantityInput.blur();
		// No request is expected, so wait out the 250ms refresh debounce.
		await page.waitForTimeout( 1000 );
		expect( refreshedQuantities ).toEqual( selectionRefreshes );

		// Proves the check above can't pass on a field that never refreshes.
		const quantityRefresh = page.waitForResponse( ( response ) =>
			isRefresh( response.request() )
		);
		await quantityInput.fill( '2' );
		await quantityInput.blur();
		await quantityRefresh;
		expect( refreshedQuantities ).toEqual( [ ...selectionRefreshes, '2' ] );
	} );
} );
