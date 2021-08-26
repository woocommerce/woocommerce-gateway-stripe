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
	module: {
		...defaultConfig.module,
		rules: defaultConfig.module.rules.map((rule) => {
			if (rule.test.test('.jsx')) {
				rule.use.push({
					loader: '@linaria/webpack-loader',
					options: {
						sourceMap: process.env.NODE_ENV !== 'production',
					},
				});
			}

			return rule
		}),
	},
	resolve: {
		extensions: [ '.json', '.js', '.jsx' ],
		modules: [ path.join( __dirname, 'client' ), 'node_modules' ],
		alias: {
			wcstripe: path.resolve( __dirname, 'client' ),
		},
	},
	entry: {
		index: './client/blocks/index.js',
		upe_classic: './client/classic/upe/index.js',
		upe_settings: './client/settings/index.js',
		additional_methods_setup: './client/additional-methods-setup/index.js',
	},
};
