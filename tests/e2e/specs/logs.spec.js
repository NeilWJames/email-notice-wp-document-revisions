/**
 * The two email logs (Document Emails > Internal / External User Email Log): search,
 * sorting, CSV export of what the page shows, and who can see them.
 *
 * Demo content, loaded once for the file (the tests only read). The demo sends:
 * internal 14 emails (2026 Budget twice, Payroll calendar, Staff handbook), external
 * 6 emails (2026 Budget and Payroll calendar to Auditors, Purchasing policy to Auditors,
 * Board members and Suppliers; paused addresses are skipped).
 */
import fs from 'fs';
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	resetSite,
	loginAs,
	collectPageErrors,
	INT_LOG_URL,
	EXT_LOG_URL,
} from '../helpers';

/**
 * Parse CSV text (RFC 4180: quoted fields may hold commas, quotes and line breaks).
 *
 * @param {string} text CSV text.
 * @return {string[][]} Rows of fields.
 */
function parseCsv( text ) {
	const rows = [];
	let row = [];
	let field = '';
	let quoted = false;
	for ( let i = 0; i < text.length; i++ ) {
		const c = text[ i ];
		if ( quoted ) {
			if ( '"' === c && '"' === text[ i + 1 ] ) {
				field += '"';
				i++;
			} else if ( '"' === c ) {
				quoted = false;
			} else {
				field += c;
			}
		} else if ( '"' === c ) {
			quoted = true;
		} else if ( ',' === c ) {
			row.push( field );
			field = '';
		} else if ( '\n' === c ) {
			row.push( field.replace( /\r$/, '' ) );
			rows.push( row );
			row = [];
			field = '';
		} else {
			field += c;
		}
	}
	if ( '' !== field || row.length ) {
		row.push( field );
		rows.push( row );
	}
	return rows;
}

/**
 * Click Export CSV and read the file.
 *
 * @param {import('@playwright/test').Page} page Current page.
 * @return {Promise<{name: string, text: string}>} File name and content.
 */
async function exportCsv( page ) {
	const [ download ] = await Promise.all( [
		page.waitForEvent( 'download' ),
		page.getByRole( 'link', { name: 'Export CSV' } ).click(),
	] );
	return {
		name: download.suggestedFilename(),
		text: fs.readFileSync( await download.path(), 'utf8' ),
	};
}

/**
 * The table rows shown.
 *
 * @param {import('@playwright/test').Page} page Current page.
 */
const rows = ( page ) => page.locator( '#the-list tr' );

test.beforeAll( async ( { requestUtils } ) => {
	await resetSite( requestUtils );
} );

test( 'the internal log lists, searches and exports what it shows', async ( {
	admin,
	page,
} ) => {
	const errors = collectPageErrors( page );
	await admin.visitAdminPage( INT_LOG_URL );
	await expect(
		page.getByRole( 'heading', { name: 'Document Email Notification Log' } )
	).toBeVisible();
	await expect( rows( page ) ).toHaveCount( 14 );

	// Everything: header and one row per email, Excel-friendly UTF-8.
	let csv = await exportCsv( page );
	expect( csv.name ).toMatch( /\.csv$/ );
	expect( csv.text.charCodeAt( 0 ) ).toBe( 0xfeff );
	let data = parseCsv( csv.text.slice( 1 ) );
	expect( data[ 0 ] ).toEqual( [
		'ID',
		'Document ID',
		'Document',
		'Email sent',
		'User ID',
		'User Name',
		'User Email',
		'Status',
		'Extra Text',
	] );
	expect( data ).toHaveLength( 15 );

	// Search, then export only the rows found.
	await page.getByRole( 'searchbox', { name: 'Search:' } ).fill( 'Payroll' );
	await page.getByRole( 'button', { name: 'Search', exact: true } ).click();
	await expect( page ).toHaveURL( /[?&]s=Payroll/ );
	await expect( rows( page ).first() ).toContainText( 'Payroll calendar' );
	const found = await rows( page ).count();
	expect( found ).toBeGreaterThan( 0 );
	for ( const title of await rows( page )
		.locator( '.column-post_title' )
		.allInnerTexts() ) {
		expect( title ).toBe( 'Payroll calendar' );
	}

	csv = await exportCsv( page );
	data = parseCsv( csv.text.slice( 1 ) );
	expect( data ).toHaveLength( found + 1 );
	for ( const row of data.slice( 1 ) ) {
		expect( row[ 2 ] ).toBe( 'Payroll calendar' );
	}
	expect( errors ).toEqual( [] );
} );

