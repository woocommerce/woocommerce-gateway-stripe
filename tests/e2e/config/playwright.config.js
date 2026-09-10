'use strict';

/* jshint node: true */

import { devices } from '@playwright/test';
import dotenv from 'dotenv';

dotenv.config( {
	path: `${ process.env.E2E_ROOT }/config/local.env`,
} );

const {
	BASE_URL,
	CI,
	DOCKER,
	E2E_MAX_FAILURES,
	E2E_TRACE,
	E2E_VIDEO,
	E2E_WORKERS,
	TIMEOUT,
} = process.env;

const config = {
	globalSetup: DOCKER ? './global-setup-docker' : './global-setup',
	globalTeardown: './global-teardown',

	testDir: '../tests',

	// Maximum time one test can run for
	// Increased from 90s to 120s to reduce flakiness with Stripe iframe/modal flow.
	timeout: TIMEOUT ? Number( TIMEOUT ) : 120 * 1000,

	expect: {
		// Maximum time expect() should wait for the condition to be met
		// For example in `await expect(locator).toHaveText();`
		// Increased from 20s to 30s to reduce flakiness with Stripe iframe/modal interactions.
		timeout: 30 * 1000,
	},

	// Folder for test artifacts such as screenshots, videos, traces, etc
	outputDir: '../test-results/output',

	retries: 3,

	// Overridable so heavier themes can lower concurrency: block/FSE themes
	// (e.g. purple) load far more per page than a classic theme, and 5 workers
	// against the single-container e2e site exhaust it into connection resets.
	workers: E2E_WORKERS ? Number( E2E_WORKERS ) : CI ? 5 : undefined,

	// Reporter to use. See https://playwright.dev/docs/test-reporters
	reporter: [
		[ CI ? 'github' : 'list' ],
		[
			'html',
			{
				outputFolder: '../test-results/report-html',
				open: CI ? 'never' : 'on-failure',
			},
		],
		[
			'allure-playwright',
			{
				resultsDir: 'tests/e2e/test-results/report-allure/',
			},
		],
	],

	maxFailures: E2E_MAX_FAILURES ? Number( E2E_MAX_FAILURES ) : 0,

	use: {
		baseURL: BASE_URL,

		stateDir: 'tests/e2e/test-results/storage/',

		// Capture screenshot after each test failure
		screenshot: 'only-on-failure',

		// Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer
		// Override with E2E_TRACE=on to record every test (view with
		// `npx playwright show-trace <trace.zip>`).
		trace: E2E_TRACE || 'retain-on-failure',

		// Record video only when retrying a test for the first time. Override
		// with E2E_VIDEO=on to screen-record every test, or
		// E2E_VIDEO=retain-on-failure to keep only failing runs' videos.
		video: E2E_VIDEO || 'on-first-retry',

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
				'**/*optimized-checkout.spec.js',
				'**/adaptive-pricing.spec.js',
				'**/blik.spec.js',
				'**/becs.spec.js',
				'**/isk.spec.js',
				'**/free-trial-link.spec.js',
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
			// Runs the slow Link enrollment/purchase flows in their own job so a
			// slow Link popup can't push the shared `default` job over its
			// timeout and cancel the other specs.
			name: 'express-checkout-link',
			testMatch: '**/free-trial-link.spec.js',
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
			testMatch: '**/*optimized-checkout.spec.js',
			dependencies: [ 'oc-setup' ],
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'adaptive-pricing-setup',
			testMatch: '/adaptive-pricing.setup.js',
			teardown: 'adaptive-pricing-teardown',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'adaptive-pricing',
			testMatch: '**/adaptive-pricing.spec.js',
			dependencies: [ 'adaptive-pricing-setup' ],
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'adaptive-pricing-teardown',
			testMatch: '/adaptive-pricing.teardown.js',
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
