// Note it is important to have file named babel.config.js because tests fail if named .babelrc
// :exploding_head: same case here: https://github.com/facebook/jest/issues/9292#issuecomment-625750534
module.exports = {
	ignore: [],
	presets: [
		[
			'@wordpress/babel-preset-default',
			{
				cacheDirectory: false,
				cacheCompression: false,
			},
		],
	],
	plugins: [
		'@emotion',
		[ '@babel/transform-runtime', { corejs: 3 } ],
		'@babel/plugin-proposal-optional-chaining',
		'@babel/plugin-proposal-nullish-coalescing-operator',
	],
	env: {
		production: {
			plugins: [
				[
					'@wordpress/babel-plugin-makepot',
					{
						output:
							'languages/woocommerce-gateway-stripe-client.pot',
					},
				],
			],
		},
	},
};
