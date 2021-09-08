const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );

module.exports = {
	...defaultConfig,
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
		additional_methods_setup: './client/additional-methods-setup/index.js',
		payment_requests_customizer:
			'./client/settings/payment-requests/index.js',
		index: './client/blocks/index.js',
		upe_classic: './client/classic/upe/index.js',
<<<<<<< HEAD
=======
		upe_settings: './client/settings/index.js',
		upe_onboarding_wizard: './client/upe-onboarding-wizard/index.js',
>>>>>>> develop
		upe_opt_in_banner: './client/settings/upe-opt-in-banner/index.js',
		upe_settings: './client/settings/index.js',
	},
};
