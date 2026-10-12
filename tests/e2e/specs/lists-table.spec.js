/**
 * The Lists table (Document Emails > All Document External Lists): the List settings
 * columns, Quick Edit and Bulk Edit.
 *
 * Demo content, reset before each test: Newsletter is paused; Auditors attaches the
 * document; Board members needs all its terms to match.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	resetSite,
	editPost,
	collectPageErrors,
	expectNoPhpErrors,
	LISTS_URL,
} from '../helpers';

let posts;

test.beforeEach( async ( { requestUtils } ) => {
	posts = await resetSite( requestUtils );
} );

/**
 * A List's row in the table.
 *
 * @param {import('@playwright/test').Page} page Current page.
 * @param {string}                          name List title.
 */
const listRow = ( page, name ) => page.locator( `#post-${ posts[ name ] }` );

test( 'the table shows each List’s settings', async ( { admin, page } ) => {
	await admin.visitAdminPage( LISTS_URL );
	await expectNoPhpErrors( page );

	await expect( listRow( page, 'Board members' ) ).toContainText(
		'All taxonomy elements'
	);
	await expect( listRow( page, 'Auditors' ) ).toContainText(
		'Any taxonomy element'
	);
	await expect(
		listRow( page, 'Auditors' ).locator( '.column-wpdr_en_attach input' )
	).toBeChecked();
	await expect(
		listRow( page, 'Newsletter' ).locator( '.column-wpdr_en_pause input' )
	).toBeChecked();
	await expect(
		listRow( page, 'Suppliers' ).locator( '.column-wpdr_en_pause input' )
	).not.toBeChecked();
} );

test( 'Quick Edit shows and saves the List settings', async ( {
	admin,
	page,
} ) => {
	const errors = collectPageErrors( page );
	await admin.visitAdminPage( LISTS_URL );
	const row = listRow( page, 'Newsletter' );
	await row.hover();
	await row.getByRole( 'button', { name: /Quick edit/ } ).click();

	const edit = page.locator( `#edit-${ posts.Newsletter }` );
	await expect( edit ).toBeVisible();
	await expect( edit.getByLabel( 'Any taxonomy element' ) ).toBeChecked();
	await expect( edit.getByLabel( 'Document Attach' ) ).not.toBeChecked();
	await expect( edit.getByLabel( 'Pause Mail' ) ).toBeChecked();

	await edit.getByLabel( 'All taxonomy elements' ).check();
	await edit.getByLabel( 'Document Attach' ).check();
	await edit.getByLabel( 'Pause Mail' ).uncheck();
	await edit.getByRole( 'button', { name: 'Update' } ).click();

	await expect( edit ).toBeHidden();
	await expect( row ).toContainText( 'All taxonomy elements' );
	await expect( row.locator( '.column-wpdr_en_attach input' ) ).toBeChecked();
	await expect(
		row.locator( '.column-wpdr_en_pause input' )
	).not.toBeChecked();

	// Saved: the edit screen shows the same.
	await editPost( admin, posts.Newsletter );
	await expect( page.getByLabel( 'All taxonomy elements' ) ).toBeChecked();
	await expect(
		page.getByLabel( 'Attach Document to Notification Emails' )
	).toBeChecked();
	await expect( page.getByLabel( 'Pause this List' ) ).not.toBeChecked();
	expect( errors ).toEqual( [] );
} );

test( 'Bulk Edit changes only the settings chosen', async ( {
	admin,
	page,
} ) => {
	await admin.visitAdminPage( LISTS_URL );
	await page.getByLabel( 'Select Auditors' ).check();
	await page.getByLabel( 'Select Suppliers' ).check();
	await page.locator( '#bulk-action-selector-top' ).selectOption( 'edit' );
	await page.locator( '#doaction' ).click();

	const bulk = page.locator( '#bulk-edit' );
	await expect( bulk ).toBeVisible();
	await bulk.locator( 'select[name="list_pauseb"]' ).selectOption( '1' );
	await bulk.locator( 'select[name="tm_ruleb"]' ).selectOption( '1' );
	await bulk.getByRole( 'button', { name: 'Update' } ).click();

	await expect( page.locator( '#message' ) ).toContainText( '2' );
	await expectNoPhpErrors( page );
	for ( const name of [ 'Auditors', 'Suppliers' ] ) {
		const row = listRow( page, name );
		await expect( row.locator( '.column-wpdr_en_pause input' ) ).toBeChecked();
		await expect( row ).toContainText( 'All taxonomy elements' );
	}
	// Attach was left as "No Change".
	await expect(
		listRow( page, 'Auditors' ).locator( '.column-wpdr_en_attach input' )
	).toBeChecked();
	await expect(
		listRow( page, 'Suppliers' ).locator( '.column-wpdr_en_attach input' )
	).not.toBeChecked();
	// Lists not selected are unchanged.
	await expect(
		listRow( page, 'Board members' ).locator( '.column-wpdr_en_pause input' )
	).not.toBeChecked();
} );
