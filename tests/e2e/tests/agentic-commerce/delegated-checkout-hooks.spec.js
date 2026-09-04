'use strict';

/* jshint node: true */

import { test, expect } from '@playwright/test';
import {
	AGENTIC_OOS_PRODUCT_SKU,
	AGENTIC_PRODUCT_SKU,
	WEBHOOK_SECRET,
	customizeCheckoutEvent,
	finalizeCheckoutEvent,
	getConnectedAccountId,
	postAgenticHook,
} from '../../utils/agentic';

test.describe( 'Agentic delegated checkout hooks', () => {
	let accountId;

	test.beforeAll( async () => {
		// The handler drops events whose `context` does not match the cached
		// connected account, and passes everything through when the account is
		// unknown — so read the real id, and only fall back to a dummy when
		// the store has no cached account data.
		accountId = ( await getConnectedAccountId() ) || 'acct_e2e_unknown';
	} );

	test( 'customize_checkout returns line item taxes and shipping options', async ( {
		request,
	} ) => {
		const response = await postAgenticHook(
			request,
			customizeCheckoutEvent( {
				skuId: AGENTIC_PRODUCT_SKU,
				accountId,
			} ),
			WEBHOOK_SECRET
		);

		expect( response.status() ).toBe( 200 );
		const body = await response.json();

		expect( body.line_items ).toHaveLength( 1 );
		expect( body.line_items[ 0 ].id ).toBe( 'li_e2e_1' );
		expect( Array.isArray( body.line_items[ 0 ].tax_rates ) ).toBe( true );

		expect( body.shipping_options.length ).toBeGreaterThan( 0 );
		for ( const option of body.shipping_options ) {
			expect( option.shipping_rate_data.fixed_amount.currency ).toBe(
				'usd'
			);
			expect(
				option.shipping_rate_data.metadata.wc_rate_id
			).toBeTruthy();
		}

		const flatRate = body.shipping_options.find(
			( option ) => 'Flat rate' === option.shipping_rate_data.display_name
		);
		expect( flatRate ).toBeTruthy();
		expect( flatRate.shipping_rate_data.fixed_amount.amount ).toBe( 1000 );
	} );

	test( 'finalize_checkout approves a purchasable in-stock line item', async ( {
		request,
	} ) => {
		const response = await postAgenticHook(
			request,
			finalizeCheckoutEvent( {
				skuId: AGENTIC_PRODUCT_SKU,
				accountId,
			} ),
			WEBHOOK_SECRET
		);

		expect( response.status() ).toBe( 200 );
		const body = await response.json();
		expect( body.manual_approval_details.type ).toBe( 'approved' );
	} );

	test( 'finalize_checkout declines an out-of-stock line item', async ( {
		request,
	} ) => {
		const response = await postAgenticHook(
			request,
			finalizeCheckoutEvent( {
				skuId: AGENTIC_OOS_PRODUCT_SKU,
				accountId,
			} ),
			WEBHOOK_SECRET
		);

		expect( response.status() ).toBe( 200 );
		const body = await response.json();
		expect( body.manual_approval_details.type ).toBe( 'declined' );
		expect( body.manual_approval_details.declined.reason ).toContain(
			'out of stock'
		);
	} );

	test( 'finalize_checkout rejects an unknown SKU with a 400', async ( {
		request,
	} ) => {
		// Product resolution failure throws inside the handler, which converts
		// any Throwable from hook processing into a 400 (not a decline).
		const response = await postAgenticHook(
			request,
			finalizeCheckoutEvent( {
				skuId: 'E2E-AGENTIC-NO-SUCH-SKU',
				accountId,
			} ),
			WEBHOOK_SECRET
		);

		expect( response.status() ).toBe( 400 );
	} );

	test( 'a tampered signature is rejected', async ( { request } ) => {
		// The Docker E2E environment sets E2E_TESTING=true, which makes
		// validate_request() skip signature verification in test mode — this
		// test only means something against an environment without the bypass.
		test.skip(
			!! process.env.DOCKER,
			'Signature validation is bypassed when E2E_TESTING is set.'
		);

		const response = await postAgenticHook(
			request,
			customizeCheckoutEvent( {
				skuId: AGENTIC_PRODUCT_SKU,
				accountId,
			} ),
			'whsec_wrong_secret'
		);

		// Bad signatures are acked with a 204 so Stripe stops retrying; hook
		// output must not be returned.
		expect( response.status() ).toBe( 204 );
	} );
} );
