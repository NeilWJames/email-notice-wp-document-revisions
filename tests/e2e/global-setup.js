/**
 * Playwright global setup: log in as the administrator once and save the login state
 * (artifacts/storage-states/admin.json), as the @wordpress/scripts setup does.
 */
const { request } = require( '@playwright/test' );
const { RequestUtils } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * @param {import('@playwright/test').FullConfig} config Playwright configuration.
 */
module.exports = async function globalSetup( config ) {
	const { storageState, baseURL } = config.projects[ 0 ].use;
	const requestContext = await request.newContext( { baseURL } );
	const requestUtils = new RequestUtils( requestContext, {
		storageStatePath: storageState,
	} );

	await requestUtils.setupRest();
	await requestContext.dispose();
};
