const path = require( 'path' );
const DependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );
const LiveReloadWebpackPlugin = require( '@kooneko/livereload-webpack-plugin' );
const { BundleAnalyzerPlugin } = require( 'webpack-bundle-analyzer' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

// Map runtime-corejs3 polyfill module imports back to native globals.
// Modern browser targets (last 2 evergreen versions per @wordpress/browserslist-config)
// have native support for all of these, and wp-polyfill backstops anything older that WP itself supports.
const corejsToGlobal = {
	'@babel/runtime-corejs3/core-js/url': 'URL',
	'@babel/runtime-corejs3/core-js-stable/url': 'URL',
	'@babel/runtime-corejs3/core-js/url-search-params': 'URLSearchParams',
	'@babel/runtime-corejs3/core-js-stable/url-search-params':
		'URLSearchParams',
	'@babel/runtime-corejs3/core-js/promise': 'Promise',
	'@babel/runtime-corejs3/core-js-stable/promise': 'Promise',
	'@babel/runtime-corejs3/core-js/symbol': 'Symbol',
	'@babel/runtime-corejs3/core-js-stable/symbol': 'Symbol',
	'@babel/runtime-corejs3/core-js-stable/map': 'Map',
	'@babel/runtime-corejs3/core-js-stable/set': 'Set',
};

const defaultConfigOutput = defaultConfig.output;

const isProduction = process.env.NODE_ENV === 'production';

// Exclude jsonpFunction as it is not supported by webpack 5+.
// https://github.com/webpack/webpack.js.org/issues/3942
delete defaultConfigOutput.jsonpFunction;

/**
 * Entry points that run on the storefront, for shoppers.
 *
 * Built without Babel `targets`, so `@babel/transform-runtime` ships a core-js
 * polyfill for every method call it can polyfill. That costs bundle size, but a
 * shopper whose browser can't run checkout is a lost order and can't be asked to
 * upgrade, so these keep the widest runtime support.
 *
 * @type {Object}
 */
const shopperEntries = {
	'upe-classic': './client/classic/upe/index.js',
	'upe-blocks': './client/blocks/upe/index.js',
	'express-checkout': './client/entrypoints/express-checkout/index.js',
};

/**
 * Entry points that only ever run inside wp-admin, for merchants.
 *
 * Built for the browsers WordPress itself supports, which lets
 * `@babel/transform-runtime` skip the core-js polyfills those browsers implement
 * natively.
 *
 * @type {Object}
 */
const adminEntries = {
	'upe-settings': './client/settings/index.js',
	'payment-gateways': './client/entrypoints/payment-gateways/index.js',
	'express-checkout-settings':
		'./client/entrypoints/express-checkout-settings/index.js',
	'amazon-pay-settings': './client/entrypoints/amazon-pay-settings/index.js',
	'link-settings': './client/entrypoints/link-settings/index.js',
	'plugins-page': './client/entrypoints/plugins-page/index.js',
	'command-palette': './client/entrypoints/command-palette/index.js',
};

const babelLoader = require.resolve( 'babel-loader' );
const isBabelLoader = ( useEntry ) => useEntry?.loader === babelLoader;

/**
 * Rewrites one of the default module rules for a given compiler.
 *
 * @param {Object}                  rule         The default rule.
 * @param {Array<string>|undefined} babelTargets Browserslist queries to compile for, or undefined for no targets.
 * @return {Object} The rule to use.
 */
const overrideRule = ( rule, babelTargets ) => {
	if (
		babelTargets &&
		Array.isArray( rule.use ) &&
		rule.use.some( isBabelLoader )
	) {
		return {
			...rule,
			use: rule.use.map( ( useEntry ) =>
				isBabelLoader( useEntry )
					? {
							...useEntry,
							options: {
								...( useEntry?.options || {} ),
								targets: babelTargets,
							},
					  }
					: useEntry
			),
		};
	}

	// If the rule doesn't apply to SCSS files, return the rule as is.
	if ( ! rule.test.test( 'test.scss' ) ) {
		return rule;
	}

	return {
		...rule,
		use: [
			...rule.use.map( ( useEntry ) => {
				if ( useEntry.loader !== require.resolve( 'sass-loader' ) ) {
					return useEntry;
				}

				return {
					...useEntry,
					options: {
						...( useEntry?.options || {} ),
						sassOptions: {
							...( useEntry?.options?.sassOptions || {} ),
							quietDeps: true,
						},
					},
				};
			} ),
		],
	};
};

/**
 * Builds one webpack configuration.
 *
 * Shopper and admin entries are separate compilers rather than one compiler with
 * per-path Babel overrides because they share modules (`client/stripe-utils`,
 * `client/api`, …). Babel transforms a module once per compiler, so a separate
 * compiler is what lets the same shared module be polyfilled for shoppers and left
 * unpolyfilled for admin.
 *
 * @param {Object}                  options              Config options.
 * @param {string}                  options.name         Compiler name, used in stats output and the bundle report filename.
 * @param {Object}                  options.entry        Entry points for this compiler.
 * @param {Array<string>|undefined} options.babelTargets Browserslist queries to compile for, or undefined for no targets.
 * @return {Object} A webpack configuration.
 */
const createConfig = ( { name, entry, babelTargets } ) => ( {
	...defaultConfig,
	name,
	output: {
		...defaultConfigOutput,
		chunkLoadingGlobal: defaultConfig.output.jsonpFunction,
		devtoolModuleFilenameTemplate: 'webpack://[resource-path]',
		// Both compilers emit into the same directory, so webpack's own cleanup
		// would delete whatever the other one wrote. `build:webpack` empties
		// `build/` before running instead.
		clean: false,
	},
	devtool:
		process.env.NODE_ENV === 'production'
			? 'hidden-source-map'
			: defaultConfig.devtool,
	optimization: {
		...defaultConfig.optimization,
		minimizer: [
			...defaultConfig.optimization.minimizer.map( ( plugin ) => {
				if ( plugin.constructor.name === 'TerserPlugin' ) {
					// wp-scripts does not allow to override the Terser minimizer sourceMap option, without this
					// `devtool: 'hidden-source-map'` is not generated for js files.
					plugin.options.sourceMap = true;
				}
				return plugin;
			} ),
		],
		splitChunks: false,
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !==
					'DependencyExtractionWebpackPlugin' &&
				plugin.constructor.name !== 'LiveReloadPlugin'
		),
		new DependencyExtractionWebpackPlugin( {
			injectPolyfill: true,
		} ),
		process.env.BUNDLE_ANALYZE === 'true' &&
			new BundleAnalyzerPlugin( {
				analyzerMode: 'static',
				reportFilename: `../bundle-report-${ name }.html`,
				openAnalyzer: false,
			} ),
		! isProduction &&
			// Both instances share a single server when they share a port, so the
			// two compilers reload the same page without fighting over it.
			new LiveReloadWebpackPlugin( {
				port: process.env.WP_LIVE_RELOAD_PORT || 35729,
			} ),
	],
	module: {
		...defaultConfig.module,
		rules: [
			...defaultConfig.module.rules.map( ( rule ) =>
				overrideRule( rule, babelTargets )
			),
			{
				test: /\.mjs$/,
				include: /node_modules/,
				type: 'javascript/auto',
				resolve: {
					fullySpecified: false,
				},
			},
		],
	},
	resolve: {
		...defaultConfig.resolve,
		extensions: [ '.json', '.js', '.jsx', '.mjs' ],
		modules: [ path.join( __dirname, 'client' ), 'node_modules' ],
		alias: {
			...defaultConfig.resolve.alias,
			wcstripe: path.resolve( __dirname, 'client' ),
			// Swap Babel runtime helpers for the non-corejs equivalents (identical helpers, no polyfills).
			'@babel/runtime-corejs3/helpers': '@babel/runtime/helpers',
		},
	},
	externals: {
		...( defaultConfig.externals || {} ),
		...corejsToGlobal,
	},
	entry,
} );

module.exports = [
	createConfig( { name: 'shopper', entry: shopperEntries } ),
	createConfig( {
		name: 'admin',
		entry: adminEntries,
		babelTargets: require( '@wordpress/browserslist-config' ),
	} ),
];
