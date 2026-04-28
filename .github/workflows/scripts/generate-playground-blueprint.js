const generatePlaygroundBlueprint = ( runId, prNumber, devToolsAvailable ) => {
	const steps = [
		{
			step: 'installPlugin',
			pluginData: {
				resource: 'wordpress.org/plugins',
				slug: 'woocommerce',
			},
			options: {
				activate: true,
			},
		},
		{
			step: 'installPlugin',
			pluginData: {
				resource: 'url',
				/* The plugin proxy helper fetches the artifact produced by the specified workflow in the target GitHub repo.
				In this case, it fetches the `plugins-<runId>` artifact from the "Build plugin and Playground artifacts" workflow in the woocommerce/woocommerce-gateway-stripe repo. */
				url: `https://playground.wordpress.net/plugin-proxy.php?org=woocommerce&repo=woocommerce-gateway-stripe&workflow=Build%20plugin%20and%20Playground%20artifacts&artifact=plugins-${ runId }&pr=${ prNumber }`,
			},
			options: {
				activate: true,
			},
		},
	];

	if ( devToolsAvailable ) {
		steps.push( {
			step: 'installPlugin',
			pluginData: {
				resource: 'url',
				url: `https://playground.wordpress.net/plugin-proxy.php?org=woocommerce&repo=woocommerce-gateway-stripe&workflow=Build%20plugin%20and%20Playground%20artifacts&artifact=dev-tools-${ runId }&pr=${ prNumber }`,
			},
			options: {
				activate: true,
			},
		} );
	}

	steps.push( {
		step: 'login',
		username: 'admin',
		password: 'password',
	} );

	return {
		landingPage: devToolsAvailable
			? '/wp-admin/admin.php?page=wc-stripe-dev'
			: '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe',

		preferredVersions: {
			php: '8.4',
			wp: 'latest',
		},

		phpExtensionBundles: [ 'kitchen-sink' ],

		features: { networking: true },

		steps,
	};
};

async function run( { github, context, core } ) {
	const commentInfo = {
		owner: context.repo.owner,
		repo: context.repo.repo,
		issue_number: context.issue.number,
	};

	const comments = ( await github.rest.issues.listComments( commentInfo ) )
		.data;
	let existingCommentId = null;

	for ( const currentComment of comments ) {
		if (
			currentComment.user.type === 'Bot' &&
			currentComment.body.includes( 'Test using WordPress Playground' )
		) {
			existingCommentId = currentComment.id;
			break;
		}
	}

	const devToolsAvailable = process.env.DEV_TOOLS_AVAILABLE === 'true';
	const blueprint = generatePlaygroundBlueprint(
		context.runId,
		context.issue.number,
		devToolsAvailable
	);

	const url = `https://playground.wordpress.net/#${ JSON.stringify(
		blueprint
	) }`;

	const body = `## Test using WordPress Playground
The changes in this pull request can be previewed and tested using a [WordPress Playground](https://developer.wordpress.org/playground/) instance.

[Test this pull request with WordPress Playground](${ url }).

**Scope:** Playground runs PHP in a browser WASM sandbox, so outbound calls to \`api.stripe.com\` are subject to browser CORS. In practice, account connection, payment-method listing, checkout against Stripe, and webhook delivery will not work end-to-end here. Use the preview to review admin UI, settings shape, and install/activation flow; use a real local environment (\`npm run up\`) for anything that needs to talk to Stripe.

Note that this URL is valid for 30 days from when this comment was last updated. You can update it by closing/reopening the PR or pushing a new commit.
`;

	if ( existingCommentId ) {
		await github.rest.issues.updateComment( {
			owner: commentInfo.owner,
			repo: commentInfo.repo,
			comment_id: existingCommentId,
			body,
		} );
	} else {
		commentInfo.body = body;
		await github.rest.issues.createComment( commentInfo );
	}
}

module.exports = { run };
