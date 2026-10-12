/**
 * Settings > Writing (Document Email Settings), Auto mode, and the users' notification
 * choices (profile, Add New User, the Users list column and bulk actions).
 *
 * Demo content, reset before each test.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	resetSite,
	getMails,
	setOption,
	collectPageErrors,
	expectNoPhpErrors,
} from '../helpers';

let posts;

test.beforeEach( async ( { requestUtils } ) => {
	posts = await resetSite( requestUtils );
} );

test( 'the Writing settings are saved', async ( { admin, page } ) => {
	const errors = collectPageErrors( page );
	await admin.visitAdminPage( 'options-writing.php' );
	await expectNoPhpErrors( page );

	await expect( page.getByLabel( /^Manual/ ) ).toBeChecked();
	await page.getByLabel( /^Auto/ ).check();
	await page.getByLabel( 'Private posts' ).uncheck();
	await page
		.getByLabel( 'Notification email subject' )
		.fill( 'Library: %title%' );
	await page.locator( '#wpdr_en_set_log_days' ).fill( '60' );
	await page.getByRole( 'button', { name: 'Save Changes' } ).click();

	await expect( page.locator( '#setting-error-settings_updated' ) ).toBeVisible();
	await expect( page.getByLabel( /^Auto/ ) ).toBeChecked();
	await expect( page.getByLabel( 'Public posts' ) ).toBeChecked();
	await expect( page.getByLabel( 'Private posts' ) ).not.toBeChecked();
	await expect( page.getByLabel( 'Notification email subject' ) ).toHaveValue(
		'Library: %title%'
	);
	await expect( page.locator( '#wpdr_en_set_log_days' ) ).toHaveValue( '60' );
	expect( errors ).toEqual( [] );
} );

test( 'in Auto mode, publishing a Document sends once and says so', async ( {
	admin,
	page,
	requestUtils,
} ) => {
	await setOption( requestUtils, 'wpdr_en_set_notification_mode', 'Auto' );

	// Publish the draft "Strategy 2027" with Quick Edit.
	await admin.visitAdminPage( 'edit.php', 'post_type=document' );
	const row = page.locator( `#post-${ posts[ 'Strategy 2027' ] }` );
	await row.hover();
	await row.getByRole( 'button', { name: /Quick edit/ } ).click();
	const edit = page.locator( `#edit-${ posts[ 'Strategy 2027' ] }` );
	await edit.locator( 'select[name="_status"]' ).selectOption( 'publish' );
	await edit.getByRole( 'button', { name: 'Update' } ).click();
	await expect( edit ).toBeHidden();

	const mails = await getMails( requestUtils );
	expect( mails.length ).toBeGreaterThan( 0 );
	for ( const mail of mails ) {
		expect( mail.subject ).toContain( 'Strategy 2027' );
	}

	// The result is shown on the next admin page, once.
	await admin.visitAdminPage( 'edit.php', 'post_type=document' );
	const notice = page.locator( '#wpdr_en_notification_div' );
	await expect( notice ).toContainText(
		`${ mails.length } notification(s) sent.`
	);
	await expect( notice.getByRole( 'link', { name: 'Check log' } ) ).toBeVisible();
	await admin.visitAdminPage( 'edit.php', 'post_type=document' );
	await expect( notice ).toHaveCount( 0 );

	// Saving the published Document again sends nothing more.
	await row.hover();
	await row.getByRole( 'button', { name: /Quick edit/ } ).click();
	await edit.getByLabel( 'Title' ).fill( 'Strategy 2027 (revised)' );
	await edit.getByRole( 'button', { name: 'Update' } ).click();
	await expect( edit ).toBeHidden();
	expect( await getMails( requestUtils ) ).toHaveLength( mails.length );
} );

test( 'the profile choices are labelled for the user and saved', async ( {
	admin,
	page,
} ) => {
	// Own profile: "me". The demo subscribes the administrator.
	await admin.visitAdminPage( 'profile.php' );
	await expectNoPhpErrors( page );
	const notify = page.getByLabel(
		'Notify me by email when a new document is published'
	);
	const attach = page.getByLabel( /^Also send me the document/ );
	await expect( notify ).toBeChecked();
	await expect( attach ).not.toBeChecked();
	await attach.check();
	await page.getByRole( 'button', { name: 'Update Profile' } ).click();
	await expect( page.locator( '#message' ) ).toContainText( 'Profile updated' );
	await expect( attach ).toBeChecked();

	// Another user's profile: "this user".
	await admin.visitAdminPage( 'users.php' );
	await page.getByRole( 'link', { name: 'author', exact: true } ).click();
	const other = page.getByLabel(
		'Notify this user by email when a new document is published'
	);
	await expect( other ).not.toBeChecked();
	await other.check();
	await page.getByRole( 'button', { name: 'Update User' } ).click();
	await expect( page.locator( '#message' ) ).toContainText( 'User updated' );
	await expect( other ).toBeChecked();
} );

test( 'a new user can be subscribed when added', async ( { admin, page } ) => {
	await admin.visitAdminPage( 'user-new.php' );
	await page.locator( '#user_login' ).fill( 'e2e-reader' );
	await page.locator( '#email' ).fill( 'e2e-reader@example.com' );
	await page.locator( '#pass1' ).fill( 'e2e-Reader-Pass-2026!' );
	await page
		.getByLabel( 'Notify this user by email when a new document is published' )
		.check();
	await page.getByRole( 'button', { name: 'Add User' } ).click();
	await expect( page ).toHaveURL( /users\.php/ );
	await expectNoPhpErrors( page );

	await admin.visitAdminPage( 'users.php', 's=e2e-reader' );
	const row = page.locator( '#the-list tr' ).filter( { hasText: 'e2e-reader' } );
	await expect(
		row.getByRole( 'checkbox', { name: 'Notified by email' } )
	).toBeChecked();
	await expect(
		row.getByRole( 'checkbox', { name: 'Document sent as attachment' } )
	).not.toBeChecked();
} );

test( 'the Users bulk actions subscribe and unsubscribe', async ( {
	admin,
	page,
} ) => {
	await admin.visitAdminPage( 'users.php' );
	// The Users column: is the user notified by email?
	const notified = ( login ) =>
		page
			.locator( '#the-list tr' )
			.filter( { has: page.getByRole( 'link', { name: login, exact: true } ) } )
			.getByRole( 'checkbox', { name: 'Notified by email' } );
	await expect( notified( 'editor' ) ).toBeChecked();
	await expect( notified( 'author' ) ).not.toBeChecked();

	await page.getByLabel( 'Select author' ).check();
	await page.getByLabel( 'Select contributor' ).check();
	await page
		.locator( '#bulk-action-selector-top' )
		.selectOption( 'wpdr_subscribe' );
	await page.locator( '#doaction' ).click();
	await expectNoPhpErrors( page );
	await expect( notified( 'author' ) ).toBeChecked();
	await expect( notified( 'contributor' ) ).toBeChecked();

	await page.getByLabel( 'Select author' ).check();
	await page
		.locator( '#bulk-action-selector-top' )
		.selectOption( 'wpdr_unsubscribe' );
	await page.locator( '#doaction' ).click();
	await expect( notified( 'author' ) ).not.toBeChecked();
	await expect( notified( 'contributor' ) ).toBeChecked();
} );
