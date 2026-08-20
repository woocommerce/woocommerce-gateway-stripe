'use strict';

/* jshint node: true */

import { test, expect } from '@playwright/test';
import {
	AGENTIC_PRODUCT_SKU,
	WEBHOOK_SECRET,
	checkoutSessionCompletedEvent,
	customizeCheckoutEvent,
	getConnectedAccountId,
	getOrdersBySessionId,
	postAgenticHook,
	runDeferredWebhookJobs,
} from '../../utils/agentic';

// Later tests assert on state the first one creates.
test.describe.configure( { mode: 'serial' } );

test.describe( 'Agentic order creation from a completed session', () => {
	// The session-retrieval stub is a mu-plugin installed by run-tests.sh in
	// the Docker environment only.
	test.skip(
		! process.env.DOCKER,
		'The order-creation spec needs the session stub mu-plugin from the Docker E2E environment.'
	);

	let accountId;
	// Unique per run so an order left behind by a previous run can never
	// satisfy the assertions.
	const sessionId = `cs_test_e2e_agentic_${ Date.now() }`;

	test.beforeAll( async () => {
		accountId = ( await getConnectedAccountId() ) || 'acct_e2e_unknown';
	} );

	test( 'a claimed completed session creates a paid order', async ( {
		request,
	} ) => {
		// The customize hook claims the session for this site; without the
		// claim, checkout.session.completed is treated as another site's.
		const claim = await postAgenticHook(
			request,
			customizeCheckoutEvent( {
				skuId: AGENTIC_PRODUCT_SKU,
				accountId,
				sessionId,
			} ),
			WEBHOOK_SECRET
		);
		expect( claim.status() ).toBe( 200 );

		const completed = await postAgenticHook(
			request,
			checkoutSessionCompletedEvent( { sessionId, accountId } ),
			WEBHOOK_SECRET
		);
		expect( completed.ok() ).toBe( true );

		// With no matching order, the webhook defers order creation to a
		// wc_stripe_deferred_webhook job two minutes out; run it now.
		expect( runDeferredWebhookJobs() ).toBeGreaterThan( 0 );

		const orders = await getOrdersBySessionId( sessionId );
		expect( orders ).toHaveLength( 1 );

		const [ order ] = orders;

		expect( order.status ).toBe( 'processing' );
		expect( order.total ).toBe( '34.99' );
		expect( order.shipping_total ).toBe( '10.00' );
		expect( order.billing.email ).toBe( 'agentic-e2e-buyer@example.com' );
		expect( order.transaction_id ).toBe( 'pi_e2e_agentic_order' );
		expect( order.line_items ).toHaveLength( 1 );
		expect( order.line_items[ 0 ].sku ).toBe( AGENTIC_PRODUCT_SKU );
		expect( order.line_items[ 0 ].quantity ).toBe( 1 );
	} );

	test( 'a duplicate completed event does not create a second order', async ( {
		request,
	} ) => {
		const completed = await postAgenticHook(
			request,
			checkoutSessionCompletedEvent( { sessionId, accountId } ),
			WEBHOOK_SECRET
		);
		expect( completed.ok() ).toBe( true );

		// Process anything the duplicate event may have queued, then confirm
		// no second order appeared.
		runDeferredWebhookJobs();
		expect( await getOrdersBySessionId( sessionId ) ).toHaveLength( 1 );
	} );

	test( 'an unclaimed session does not create an order', async ( {
		request,
	} ) => {
		const unclaimedSessionId = `cs_test_e2e_agentic_unclaimed_${ Date.now() }`;

		const completed = await postAgenticHook(
			request,
			checkoutSessionCompletedEvent( {
				sessionId: unclaimedSessionId,
				accountId,
			} ),
			WEBHOOK_SECRET
		);
		expect( completed.ok() ).toBe( true );

		// The deferred job still runs — the claim check inside it is what
		// must reject the session.
		expect( runDeferredWebhookJobs() ).toBeGreaterThan( 0 );
		expect( await getOrdersBySessionId( unclaimedSessionId ) ).toHaveLength(
			0
		);
	} );
} );
