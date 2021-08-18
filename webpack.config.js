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
		new DependencyExtractionWebpackPlugin(),
	],
	resolve: {
		extensions: [ '.ts', '.tsx', '.json', '.js', '.jsx' ],
		modules: [
			path.resolve( __dirname, 'client' ),
			path.resolve( __dirname, 'node_modules' ),
		],
	},
	entry: {
		index: './client/blocks/index.js',
		upe_classic: './client/classic/upe/index.js',
		upe_settings: './client/classic/upe/settings/index.js',
	},
};
