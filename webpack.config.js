const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );

module.exports = {
	...defaultConfig,
	optimization: {
		...defaultConfig.optimization,
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
	],
	resolve: {
		extensions: [ '.json', '.js', '.jsx' ],
		modules: [ path.join( __dirname, 'client' ), 'node_modules' ],
		alias: {
			wcstripe: path.resolve( __dirname, 'client' ),
		},
	},
	entry: {
		index: './client/blocks/index.js',
		payment_requests_customizer:
			'./client/settings/payment-requests/index.js',
		upe_classic: './client/classic/upe/index.js',
		upe_onboarding_wizard: './client/upe-onboarding-wizard/index.js',
		upe_opt_in_banner: './client/entrypoints/upe-opt-in-banner/index.js',
		upe_settings: './client/settings/index.js',
	},
};
