'use strict';

/* jshint node: true */

import { test, expect } from '@playwright/test';
import {
	AGENTIC_EXCLUDED_PRODUCT_SKU,
	AGENTIC_PRODUCT_SKU,
	getProductIdBySku,
} from '../../utils/agentic';

const EXCLUDE_CHECKBOX_ID = '_wc_stripe_agentic_commerce_exclude';

// The exclusion test writes state the list-table filter test asserts on, so
// the file must run in order within one worker.
test.describe.configure( { mode: 'serial' } );

test.describe( 'Agentic Commerce admin surfaces', () => {
	let adminPage;

	test.beforeAll( async ( { browser } ) => {
		const adminContext = await browser.newContext( {
			storageState: process.env.ADMINSTATE,
		} );
		adminPage = await adminContext.newPage();
	} );

	test( 'settings tab renders and the feed preview reports catalog counts', async () => {
		await adminPage.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=agentic-commerce'
		);

		await expect(
			adminPage.getByRole( 'heading', { name: 'Agentic Commerce' } )
		).toBeVisible();

		await adminPage.getByRole( 'button', { name: 'Preview feed' } ).click();

		// The preview walks the catalog through the same mapper + validator
		// pipeline as the sync, synchronously, so allow it a moment.
		const stats = adminPage.locator(
			'.wc-stripe-agentic-preview__stat-label'
		);
		await expect( stats.filter( { hasText: 'Included' } ) ).toBeVisible();
		await expect(
			stats.filter( { hasText: 'With errors' } )
		).toBeVisible();
		await expect( stats.filter( { hasText: 'Excluded' } ) ).toBeVisible();

		// At minimum the seeded agentic products are included.
		const includedValue = adminPage
			.locator( '.is-included .wc-stripe-agentic-preview__stat-value' )
			.first();
		const included = Number(
			( await includedValue.textContent() ).replace( /[^0-9]/g, '' )
		);
		expect( included ).toBeGreaterThan( 0 );
	} );

	test( 'a product can be excluded from the sync on the product edit page', async () => {
		const productId = await getProductIdBySku(
			AGENTIC_EXCLUDED_PRODUCT_SKU
		);
		expect( productId ).not.toBeNull();

		await adminPage.goto(
			`/wp-admin/post.php?post=${ productId }&action=edit`
		);

		await adminPage.locator( '.inventory_options' ).click();
		const checkbox = adminPage.locator( `#${ EXCLUDE_CHECKBOX_ID }` );
		await expect( checkbox ).toBeVisible();
		await checkbox.check();

		await adminPage.locator( '#publish' ).click();
		await expect(
			adminPage.locator( '.notice-success' ).first()
		).toBeVisible();

		// Re-open the Inventory tab: persistence, not DOM state.
		await adminPage.locator( '.inventory_options' ).click();
		await expect(
			adminPage.locator( `#${ EXCLUDE_CHECKBOX_ID }` )
		).toBeChecked();
	} );

	test( 'the products list shows sync status and filters excluded products', async () => {
		await adminPage.goto( '/wp-admin/edit.php?post_type=product' );

		await expect(
			adminPage.locator( 'th#wc_stripe_agentic_sync' )
		).toHaveCount( 1 );

		// Filter down to excluded products only.
		const filter = adminPage.locator(
			'select[name="wc_stripe_agentic_sync_status"]'
		);
		await filter.selectOption( 'excluded' );
		await adminPage.locator( '#post-query-submit' ).click();

		await expect(
			adminPage.getByRole( 'link', {
				name: 'Agentic E2E Excludable Product',
				exact: true,
			} )
		).toBeVisible();
		await expect(
			adminPage.getByRole( 'link', {
				name: 'Agentic E2E Product',
				exact: true,
			} )
		).toHaveCount( 0 );
	} );

	test( 'the excluded product row is labeled Excluded in the sync column', async () => {
		const productId = await getProductIdBySku(
			AGENTIC_EXCLUDED_PRODUCT_SKU
		);
		const includedId = await getProductIdBySku( AGENTIC_PRODUCT_SKU );

		await adminPage.goto( '/wp-admin/edit.php?post_type=product' );

		await expect(
			adminPage.locator(
				`#post-${ productId } .column-wc_stripe_agentic_sync`
			)
		).toContainText( 'Excluded' );
		await expect(
			adminPage.locator(
				`#post-${ includedId } .column-wc_stripe_agentic_sync`
			)
		).toContainText( 'Synced' );
	} );
} );
