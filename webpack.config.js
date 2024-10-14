const path = require( 'path' );
const webpack = require( 'webpack' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );

module.exports = {
	...defaultConfig,
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
		splitChunks: undefined,
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new DependencyExtractionWebpackPlugin( {
			injectPolyfill: true,
		} ),
		new webpack.DefinePlugin( {
			__PAYMENT_METHOD_FEES_ENABLED: JSON.stringify(
				process.env.PAYMENT_METHOD_FEES_ENABLED === 'true'
			),
		} ),
	],
	module: {
		...defaultConfig.module,
		rules: [
			...defaultConfig.module.rules,
			{
				test: /\.mjs$/,
				include: /node_modules/,
				type: 'javascript/auto',
			},
		],
	},
	resolve: {
		extensions: [ '.json', '.js', '.jsx', '.mjs' ],
		modules: [ path.join( __dirname, 'client' ), 'node_modules' ],
		alias: {
			wcstripe: path.resolve( __dirname, 'client' ),
		},
	},
	entry: {
		index: './client/blocks/index.js',
		payment_requests_settings:
			'./client/entrypoints/payment-request-settings/index.js',
		upe_classic: './client/classic/upe/index.js',
		upe_blocks: './client/blocks/upe/index.js',
		upe_settings: './client/settings/index.js',
		payment_gateways: './client/entrypoints/payment-gateways/index.js',
		express_checkout: './client/entrypoints/express-checkout/index.js',
	},
};
