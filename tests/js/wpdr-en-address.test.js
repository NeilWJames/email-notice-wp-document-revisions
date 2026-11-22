/**
 * Document External List edit screen (src/js/wpdr-en-address.js).
 */
const { loadScript, lastRequest, asOnclick } = require( './helpers' );

/**
 * The address entry form as doc_ext_list_metabox() writes it, with an address table.
 *
 * @return {string} HTML.
 */
function listScreen() {
	return `
		<form id="post">
		<input type="hidden" id="post_ID" value="12">
		<input type="hidden" id="hiddenaction" value="editpost">
		<p id="wpdr-en-entry-help">Enter a name and an email address.</p>
		<table id="user-entry">
		<tr><td><input type="text" id="wpdr-en-user-name" aria-describedby="wpdr-en-entry-help"></td></tr>
		<tr><td><input type="text" id="wpdr-en-email" aria-describedby="wpdr-en-entry-help"></td></tr>
		<tr><td><input type="checkbox" id="wpdr_pause"></td></tr>
		<tr><td><div id="clear_address" style="display:none"></div></td>
		<td><input type="submit" id="add_address" value="Add Address to List" disabled></td></tr>
		<tr><td><p id="wpdr-en-message" role="status" aria-live="polite"></p></td></tr>
		</table>
		<div id="current-list"><h2 id="wpdr-en-list-heading" tabindex="-1">List</h2><table><tr><td>old</td></tr></table></div>
		<input type="submit" id="search-submit">
		</form>`;
}

beforeAll( () => {
	// jsdom has no layout.
	Element.prototype.scrollIntoView = jest.fn();
} );

