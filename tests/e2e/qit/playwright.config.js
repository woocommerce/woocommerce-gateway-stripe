'use strict';

import { devices } from '@playwright/test';

const { QIT_SITE_URL, BASE_URL, CI, E2E_WORKERS, E2E_MAX_FAILURES, TIMEOUT } =
	process.env;

// Point the `config` npm module at our test-data directory.
process.env.NODE_CONFIG_DIR = new URL(
	'../test-data',
	import.meta.url
).pathname;

const config = {
	globalSetup: './global-setup.js',
	globalTeardown: './global-teardown.js',

	testDir: '../tests',

	// Increased from 90s to 120s to reduce flakiness with Stripe iframe/modal flow.
	timeout: TIMEOUT ? Number( TIMEOUT ) : 120 * 1000,

	expect: {
		// Increased from 20s to 30s to reduce flakiness with Stripe iframe/modal interactions.
		timeout: 30 * 1000,
	},

	outputDir: '../results/output',

	/* Retry on CI only */
	retries: CI ? 3 : 0,

	workers: E2E_WORKERS ? Number( E2E_WORKERS ) : 5,

	// Reporter to use. See https://playwright.dev/docs/test-reporters
	reporter: [
		[ CI ? 'github' : 'list' ],
		[
			'playwright-ctrf-json-reporter',
			{
				outputDir: './results',
				outputFile: 'ctrf.json',
			},
		],
		[
			'allure-playwright',
			{
				outputFolder: './results/allure',
			},
		],
	],

	maxFailures: E2E_MAX_FAILURES ? Number( E2E_MAX_FAILURES ) : 0,

	use: {
		baseURL: QIT_SITE_URL || BASE_URL,

		stateDir: './results/storage/',

		screenshot: 'only-on-failure',

		trace: 'retain-on-failure',

		video: 'on-first-retry',

		viewport: { width: 1280, height: 720 },

		// Maximum time for individual actions (click, fill, etc.)
		actionTimeout: 15 * 1000,
	},

	projects: [
		{
			name: 'default-setup',
			testMatch: '/default.setup.js',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'default',
			testMatch: '**/*.spec.js',
			testIgnore: [
				'**/acss.spec.js',
				'**/optimized-checkout.spec.js',
				'**/blik.spec.js',
				'**/becs.spec.js',
				'**/isk.spec.js',
			],
			dependencies: [ 'default-setup' ],
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'isk',
			testMatch: '**/isk.spec.js',
			dependencies: [ 'default-setup' ],
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'acss-setup',
			testMatch: '/acss.setup.js',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'acss',
			testMatch: '**/acss.spec.js',
			dependencies: [ 'acss-setup' ],
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'becs-setup',
			testMatch: '/becs.setup.js',
			teardown: 'reset account',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'becs',
			testMatch: '**/becs.spec.js',
			dependencies: [ 'becs-setup' ],
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'oc-setup',
			testMatch: '/optimized-checkout.setup.js',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'optimized-checkout',
			testMatch: '**/optimized-checkout.spec.js',
			dependencies: [ 'oc-setup' ],
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'blik-setup',
			testMatch: '/blik.setup.js',
			teardown: 'reset account',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'blik',
			testMatch: '**/blik.spec.js',
			dependencies: [ 'blik-setup' ],
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'reset account',
			testMatch: '/lpm.teardown.js',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
};

export default config;
