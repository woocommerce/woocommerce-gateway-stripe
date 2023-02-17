import { devices } from '@playwright/test';
import dotenv from 'dotenv';

dotenv.config( {
	path: `${ process.env.E2E_ROOT }/config/local.env`,
} );

const {
	ALLURE_RESULTS_DIR,
	BASE_URL,
	CI,
	DEFAULT_TIMEOUT_OVERRIDE,
	E2E_MAX_FAILURES,
} = process.env;

const config = {
	timeout: DEFAULT_TIMEOUT_OVERRIDE
		? Number( DEFAULT_TIMEOUT_OVERRIDE )
		: 90 * 1000,
	expect: { timeout: 20 * 1000 },
	outputDir: '../report',
	globalSetup: require.resolve( './global-setup' ),
	globalTeardown: require.resolve( './global-teardown' ),
	testDir: '../tests',
	retries: CI ? 4 : 2,
	workers: 4,
	reporter: [
		[ CI ? 'github' : 'list' ],
		[
			'html',
			{
				outputFolder: '../output',
				open: CI ? 'never' : 'always',
			},
		],
		[
			'allure-playwright',
			{
				outputFolder: ALLURE_RESULTS_DIR ?? 'tests/e2e/allure-results/',
			},
		],
		[ 'json', { outputFile: '../report/test-results.json' } ],
	],
	maxFailures: E2E_MAX_FAILURES ? Number( E2E_MAX_FAILURES ) : 0,
	use: {
		baseURL: BASE_URL ?? 'http://localhost:8086',
		screenshot: 'only-on-failure',
		stateDir: 'tests/e2e/storage/',
		trace: 'retain-on-failure',
		video: 'on-first-retry',
		viewport: { width: 1280, height: 720 },
	},
	projects: [
		{
			name: 'Chrome',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
};

module.exports = config;
