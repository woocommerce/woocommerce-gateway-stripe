'use strict';

/* jshint node: true */

import { devices } from '@playwright/test';
import dotenv from 'dotenv';

dotenv.config( {
	path: `${ process.env.E2E_ROOT }/config/local.env`,
} );

const { BASE_URL, CI, DOCKER, E2E_MAX_FAILURES, TIMEOUT } = process.env;

const config = {
	globalSetup: DOCKER ? './global-setup-docker' : './global-setup',
	globalTeardown: './global-teardown',

	testDir: '../tests',

	// Maximum time one test can run for
	timeout: TIMEOUT ? Number( TIMEOUT ) : 90 * 1000,

	expect: {
		// Maximum time expect() should wait for the condition to be met
		// For example in `await expect(locator).toHaveText();`
		timeout: 20 * 1000,
	},

	// Folder for test artifacts such as screenshots, videos, traces, etc
	outputDir: '../test-results/output',

	/* Retry on CI only */
	retries: CI ? 3 : 0,

	workers: 5,

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
				outputFolder: 'tests/e2e/test-results/report-allure/',
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
		trace: 'retain-on-failure',

		// Record video only when retrying a test for the first time
		video: 'on-first-retry',

		viewport: { width: 1280, height: 720 },
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
			testIgnore: /_legacy-experience/,
			dependencies: [ 'default-setup' ],
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'legacy-setup',
			testMatch: '_legacy-experience/legacy.setup.js',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'legacy',
			testMatch: '/_legacy-experience/**/*.spec.js',
			dependencies: [ 'legacy-setup' ],
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
};

export default config;
