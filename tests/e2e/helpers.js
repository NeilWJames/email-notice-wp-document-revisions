/**
 * Shared helpers for the Email Notice for WP Document Revisions end-to-end tests.
 */
import { expect } from '@wordpress/e2e-test-utils-playwright';

/** Admin URL of the Lists table (Document Emails > All Document External Lists). */
export const LISTS_URL = 'edit.php?post_type=doc_ext_list';

/** Admin URL of the internal (WordPress user) email log. */
export const INT_LOG_URL = 'edit.php?post_type=doc_ext_list&page=wpdr_en_notification_log';

/** Admin URL of the external (List) email log. */
export const EXT_LOG_URL = 'edit.php?post_type=doc_ext_list&page=wpdr_en_ext_notice_log';

/**
 * Reset the site (helper must-use plugin, route wpdr-en-e2e/v1/reset): no Documents, Lists,
 * log entries, demo users or plugin settings. With `demo`, then load the playground demo
 * (playground/demo-content.php): users, Departments, 7 Documents, 4 Lists and some
 * notifications already sent.
 *
 * @param {Object}  requestUtils RequestUtils fixture.
 * @param {boolean} demo         Load the demo content.
 * @return {Promise<Object<string, number>>} Post ID of each Document and List, by title.
 */
export async function resetSite( requestUtils, demo = true ) {
	const result = await requestUtils.rest( {
		method: 'POST',
		path: '/wpdr-en-e2e/v1/reset',
		data: { demo },
	} );
	return result.posts;
}

/**
 * The emails sent since the last reset (recorded by the helper; none is really sent).
 *
 * @param {Object} requestUtils RequestUtils fixture.
 * @return {Promise<Array<{to: string, address: string, subject: string, message: string, attachments: string[]}>>}
 *         Emails; `to` as sent ("Name <address>"), `address` the address alone.
 */
export async function getMails( requestUtils ) {
	const mails = await requestUtils.rest( { path: '/wpdr-en-e2e/v1/mails' } );
	return mails.map( ( mail ) => ( {
		...mail,
		address: mail.to.replace( /^.*<([^>]+)>\s*$/, '$1' ),
	} ) );
}

/**
 * Set a plugin setting (an option named wpdr_en_set_...).
 *
 * @param {Object} requestUtils RequestUtils fixture.
 * @param {string} name         Option name.
 * @param {*}      value        Value.
 */
export async function setOption( requestUtils, name, value ) {
	await requestUtils.rest( {
		method: 'POST',
		path: '/wpdr-en-e2e/v1/option',
		data: { name, value },
	} );
}

/**
 * Open a post (Document or List) for editing.
 *
 * @param {Object} admin Admin fixture.
 * @param {number} id    Post ID.
 */
export async function editPost( admin, id ) {
	await admin.visitAdminPage( 'post.php', `post=${ id }&action=edit` );
}

/**
 * Log in as another user (password "password") in this test's browser context.
 * The saved administrator login is not changed.
 *
 * @param {import('@playwright/test').Page} page     Current page.
 * @param {string}                          username User login.
 */
export async function loginAs( page, username ) {
	await page.context().clearCookies();
	await page.goto( 'wp-login.php' );
	await page.locator( '#user_login' ).fill( username );
	await page.locator( '#user_pass' ).fill( 'password' );
	await page.locator( '#wp-submit' ).click();
	await page.waitForURL( /wp-admin/ );
}

/**
 * Collect uncaught script errors on the page.
 *
 * @param {import('@playwright/test').Page} page Current page.
 * @return {string[]} Messages, filled as errors happen.
 */
export function collectPageErrors( page ) {
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	return errors;
}

/**
 * Expect no PHP error, warning, notice or deprecation from this plugin on the page
 * (WP_DEBUG is on, so PHP shows them in the page).
 *
 * @param {import('@playwright/test').Page} page Current page.
 */
export async function expectNoPhpErrors( page ) {
	const text = await page.locator( 'body' ).innerText();
	expect( text ).not.toMatch(
		/(Fatal error|Warning|Notice|Deprecated):[^\n]*email-notice-wp-document-revisions/
	);
}

/**
 * Locators for the Document Email Notifications metabox on a Document's edit screen.
 *
 * @param {import('@playwright/test').Page} page Current page.
 */
export function notifyBox( page ) {
	const box = page.locator( '#wpdr_en_sectionid' );
	return {
		box,
		extra: box.locator( '#wpdr-en-extra' ),
		intButton: box.locator( '#wpdr-en-notify' ),
		intExtra: box.locator( '#wpdr-en-int-extra' ),
		extButton: box.locator( '#wpdr-en-ext-note' ),
		extExtra: box.locator( '#wpdr-en-ext-extra' ),
		message: box.locator( '#wpdr-en-message' ),
		list: ( name ) => box.getByRole( 'checkbox', { name, exact: true } ),
	};
}

/**
 * Locators for the address entry on a List's edit screen.
 *
 * @param {import('@playwright/test').Page} page Current page.
 */
export function addressForm( page ) {
	return {
		name: page.locator( '#wpdr-en-user-name' ),
		email: page.locator( '#wpdr-en-email' ),
		pause: page.locator( '#wpdr_pause' ),
		add: page.locator( '#add_address' ),
		clear: page.getByRole( 'button', { name: 'Clear values' } ),
		message: page.locator( '#wpdr-en-message' ),
		table: page.locator( '#current-list table.wp-list-table' ),
		row: ( text ) =>
			page
				.locator( '#current-list tbody tr' )
				.filter( { hasText: text } ),
	};
}

/**
 * Enter a name and email address. The form checks them when a field is left.
 *
 * @param {Object} form  Locators from addressForm().
 * @param {string} name  User name.
 * @param {string} email Email address.
 */
export async function enterAddress( form, name, email ) {
	await form.name.fill( name );
	await form.name.press( 'Tab' );
	await form.email.fill( email );
	await form.email.press( 'Tab' );
}
