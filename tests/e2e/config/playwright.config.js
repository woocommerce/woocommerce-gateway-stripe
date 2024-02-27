import { devices } from '@playwright/test';
import dotenv from 'dotenv';

dotenv.config( {
	path: `${ process.env.E2E_ROOT }/config/local.env`,
} );

const { BASE_URL, CI, DOCKER, E2E_MAX_FAILURES, TIMEOUT } = process.env;

const config = {
	timeout: TIMEOUT ? Number( TIMEOUT ) : 90 * 1000,
	expect: { timeout: 20 * 1000 },
	outputDir: '../report/output',
	globalSetup: DOCKER ? './global-setup-docker' : './global-setup',
	globalTeardown: './global-teardown',
	testDir: '../tests',
	retries: 3,
	workers: 4,
	reporter: [
		[ CI ? 'github' : 'list' ],
		[
			'html',
			{
				outputFolder: '../report/html',
				open: CI ? 'never' : 'always',
			},
		],
		[
			'allure-playwright',
			{
				outputFolder: 'tests/e2e/report/allure-results/',
			},
		],
		[ 'json', { outputFile: '../report/test-results.json' } ],
	],
	maxFailures: E2E_MAX_FAILURES ? Number( E2E_MAX_FAILURES ) : 0,
	use: {
		baseURL: BASE_URL,
		screenshot: 'only-on-failure',
		stateDir: 'tests/e2e/report/storage/',
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

export default config;