test( 'the internal log keeps the extra text and sorts by a column', async ( {
	admin,
	page,
} ) => {
	await admin.visitAdminPage( INT_LOG_URL );

	// The heading link sorts by email address; a second click reverses the order. Only
	// the first letters are compared: MySQL and SQLite order "subscriber2@" differently, and the
	// administrator's address differs (admin@localhost.com in Playground, wordpress@example.com
	// in wp-env).
	const initials = ( list ) => list.map( ( e ) => e[ 0 ] );
	const emails = () =>
		rows( page ).locator( '.column-user_email' ).allInnerTexts();
	await page.locator( 'thead th#user_email a' ).click();
	await expect( page ).toHaveURL( /orderby=user_email&order=asc/ );
	await expect( rows( page ) ).toHaveCount( 14 );
	const ascending = await emails();
	expect( initials( ascending ) ).toEqual( initials( ascending ).sort() );
	expect( ascending[ 0 ][ 0 ] ).not.toBe( ascending[ 13 ][ 0 ] );

	await page.locator( 'thead th#user_email a' ).click();
	await expect( page ).toHaveURL( /orderby=user_email&order=desc/ );
	await expect( rows( page ) ).toHaveCount( 14 );
	const descending = await emails();
	expect( initials( descending ) ).toEqual( initials( ascending ).reverse() );

	// The sort is kept in the export.
	const data = parseCsv( ( await exportCsv( page ) ).text.slice( 1 ) );
	expect( data.slice( 1 ).map( ( row ) => row[ 6 ] ) ).toEqual( await emails() );
	// The budget's second sending had extra text.
	expect(
		data.filter( ( row ) =>
			row[ 8 ].includes( 'Approved by the board on 1 October.' )
		).length
	).toBeGreaterThan( 0 );
} );

test( 'the external log lists and exports the List emails', async ( {
	admin,
	page,
} ) => {
	await admin.visitAdminPage( EXT_LOG_URL );
	await expect(
		page.getByRole( 'heading', { name: 'Document Email External Notice Log' } )
	).toBeVisible();
	await expect( rows( page ) ).toHaveCount( 6 );
	// Bob Checker's address is paused.
	await expect( rows( page ).filter( { hasText: 'bob@audit.example' } ) ).toHaveCount( 0 );

	const data = parseCsv( ( await exportCsv( page ) ).text.slice( 1 ) );
	expect( data[ 0 ] ).toEqual( [
		'ID',
		'Document ID',
		'Document',
		'List ID',
		'List',
		'Email sent',
		'Name',
		'Email',
		'Status',
		'Extra Text',
	] );
	expect( data ).toHaveLength( 7 );
	expect(
		data
			.slice( 1 )
			.map( ( row ) => row[ 4 ] )
			.sort()
	).toEqual( [
		'Auditors',
		'Auditors',
		'Auditors',
		'Board members',
		'Board members',
		'Suppliers',
	] );
} );

test( 'editors see the logs; authors do not', async ( { admin, page } ) => {
	await loginAs( page, 'editor' );
	await admin.visitAdminPage( EXT_LOG_URL );
	await expect( rows( page ) ).toHaveCount( 6 );
	await expect( page.getByRole( 'link', { name: 'Export CSV' } ) ).toBeVisible();

	await loginAs( page, 'author' );
	await expect(
		page.locator( '#adminmenu' ).getByRole( 'link', { name: 'Document Emails' } )
	).toHaveCount( 0 );
	const response = await page.goto( `wp-admin/${ EXT_LOG_URL }` );
	expect( response.status() ).toBe( 403 );
} );
