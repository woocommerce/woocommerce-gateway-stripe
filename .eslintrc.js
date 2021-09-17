module.exports = {
	extends: [ 'plugin:@woocommerce/eslint-plugin/recommended' ],
	globals: {
		_: false,
		Backbone: false,
		jQuery: false,
		wp: false,
		__PAYMENT_METHOD_FEES_ENABLED: false,
	},
	settings: {
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