describe( 'address entry', () => {
	test( 'Add posts the entry and stops the form submitting', () => {
		const ajax = loadScript( 'wpdr-en-address', listScreen() );
		document.getElementById( 'wpdr-en-user-name' ).value = 'Ann';
		document.getElementById( 'wpdr-en-email' ).value     = 'ann@example.org';
		document.getElementById( 'wpdr_pause' ).checked      = true;

		const event = asOnclick( wpdr_en_insert );

		expect( lastRequest( ajax ).data ).toEqual( {
			action: 'wpdr_en_add_address',
			post_id: '12',
			user_name: 'Ann',
			email: 'ann@example.org',
			pause: 1,
			wpdr_en_nonce: 'nonce123',
		} );
		expect( event.defaultPrevented ).toBe( true );
		// The values were global variables before 4.0.
		expect( window.user_name ).toBeUndefined();
	} );

	test( 'a successful change shows the new table and empties the entry', () => {
		const ajax = loadScript( 'wpdr-en-address', listScreen() );
		document.getElementById( 'wpdr-en-user-name' ).value = 'Ann';
		asOnclick( wpdr_en_insert );

		// Before 4.0 this then threw (it called location.reload() on a div).
		expect( () => lastRequest( ajax ).success( { error: false, error_msg: JSON.stringify( '<table><tr><td>Ann</td></tr></table>' ) } ) ).not.toThrow();

		expect( document.getElementById( 'current-list' ).textContent ).toBe( 'Ann' );
		expect( document.getElementById( 'wpdr-en-user-name' ).value ).toBe( '' );
		expect( document.getElementById( 'add_address' ).disabled ).toBe( true );
		// Announced in the status region, and ready for the next address.
		expect( document.getElementById( 'wpdr-en-message' ).textContent ).toBe( 'Address added to the List.' );
		expect( document.activeElement.id ).toBe( 'wpdr-en-user-name' );
	} );

	test( 'saving an edited address says it was updated', () => {
		const ajax = loadScript( 'wpdr-en-address', listScreen() );
		document.getElementById( 'add_address' ).value = 'Edit existing Address';
		asOnclick( wpdr_en_insert );

		lastRequest( ajax ).success( { error: false, error_msg: JSON.stringify( '<p>table</p>' ) } );

		expect( document.getElementById( 'wpdr-en-message' ).textContent ).toBe( 'Address updated.' );
	} );

	test( 'an error is shown as text and the entry kept', () => {
		const ajax = loadScript( 'wpdr-en-address', listScreen() );
		document.getElementById( 'wpdr-en-email' ).value = 'bad';
		asOnclick( wpdr_en_insert );

		lastRequest( ajax ).success( { error: true, error_msg: 'Invalid <b>email</b> address', error_code: 'F-17' } );

		expect( document.getElementById( 'wpdr-en-message' ).textContent ).toBe( 'Invalid <b>email</b> address ( F-17 )' );
		expect( document.getElementById( 'wpdr-en-email' ).value ).toBe( 'bad' );
		expect( document.getElementById( 'current-list' ).textContent ).toBe( 'Listold' );
	} );

	test( 'an invalid email address marks the field and takes focus to it', () => {
		const ajax  = loadScript( 'wpdr-en-address', listScreen() );
		const email = document.getElementById( 'wpdr-en-email' );
		email.value = 'bad';
		asOnclick( wpdr_en_insert );

		lastRequest( ajax ).success( { error: true, error_msg: 'Invalid email address', error_code: 'F-17' } );

		expect( email.getAttribute( 'aria-invalid' ) ).toBe( 'true' );
		expect( email.getAttribute( 'aria-describedby' ) ).toBe( 'wpdr-en-message wpdr-en-entry-help' );
		expect( document.activeElement ).toBe( email );

		// Typing again clears the mark; a successful add resets the description.
		email.value = 'good@example.org';
		email.dispatchEvent( new Event( 'input' ) );
		expect( email.hasAttribute( 'aria-invalid' ) ).toBe( false );
		asOnclick( wpdr_en_insert );
		lastRequest( ajax ).success( { error: false, error_msg: JSON.stringify( '<p>table</p>' ) } );
		expect( email.getAttribute( 'aria-describedby' ) ).toBe( 'wpdr-en-entry-help' );
	} );

	test( 'other errors leave the email field alone', () => {
		const ajax = loadScript( 'wpdr-en-address', listScreen() );
		asOnclick( wpdr_en_insert );

		lastRequest( ajax ).success( { error: true, error_msg: 'Something went wrong', error_code: 'F-11' } );

		expect( document.getElementById( 'wpdr-en-email' ).hasAttribute( 'aria-invalid' ) ).toBe( false );
	} );

	test( 'Delete asks first, then posts the List and record number', () => {
		const ajax = loadScript( 'wpdr-en-address', listScreen() );

		const event = asOnclick( wpdr_en_delete, 12, 3 );

		expect( window.confirm ).toHaveBeenCalledWith( 'Delete this address from the List? This cannot be undone.' );
		const req = lastRequest( ajax );
		expect( req.data ).toEqual( { action: 'wpdr_en_del_address', post_id: 12, del_rec: 3, wpdr_en_nonce: 'nonce123' } );
		expect( event.defaultPrevented ).toBe( true );
		req.success( { error: false, error_msg: JSON.stringify( '<h2 id="wpdr-en-list-heading" tabindex="-1">List</h2><p>empty</p>' ) } );
		expect( document.getElementById( 'current-list' ).innerHTML ).toBe( '<h2 id="wpdr-en-list-heading" tabindex="-1">List</h2><p>empty</p>' );
		// Announced, and focus moves to the table heading (the Delete button is gone).
		expect( document.getElementById( 'wpdr-en-message' ).textContent ).toBe( 'Address deleted from the List.' );
		expect( document.activeElement.id ).toBe( 'wpdr-en-list-heading' );
	} );

	test( 'cancelling the Delete question deletes nothing', () => {
		const ajax = loadScript( 'wpdr-en-address', listScreen() );
		window.confirm.mockReturnValueOnce( false );

		const event = asOnclick( wpdr_en_delete, 12, 3 );

		expect( ajax ).not.toHaveBeenCalled();
		expect( event.defaultPrevented ).toBe( true );
	} );

	test( 'Edit copies the row into the entry; Clear resets it', () => {
		loadScript( 'wpdr-en-address', listScreen() );
		const btn = document.createElement( 'button' );
		btn.dataset.userName = 'Bob "B"';
		btn.dataset.email    = 'bob@example.org';
		btn.dataset.pause    = '1';

		asOnclick( wpdr_en_edit, btn );

		expect( document.getElementById( 'wpdr-en-user-name' ).value ).toBe( 'Bob "B"' );
		expect( document.getElementById( 'wpdr-en-email' ).disabled ).toBe( true );
		expect( document.getElementById( 'wpdr_pause' ).checked ).toBe( true );
		expect( document.getElementById( 'add_address' ).value ).toBe( 'Edit existing Address' );
		expect( document.getElementById( 'clear_address' ).style.display ).toBe( 'block' );
		// Focus goes to the values being edited.
		expect( document.activeElement.id ).toBe( 'wpdr-en-user-name' );

		document.getElementById( 'add_address' ).focus();
		const event = asOnclick( wpdr_en_clear );

		expect( event.defaultPrevented ).toBe( true );
		expect( document.getElementById( 'wpdr-en-user-name' ).value ).toBe( '' );
		expect( document.getElementById( 'wpdr-en-email' ).disabled ).toBe( false );
		expect( document.getElementById( 'add_address' ).value ).toBe( 'Add Address to List' );
		expect( document.getElementById( 'clear_address' ).style.display ).toBe( 'none' );
		expect( document.activeElement.id ).toBe( 'wpdr-en-user-name' );
	} );

	test( 'Add is enabled only with a name and an email', () => {
		loadScript( 'wpdr-en-address', listScreen() );
		const add = document.getElementById( 'add_address' );

		document.getElementById( 'wpdr-en-user-name' ).value = 'Ann';
		check_address();
		expect( add.disabled ).toBe( true );

		document.getElementById( 'wpdr-en-email' ).value = ' ann@example.org ';
		check_address();
		expect( add.disabled ).toBe( false );

		document.getElementById( 'wpdr-en-user-name' ).value = '  ';
		check_address();
		expect( add.disabled ).toBe( true );
	} );

	test( 'Add is enabled as the user types, not only on leaving a field', () => {
		loadScript( 'wpdr-en-address', listScreen() );
		const type = ( id, value ) => {
			const field = document.getElementById( id );
			field.value = value;
			field.dispatchEvent( new Event( 'input' ) );
		};

		type( 'wpdr-en-user-name', 'Ann' );
		expect( document.getElementById( 'add_address' ).disabled ).toBe( true );
		type( 'wpdr-en-email', 'a' );
		expect( document.getElementById( 'add_address' ).disabled ).toBe( false );
	} );

	test( 'functions called without an event (e.g. from code) do not fail', () => {
		loadScript( 'wpdr-en-address', listScreen() );

		expect( () => wpdr_en_clear() ).not.toThrow();
	} );
} );

