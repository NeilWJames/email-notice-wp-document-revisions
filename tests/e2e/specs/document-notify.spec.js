/**
 * Document Email Notifications metabox on a Document's edit screen (manual mode):
 * sending to WordPress users and to Document External Lists, with and without extra text.
 *
 * Demo content (playground/demo-content.php), reset before each test. Subscribed users:
 * admin, editor, subscriber (with the document attached) and subscriber2. Lists:
 * Auditors (Finance, attach), Board members (Finance and Operations), Suppliers
 * (Purchasing), Newsletter (Operations, paused).
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	resetSite,
	getMails,
	editPost,
	notifyBox,
	collectPageErrors,
	expectNoPhpErrors,
	EXT_LOG_URL,
} from '../helpers';

let posts;

test.beforeEach( async ( { requestUtils } ) => {
	posts = await resetSite( requestUtils );
} );

test( 'sends to subscribed users, then offers to re-send', async ( {
	admin,
	page,
	requestUtils,
} ) => {
	const errors = collectPageErrors( page );
	await editPost( admin, posts[ 'Fire drill procedure' ] );
	await expectNoPhpErrors( page );
	const box = notifyBox( page );

	await expect( box.intButton ).toHaveValue( 'Send notification email(s)' );
	await box.intButton.click();

	await expect( box.message ).toContainText( 'notification(s) sent.' );
	await expect( box.intButton ).toHaveValue( 'Re-send notification email(s)' );
	await expect( box.intButton ).toBeEnabled();

	const mails = await getMails( requestUtils );
	// The administrator's address depends on the runtime (Playground or wp-env).
	const me = await requestUtils.rest( {
		path: '/wp/v2/users/me',
		params: { context: 'edit' },
	} );
	expect( mails.map( ( mail ) => mail.address ).sort() ).toEqual(
		[
			me.email,
			'editor@example.com',
			'subscriber2@example.com',
			'subscriber@example.com',
		].sort()
	);
	await expect( box.message ).toContainText( `${ mails.length } notification(s) sent.` );
	// Only the subscriber asked for the document as an attachment.
	for ( const mail of mails ) {
		expect( mail.subject ).toContain( 'Fire drill procedure' );
		expect( mail.attachments ).toHaveLength(
			'subscriber@example.com' === mail.address ? 1 : 0
		);
	}

	// The log link goes to the internal log, which lists the emails.
	await box.message.getByRole( 'link', { name: 'log' } ).click();
	await expect( page ).toHaveURL( /page=wpdr_en_notification_log/ );
	await expect(
		page.locator( '#the-list tr' ).filter( { hasText: 'Fire drill procedure' } )
	).toHaveCount( 4 );
	expect( errors ).toEqual( [] );
} );

test( 'extra text is added only when its box is ticked', async ( {
	admin,
	page,
	requestUtils,
} ) => {
	await editPost( admin, posts[ 'Fire drill procedure' ] );
	const box = notifyBox( page );

	// The "Add Extra text" boxes are enabled once there is text.
	await expect( box.intExtra ).toBeDisabled();
	await box.extra.fill( 'Practice on Friday at 10:00.' );
	await expect( box.intExtra ).toBeEnabled();

	await box.intButton.click();
	await expect( box.message ).toContainText( 'notification(s) sent.' );
	let mails = await getMails( requestUtils );
	expect( mails.length ).toBeGreaterThan( 0 );
	for ( const mail of mails ) {
		expect( mail.message ).not.toContain( 'Practice on Friday' );
	}

	await box.intExtra.check();
	await box.intButton.click();
	await expect( box.intButton ).toBeEnabled();
	mails = ( await getMails( requestUtils ) ).slice( mails.length );
	expect( mails.length ).toBeGreaterThan( 0 );
	for ( const mail of mails ) {
		expect( mail.message ).toContain( 'Practice on Friday at 10:00.' );
	}
} );

test( 'sends to the ticked Lists only', async ( {
	admin,
	page,
	requestUtils,
} ) => {
	const errors = collectPageErrors( page );
	// Purchasing (below Finance) and Operations: Auditors, Board members and Suppliers match;
	// Newsletter is paused.
	await editPost( admin, posts[ 'Purchasing policy' ] );
	const box = notifyBox( page );

	for ( const name of [ 'Auditors', 'Board members', 'Suppliers' ] ) {
		await expect( box.list( name ) ).toBeChecked();
	}
	await expect( box.list( 'Newsletter' ) ).toHaveCount( 0 );

	// Unticking every List disables the button.
	for ( const name of [ 'Auditors', 'Board members', 'Suppliers' ] ) {
		await box.list( name ).uncheck();
	}
	await expect( box.extButton ).toBeDisabled();
	await box.list( 'Suppliers' ).check();
	await expect( box.extButton ).toBeEnabled();

	await box.extra.fill( 'New prices from November.' );
	await box.extExtra.check();
	await box.extButton.click();
	await expect( box.message ).toContainText( 'sent' );

	const mails = await getMails( requestUtils );
	expect( mails.map( ( mail ) => mail.address ) ).toEqual( [
		'orders@supplies.example',
	] );
	expect( mails[ 0 ].message ).toContain( 'New prices from November.' );

	// The external log shows the email.
	await admin.visitAdminPage( EXT_LOG_URL );
	await expectNoPhpErrors( page );
	const rows = page
		.locator( '#the-list tr' )
		.filter( { hasText: 'Purchasing policy' } )
		.filter( { hasText: 'orders@supplies.example' } );
	await expect( rows ).not.toHaveCount( 0 );
	expect( errors ).toEqual( [] );
} );

test( 'a List that attaches the document sends it with the email', async ( {
	admin,
	page,
	requestUtils,
} ) => {
	// Travel expenses form: Finance only, so only Auditors (attach; Bob Checker paused).
	await editPost( admin, posts[ 'Travel expenses form' ] );
	const box = notifyBox( page );
	await expect( box.list( 'Auditors' ) ).toBeChecked();
	await expect( box.list( 'Board members' ) ).toHaveCount( 0 );

	await box.extButton.click();
	await expect( box.message ).toContainText( 'sent' );

	const mails = await getMails( requestUtils );
	expect( mails.map( ( mail ) => mail.address ) ).toEqual( [ 'alice@audit.example' ] );
	expect( mails[ 0 ].attachments ).toHaveLength( 1 );
} );
