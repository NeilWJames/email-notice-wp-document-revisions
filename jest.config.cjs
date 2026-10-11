/**
 * Jest configuration for the JavaScript tests (tests/js). Run with `npm test`.
 *
 * The scripts in src/js are plain browser scripts that declare global functions, so they are loaded
 * through tests/js/global-script-transform.cjs, which makes those functions globals as in the browser.
 */
module.exports = {
	testEnvironment: 'jsdom',
	roots: [ '<rootDir>/tests/js' ],
	testMatch: [ '**/*.test.js' ],
	transform: {
		'src[\\\\/]js[\\\\/].+\\.js$': '<rootDir>/tests/js/global-script-transform.cjs',
	},
	collectCoverageFrom: [ '<rootDir>/src/js/**/*.js' ],
	coverageProvider: 'v8',
	coverageDirectory: '<rootDir>/coverage/js',
	coverageReporters: [ 'text', 'lcov' ],
};