describe( 'edit screen', () => {
	test( 'searching the address table keeps the edit action', () => {
		loadScript( 'wpdr-en-address', listScreen() );

		document.getElementById( 'search-submit' ).addEventListener( 'click', ( e ) => e.preventDefault() );
		document.getElementById( 'search-submit' ).click();

		expect( document.getElementById( 'hiddenaction' ).value ).toBe( 'edit' );
	} );

	test( 'a "List updated" message is removed when the save was refused', () => {
		loadScript(
			'wpdr-en-address',
			'<div id="message" class="updated">List updated.</div><div id="wpdr_en_message" class="notice notice-error"></div>'
		);
		expect( document.getElementById( 'message' ) ).toBeNull();

		loadScript( 'wpdr-en-address', '<div id="message" class="updated">List updated.</div><div id="wpdr_en_message" class="notice notice-warning"></div>' );
		expect( document.getElementById( 'message' ) ).not.toBeNull();
	} );

	test( 'unsaved term choices are restored', () => {
		loadScript( 'wpdr-en-address', '<input type="checkbox" id="in-category-5"><input type="checkbox" id="in-category-6" checked>' );

		revert_unsaved_hier( 'category', 5, true );
		revert_unsaved_hier( 'category', 6, false );
		revert_unsaved_hier( 'category', 99, true );

		expect( document.getElementById( 'in-category-5' ).checked ).toBe( true );
		expect( document.getElementById( 'in-category-6' ).checked ).toBe( false );
	} );
} );

describe( 'Quick Edit', () => {
	/**
	 * The Lists table with one row and its open Quick Edit row.
	 *
	 * @param {Object} values tm, attach and pause values in the row.
	 * @return {string} HTML.
	 */
	function listTable( values ) {
		return `
			<table><tbody id="the-list">
			<tr id="post-8"><td>
				<input type="hidden" id="tm-rule-8" value="${ values.tm }">
				<input type="checkbox" id="attach-8" value="${ values.attach }" disabled>
				<input type="checkbox" id="pause-8" value="${ values.pause }" disabled>
				<button type="button" class="editinline">Quick Edit</button>
			</td></tr>
			<tr id="edit-8" class="inline-edit-row"><td>
				<input type="radio" name="tm_rule" value="0">
				<input type="radio" name="tm_rule" value="1">
				<input type="checkbox" name="wpdr_en_attach" value="">
				<input type="checkbox" name="wpdr_en_pause" value="">
			</td></tr>
			<tr id="bulk-edit" class="inline-edit-row"><td></td></tr>
			</tbody></table>`;
	}

	test( 'opening Quick Edit copies the row settings into it (the Bulk Edit row is left alone)', () => {
		loadScript( 'wpdr-en-address', listTable( { tm: 1, attach: 1, pause: 0 } ) );

		document.querySelector( '.editinline' ).click();

		const row = document.getElementById( 'edit-8' );
		expect( row.querySelector( '[name="tm_rule"][value="1"]' ).checked ).toBe( true );
		expect( row.querySelector( '[name="tm_rule"][value="0"]' ).checked ).toBe( false );
		expect( row.querySelector( '[name="wpdr_en_attach"]' ).checked ).toBe( true );
		expect( row.querySelector( '[name="wpdr_en_pause"]' ).checked ).toBe( false );
	} );

	test( 'ticking a box in Quick Edit updates its value', () => {
		loadScript( 'wpdr-en-address', listTable( { tm: 0, attach: 0, pause: 0 } ) );
		// Pressing Enter on the Quick Edit button clicks it, so there is no separate key handler.
		document.querySelector( '.editinline' ).click();
		const pause = document.querySelector( '#edit-8 [name="wpdr_en_pause"]' );

		pause.click();

		expect( pause.value ).toBe( '1' );
		expect( document.querySelector( '#edit-8 [name="tm_rule"][value="0"]' ).checked ).toBe( true );
	} );

	test( 'other clicks in the table change nothing', () => {
		loadScript( 'wpdr-en-address', listTable( { tm: 1, attach: 1, pause: 1 } ) );

		document.getElementById( 'tm-rule-8' ).click();

		expect( document.querySelector( '#edit-8 [name="tm_rule"][value="1"]' ).checked ).toBe( false );
	} );
} );
