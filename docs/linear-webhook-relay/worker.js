/**
 * Cloudflare Worker — Linear → GitHub repository_dispatch relay
 *
 * Receives Linear webhook events and forwards them to GitHub Actions via the
 * repository_dispatch API when a specific label is added to a Linear issue.
 *
 * Required Worker secrets (set via `wrangler secret put <NAME>`):
 *   LINEAR_WEBHOOK_SECRET   — found in Linear › Settings › API › Webhooks
 *   GITHUB_PAT              — a GitHub Personal Access Token with `repo` scope
 *
 * Required Worker vars (set in wrangler.toml or the dashboard):
 *   GITHUB_OWNER            — e.g. "woocommerce"
 *   GITHUB_REPO             — e.g. "woocommerce-gateway-stripe"
 *   LINEAR_TRIGGER_LABEL    — e.g. "create-github-issue"
 */

const GITHUB_DISPATCH_EVENT_TYPE = 'linear_label_added';

export default {
	async fetch( request, env ) {
		if ( request.method !== 'POST' ) {
			return new Response( 'Method not allowed', { status: 405 } );
		}

		const rawBody = await request.text();

		// -------------------------------------------------------------------------
		// 1. Verify the Linear webhook signature (HMAC-SHA256).
		// -------------------------------------------------------------------------
		const signature = request.headers.get( 'linear-signature' );
		if ( ! signature ) {
			return new Response( 'Missing signature', { status: 401 } );
		}

		const isValid = await verifySignature(
			rawBody,
			signature,
			env.LINEAR_WEBHOOK_SECRET
		);
		if ( ! isValid ) {
			return new Response( 'Invalid signature', { status: 401 } );
		}

		// -------------------------------------------------------------------------
		// 2. Parse the payload.
		// -------------------------------------------------------------------------
		let payload;
		try {
			payload = JSON.parse( rawBody );
		} catch {
			return new Response( 'Invalid JSON', { status: 400 } );
		}

		// We only care about IssueLabel (label added) events.
		if ( payload.type !== 'IssueLabel' || payload.action !== 'create' ) {
			return new Response( 'Ignored', { status: 200 } );
		}

		const labelName = payload.data?.label?.name ?? '';
		if (
			labelName.toLowerCase() !==
			( env.LINEAR_TRIGGER_LABEL ?? 'create-github-issue' ).toLowerCase()
		) {
			return new Response( 'Label not matched', { status: 200 } );
		}

		const issue = payload.data?.issue;
		if ( ! issue ) {
			return new Response( 'No issue in payload', { status: 400 } );
		}

		// -------------------------------------------------------------------------
		// 3. Forward to GitHub Actions via repository_dispatch.
		// -------------------------------------------------------------------------
		const dispatchUrl = `https://api.github.com/repos/${ env.GITHUB_OWNER }/${ env.GITHUB_REPO }/dispatches`;

		const dispatchRes = await fetch( dispatchUrl, {
			method: 'POST',
			headers: {
				Accept: 'application/vnd.github+json',
				Authorization: `Bearer ${ env.GITHUB_PAT }`,
				'Content-Type': 'application/json',
				'X-GitHub-Api-Version': '2022-11-28',
			},
			body: JSON.stringify( {
				event_type: GITHUB_DISPATCH_EVENT_TYPE,
				client_payload: {
					identifier: issue.identifier, // e.g. "STRIPE-123"
					title: issue.title,
					description: issue.description ?? '',
					url: issue.url,
				},
			} ),
		} );

		if ( ! dispatchRes.ok ) {
			const text = await dispatchRes.text();
			console.error(
				`GitHub dispatch failed: ${ dispatchRes.status } ${ text }`
			);
			return new Response( 'Failed to dispatch to GitHub', {
				status: 502,
			} );
		}

		return new Response( 'OK', { status: 200 } );
	},
};

/**
 * Verify the HMAC-SHA256 signature Linear sends with every webhook.
 * @param {string} body       Raw request body string.
 * @param {string} signature  Value of the `linear-signature` header.
 * @param {string} secret     The webhook signing secret from Linear.
 */
async function verifySignature( body, signature, secret ) {
	const encoder = new TextEncoder();
	const key = await crypto.subtle.importKey(
		'raw',
		encoder.encode( secret ),
		{ name: 'HMAC', hash: 'SHA-256' },
		false,
		[ 'sign' ]
	);

	const mac = await crypto.subtle.sign( 'HMAC', key, encoder.encode( body ) );
	const hex = [ ...new Uint8Array( mac ) ]
		.map( ( b ) => b.toString( 16 ).padStart( 2, '0' ) )
		.join( '' );

	// Constant-time comparison to prevent timing attacks.
	if ( hex.length !== signature.length ) return false;
	let diff = 0;
	for ( let i = 0; i < hex.length; i++ ) {
		diff |= hex.charCodeAt( i ) ^ signature.charCodeAt( i );
	}
	return diff === 0;
}
