const path = require( 'path' );
const webpack = require( 'webpack' );
const DependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );
const LiveReloadWebpackPlugin = require( '@kooneko/livereload-webpack-plugin' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

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
		},
	},
	entry: {
		index: './client/blocks/index.js',
		'payment-requests-settings':
			'./client/entrypoints/payment-request-settings/index.js',
		'upe-classic': './client/classic/upe/index.js',
		'upe-blocks': './client/blocks/upe/index.js',
		'upe-settings': './client/settings/index.js',
		'payment-gateways': './client/entrypoints/payment-gateways/index.js',
		'express-checkout': './client/entrypoints/express-checkout/index.js',
		'amazon-pay-settings':
			'./client/entrypoints/amazon-pay-settings/index.js',
	},
};
