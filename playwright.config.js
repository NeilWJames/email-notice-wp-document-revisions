/**
 * Playwright configuration for the Email Notice for WP Document Revisions end-to-end tests.
 *
 * Set up as for Simple Taxonomy Refreshed, but without @wordpress/scripts (this plugin does
 * not otherwise use it): its Playwright defaults are copied here, and tests/e2e/global-setup.js
 * logs in as the administrator and saves the login state.
 *
 *  - Tests are in tests/e2e/specs; output (login state, traces, screenshots) in artifacts/.
 *  - The site is on port 8888 (admin / password): http://127.0.0.1:8888 locally, where
 *    Playground listens on IPv4 only and uses that address as the site URL;
 *    http://localhost:8888 in CI, the wp-env site URL. Use the site's own host name, or
 *    WordPress redirects and the login cookie does not apply.
 *  - Locally WordPress runs in WordPress Playground (Node only, no Docker), started by
 *    `npm run env:start` with tests/e2e/blueprint.json. Each start is a new site (SQLite).
 *  - In CI (the CI environment variable is set) wp-env starts it with the Docker runtime
 *    and MySQL, using .wp-env.json.
 *  - One worker: the tests share one site and reset it themselves.
 *  - Longer time limits than the @wordpress/scripts defaults: with the plugin on a network
 *    share an admin page takes several seconds.
 *
 * Run with `npm run test:e2e`. If WordPress is already running (for example
 * `npm run env:start` in another window) it is used; otherwise it is started for the
 * run and stopped afterwards.
 */
const path = require( 'path' );
const { defineConfig, devices } = require( '@playwright/test' );

process.env.WP_BASE_URL ??= process.env.CI
	? 'http://localhost:8888'
	: 'http://127.0.0.1:8888';
process.env.WP_ARTIFACTS_PATH ??= path.join( __dirname, 'artifacts' );
process.env.STORAGE_STATE_PATH ??= path.join(
	process.env.WP_ARTIFACTS_PATH,
	'storage-states/admin.json'
);

module.exports = defineConfig( {
	testDir: path.join( __dirname, 'tests/e2e/specs' ),
	outputDir: path.join( process.env.WP_ARTIFACTS_PATH, 'test-results' ),
	globalSetup: require.resolve( './tests/e2e/global-setup.js' ),
	reporter: process.env.CI ? [ [ 'github' ], [ 'list' ] ] : [ [ 'list' ] ],
	forbidOnly: !! process.env.CI,
	workers: 1,
	retries: process.env.CI ? 1 : 0,
	reportSlowTests: null,
	timeout: 300_000,
	expect: { timeout: 20_000 },
	use: {
		baseURL: process.env.WP_BASE_URL,
		headless: true,
		locale: 'en-US',
		contextOptions: { reducedMotion: 'reduce', strictSelectors: true },
		storageState: process.env.STORAGE_STATE_PATH,
		actionTimeout: 30_000,
		navigationTimeout: 60_000,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'on-first-retry',
	},
	webServer: {
		command: process.env.CI ? 'npx @wordpress/env start' : 'npm run env:start',
		// Playground answers 502 ("WordPress is not ready yet") while it boots, so wait for
		// the site URL to answer rather than for the port to open.
		url: process.env.WP_BASE_URL,
		reuseExistingServer: true,
		// A first start downloads WordPress and WP Document Revisions.
		timeout: 900_000,
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
