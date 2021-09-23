module.exports = {
	parser: '@babel/eslint-parser',
	extends: [ 'plugin:@woocommerce/eslint-plugin/recommended' ],
	globals: {
		_: false,
		Backbone: false,
		jQuery: false,
		wp: false,
		__PAYMENT_METHOD_FEES_ENABLED: false,
	},
	env: {
		browser: true,
		'jest/globals': true,
		node: true,
	},
	rules: {
		'require-await': 'error',
		'react-hooks/exhaustive-deps': 'error',
		'react-hooks/rules-of-hooks': 'error',
		'react/jsx-curly-brace-presence': [
			'error',
			{ props: 'never', children: 'never' },
		],
		'react/self-closing-comp': [ 'error', { component: true, html: true } ],
		'@woocommerce/dependency-group': 'off',
		'import/no-useless-path-segments': [
			'error',
			{
				noUselessIndex: true,
			},
		],
		'import/order': [
			'error',
			{
				'newlines-between': 'never',
				pathGroups: [
					{
						pattern: 'wcstripe/**',
						group: 'internal',
					},
				],
			},
		],
	},
	settings: {
		react: {
			version: 'detect',
		},
		'import/resolver': { webpack: {} },
		jsdoc: { mode: 'typescript' },
		// List of modules that are externals in our webpack config.
		// This helps the `import/no-extraneous-dependencies` and
		//`import/no-unresolved` rules account for them.
		'import/core-modules': [
			'@woocommerce/blocks-registry',
			'@woocommerce/settings',
			'@wordpress/i18n',
			'@wordpress/is-shallow-equal',
			'@wordpress/element',
			'@wordpress/data',
		],
	},
};
