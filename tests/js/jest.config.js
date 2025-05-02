// tests/js/jest.config.js

module.exports = {
	testEnvironment: 'jsdom',
	testEnvironmentOptions: {
		browsers: [ 'chrome', 'firefox', 'safari' ],
	},

	preset: '@wordpress/jest-preset-default',
	rootDir: '../../',
	verbose: true,
	moduleDirectories: [ 'node_modules', '<rootDir>/client' ],
	restoreMocks: true,

	transform: {
		'^.+\\.jsx?$': 'babel-jest',
	},
	transformIgnorePatterns: [
		'node_modules/(?!(?:@wordpress|@woocommerce|@emotion)/)',
	],

	moduleNameMapper: {
		'^@wordpress/date$': '<rootDir>/node_modules/@wordpress/date$1',
		// Global mock for wordpress/data but individual tests can override if necessary
		'^@wordpress/data$': '<rootDir>/tests/js/__mocks__/wordpress-data.js',

		// Asset/file mocks
		'\\.(jpg|jpeg|png|gif|eot|otf|webp|svg|ttf|woff|woff2|mp4|webm|wav|mp3|m4a|aac|oga)$':
			'<rootDir>/tests/js/jest-file-mock.js',

		// React aliases
		'^react$': '<rootDir>/node_modules/react',
		'^react-dom$': '<rootDir>/node_modules/react-dom',

		// Alias your client code
		'^wcstripe(.*)$': '<rootDir>/client$1',
	},

	// Patch @wordpress/date before any tests run
	globalSetup: '<rootDir>/tests/js/jest-global-setup.js',

	// Only WP’s own global setup script
	setupFiles: [
		require.resolve(
			'@wordpress/jest-preset-default/scripts/setup-globals.js'
		),
	],

	globals: {
		__PAYMENT_METHOD_FEES_ENABLED: false,
		wc_stripe_express_checkout_params: {},
	},

	setupFilesAfterEnv: [
		require.resolve(
			'@wordpress/jest-preset-default/scripts/setup-test-framework.js'
		),
		'<rootDir>/tests/js/jest-setup.js',
	],

	testPathIgnorePatterns: [
		'/node_modules/',
		'/vendor/',
		'<rootDir>/.*/build/',
		'<rootDir>/.*/build-module/',
		'<rootDir>/docker/',
		'<rootDir>/tests',
	],
};
