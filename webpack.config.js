const path = require( 'path' );
const webpack = require( 'webpack' );
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

module.exports = {
	...defaultConfig,
	output: {
		...defaultConfigOutput,
		chunkLoadingGlobal: defaultConfig.output.jsonpFunction,
		devtoolModuleFilenameTemplate: 'webpack://[resource-path]',
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
		new webpack.DefinePlugin( {
			__PAYMENT_METHOD_FEES_ENABLED: JSON.stringify(
				process.env.PAYMENT_METHOD_FEES_ENABLED === 'true'
			),
		} ),
		process.env.BUNDLE_ANALYZE === 'true' &&
			new BundleAnalyzerPlugin( {
				analyzerMode: 'static',
				reportFilename: '../bundle-report.html',
				openAnalyzer: false,
			} ),
		! isProduction &&
			new LiveReloadWebpackPlugin( {
				port: process.env.WP_LIVE_RELOAD_PORT || 35729,
			} ),
	],
	module: {
		...defaultConfig.module,
		rules: [
			...defaultConfig.module.rules.map( ( rule ) => {
				// If the rule doesn't apply to SCSS files, return the rule as is.
				if ( ! rule.test.test( 'test.scss' ) ) {
					return rule;
				}

				return {
					...rule,
					use: [
						...rule.use.map( ( useEntry ) => {
							if (
								useEntry.loader !==
								require.resolve( 'sass-loader' )
							) {
								return useEntry;
							}

							return {
								...useEntry,
								options: {
									...( useEntry?.options || {} ),
									sassOptions: {
										...( useEntry?.options?.sassOptions ||
											{} ),
										quietDeps: true,
									},
								},
							};
						} ),
					],
				};
			} ),
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
	entry: {
		'upe-classic': './client/classic/upe/index.js',
		'upe-blocks': './client/blocks/upe/index.js',
		'upe-settings': './client/settings/index.js',
		'payment-gateways': './client/entrypoints/payment-gateways/index.js',
		'express-checkout': './client/entrypoints/express-checkout/index.js',
		'express-checkout-settings':
			'./client/entrypoints/express-checkout-settings/index.js',
		'amazon-pay-settings':
			'./client/entrypoints/amazon-pay-settings/index.js',
		'link-settings': './client/entrypoints/link-settings/index.js',
		'plugins-page': './client/entrypoints/plugins-page/index.js',
		'command-palette': './client/entrypoints/command-palette/index.js',
	},
};
