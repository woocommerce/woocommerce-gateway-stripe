'use strict';

/* jshint node: true */

import { test as setup } from '@playwright/test';
import wcApi from '@woocommerce/woocommerce-rest-api';
import playwrightConfig from '../config/playwright.config';
import {
	AGENTIC_EXCLUDED_PRODUCT_SKU,
	AGENTIC_OOS_PRODUCT_SKU,
	AGENTIC_PRODUCT_SKU,
} from '../utils/agentic';

setup( 'Seed products for agentic delegated checkout tests', async () => {
	const api = new wcApi( {
		url: playwrightConfig.use.baseURL,
		consumerKey: process.env.CONSUMER_KEY,
		consumerSecret: process.env.CONSUMER_SECRET,
		version: 'wc/v3',
	} );

	// Product creation fails on a duplicate SKU, so this setup must stay
	// idempotent across repeated runs against the same environment.
	const ensureProduct = async ( params ) => {
		const existing = await api.get( 'products', { sku: params.sku } );

		if ( existing.data.length > 0 ) {
			return existing.data[ 0 ].id;
		}

		const created = await api.post( 'products', params );
		return created.data.id;
	};

	await ensureProduct( {
		name: 'Agentic E2E Product',
		type: 'simple',
		regular_price: '24.99',
		sku: AGENTIC_PRODUCT_SKU,
	} );

	await ensureProduct( {
		name: 'Agentic E2E Out of Stock Product',
		type: 'simple',
		regular_price: '24.99',
		sku: AGENTIC_OOS_PRODUCT_SKU,
		stock_status: 'outofstock',
	} );

	// The admin-ui spec excludes this one through the product-edit UI, so it
	// must start each run without the exclusion flag.
	const excludedId = await ensureProduct( {
		name: 'Agentic E2E Excludable Product',
		type: 'simple',
		regular_price: '24.99',
		sku: AGENTIC_EXCLUDED_PRODUCT_SKU,
	} );
	await api.put( `products/${ excludedId }`, {
		meta_data: [
			{ key: '_wc_stripe_agentic_commerce_exclude', value: '' },
		],
	} );
} );
