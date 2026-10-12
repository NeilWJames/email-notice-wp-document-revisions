/**
 * A Document External List's edit screen: the address entry (add, edit, delete through
 * AJAX, without reloading the page), the List attributes and who may change the log days.
 *
 * Demo content, reset before each test. Suppliers has one address (Supplies Ltd);
 * Auditors deletes its log entries after 90 days.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	resetSite,
	editPost,
	addressForm,
	enterAddress,
	loginAs,
	collectPageErrors,
	expectNoPhpErrors,
} from '../helpers';

let posts;

test.beforeEach( async ( { requestUtils } ) => {
	posts = await resetSite( requestUtils );
} );

test( 'adds, edits and deletes an address without reloading the page', async ( {
	admin,
	page,
} ) => {
	const errors = collectPageErrors( page );
	await editPost( admin, posts.Suppliers );
	await expectNoPhpErrors( page );
	const form = addressForm( page );
	await expect( form.row( 'Supplies Ltd' ) ).toHaveCount( 1 );

	// Add is enabled only with a name and an email address.
	await expect( form.add ).toBeDisabled();
	await form.name.fill( 'Zoe Zed' );
	await form.name.press( 'Tab' );
	await expect( form.add ).toBeDisabled();
	await form.email.fill( 'zoe@supplies.example' );
	await form.email.press( 'Tab' );
	await expect( form.add ).toBeEnabled();

	// Mark the page: a reload would lose the mark.
	await page.evaluate( () => ( window.wpdrEnNoReload = true ) );
	await form.add.click();
	await expect( form.row( 'zoe@supplies.example' ) ).toHaveCount( 1 );
	await expect( form.name ).toHaveValue( '' );
	await expect( form.add ).toBeDisabled();

	// Edit: the email address is the key, so it cannot be changed.
	// The row actions show when the pointer is over the row.
	await form.row( 'zoe@supplies.example' ).hover();
	await form
		.row( 'zoe@supplies.example' )
		.getByRole( 'button', { name: 'Edit', exact: true } )
		.click();
	await expect( form.name ).toHaveValue( 'Zoe Zed' );
	await expect( form.email ).toBeDisabled();
	await expect( form.add ).toHaveValue( 'Edit existing Address' );
	await form.name.fill( 'Zoe Zed-Smith' );
	await form.pause.check();
	await form.add.click();
	const zoe = form.row( 'zoe@supplies.example' );
	await expect( zoe ).toContainText( 'Zoe Zed-Smith' );
	await expect( zoe.getByRole( 'checkbox' ) ).toBeChecked();
	await expect( form.add ).toHaveValue( 'Add Address to List' );

	// Delete.
	await zoe.hover();
	await zoe.getByRole( 'button', { name: 'Delete', exact: true } ).click();
	await expect( form.row( 'zoe@supplies.example' ) ).toHaveCount( 0 );
	await expect( form.row( 'Supplies Ltd' ) ).toHaveCount( 1 );

	expect( await page.evaluate( () => window.wpdrEnNoReload ) ).toBe( true );
	expect( errors ).toEqual( [] );
} );

test( 'an added address is kept, and an invalid one refused', async ( {
	admin,
	page,
} ) => {
	await editPost( admin, posts.Suppliers );
	const form = addressForm( page );

	await enterAddress( form, 'Not an address', 'not-an-email' );
	await form.add.click();
	await expect( form.message ).not.toBeEmpty();
	await expect( form.email ).toHaveValue( 'not-an-email' );
	await expect( form.row( 'not-an-email' ) ).toHaveCount( 0 );

	await enterAddress( form, 'Yves Young', 'yves@supplies.example' );
	await form.add.click();
	await expect( form.row( 'yves@supplies.example' ) ).toHaveCount( 1 );

	await page.reload();
	await expect( form.row( 'yves@supplies.example' ) ).toHaveCount( 1 );
	await expect( form.row( 'Supplies Ltd' ) ).toHaveCount( 1 );
} );

test( 'the List attributes are saved with the List', async ( {
	admin,
	page,
} ) => {
	await editPost( admin, posts.Suppliers );

	await page.getByLabel( 'All taxonomy elements' ).check();
	await page.getByLabel( 'Attach Document to Notification Emails' ).check();
	await page.getByLabel( 'Pause this List' ).check();
	await page.locator( '#wpdr_en_log_days' ).fill( '30' );
	await page.locator( '#publish' ).click();
	await expect( page.locator( '#message' ) ).toContainText( 'updated' );
	await expectNoPhpErrors( page );

	await expect( page.getByLabel( 'All taxonomy elements' ) ).toBeChecked();
	await expect(
		page.getByLabel( 'Attach Document to Notification Emails' )
	).toBeChecked();
	await expect( page.getByLabel( 'Pause this List' ) ).toBeChecked();
	await expect( page.locator( '#wpdr_en_log_days' ) ).toHaveValue( '30' );

	// And unticked again.
	await page.getByLabel( 'Any taxonomy element' ).check();
	await page.getByLabel( 'Pause this List' ).uncheck();
	await page.locator( '#publish' ).click();
	await expect( page.locator( '#message' ) ).toContainText( 'updated' );
	await expect( page.getByLabel( 'Any taxonomy element' ) ).toBeChecked();
	await expect( page.getByLabel( 'Pause this List' ) ).not.toBeChecked();
} );

const LOG_DAYS_NOTE = 'Only users who can delete Lists can change this.';

test( 'an administrator can change the log days', async ( { admin, page } ) => {
	await editPost( admin, posts.Auditors );
	await expect( page.locator( '#wpdr_en_log_days' ) ).toBeEnabled();
	await expect( page.getByText( LOG_DAYS_NOTE ) ).toHaveCount( 0 );
} );

test( 'an editor cannot change the log days, and saving keeps them', async ( {
	admin,
	page,
} ) => {
	const note = page.getByText( LOG_DAYS_NOTE );

	// Editors can edit Lists but not delete them.
	await loginAs( page, 'editor' );
	await editPost( admin, posts.Auditors );
	await expect( page.locator( '#wpdr_en_log_days' ) ).toBeDisabled();
	await expect( page.locator( '#wpdr_en_log_days' ) ).toHaveValue( '90' );
	await expect( note ).toBeVisible();

	// Saving the List as an editor keeps the value.
	await page.locator( '#excerpt' ).fill( 'Changed by the editor.' );
	await page.locator( '#publish' ).click();
	await expect( page.locator( '#message' ) ).toContainText( 'updated' );
	await expect( page.locator( '#wpdr_en_log_days' ) ).toHaveValue( '90' );
	await expect( page.locator( '#excerpt' ) ).toHaveValue(
		'Changed by the editor.'
	);
} );
