/*
// Scripts for the Document External List edit screen (address list, Quick Edit).
// @version: 4.0
//
// #wpdr-en-message is a status region (role="status"): what is written to it is announced by screen readers.
*/

/**
 * Stop the button's form submit: these buttons sit inside the List's edit form.
 *
 * Called from inline onclick handlers, so the event is the window's current event.
 */
function wpdr_en_stop() {
	const evt = window.event;
	if ( evt ) {
		evt.stopPropagation();
		evt.preventDefault();
	}
}

/**
 * Show the List's address table returned by an address change, and reset the entry fields.
 *
 * The result is written to the status region (so it is announced), and focus is put somewhere useful:
 * replacing the table removes the button that was pressed.
 *
 * @param {Object} response AJAX response; error_msg holds the table HTML, JSON-encoded.
 * @param {string} done     Message to show when the change succeeded.
 * @param {string} focus_id Element to focus when the change succeeded.
 */
function wpdr_en_address_done( response, done, focus_id ) {
	const message = document.getElementById( 'wpdr-en-message' );
	if ( response.error ) {
		message.textContent = response.error_msg + ' ( ' + response.error_code + ' )';
		if ( 'F-17' === response.error_code ) {
			// Invalid email address: mark the field and take the user to it.
			const email = document.getElementById( 'wpdr-en-email' );
			email.setAttribute( 'aria-invalid', 'true' );
			email.setAttribute( 'aria-describedby', 'wpdr-en-message wpdr-en-entry-help' );
			email.focus();
		}
		return;
	}
	wpdr_en_reset_entry();
	document.getElementById( 'current-list' ).innerHTML = JSON.parse( response.error_msg );
	message.textContent = done;
	const target = document.getElementById( focus_id );
	if ( target ) {
		target.focus();
	}
}

/**
 * Empty the address entry fields and return the button to "Add".
 */
function wpdr_en_reset_entry() {
	document.getElementById("wpdr-en-user-name").value     = "";
	document.getElementById("wpdr-en-email").value         = "";
	document.getElementById("wpdr-en-email").disabled      = false;
	document.getElementById("wpdr-en-email").removeAttribute( 'aria-invalid' );
	document.getElementById("wpdr-en-email").setAttribute( 'aria-describedby', 'wpdr-en-entry-help' );
	document.getElementById("wpdr_pause").checked          = false;
	document.getElementById("wpdr_pause").disabled         = false;
	document.getElementById("clear_address").style.display = "none";
	document.getElementById("add_address").value           = wpdr_en_obj.add_address;
	document.getElementById("add_address").disabled        = true;
}

function wpdr_en_insert() {
	const id        = document.getElementById("post_ID").value;
	const user_name = document.getElementById("wpdr-en-user-name").value;
	const email     = document.getElementById("wpdr-en-email").value;
	const pause     = + document.getElementById("wpdr_pause").checked;
	// The button reads "Edit existing Address" while an address is being edited.
	const done      = ( document.getElementById("add_address").value === wpdr_en_obj.edit_address ? wpdr_en_obj.address_updated : wpdr_en_obj.address_added );
	// Add the message.
	jQuery.ajax({
		type     : 'POST',
		dataType : 'json',
		url      : wpdr_en_obj.ajaxurl,
		data     : {
			action	      : 'wpdr_en_add_address',
			post_id       : id,
			user_name     : user_name,
			email         : email,
			pause         : pause,
			wpdr_en_nonce : wpdr_en_obj.wpdr_en_nonce
		},
		success  : function ( response ) {
			// Ready for the next address.
			wpdr_en_address_done( response, done, 'wpdr-en-user-name' );
		}
	});
	wpdr_en_stop();
}

function wpdr_en_delete( id, rec_num ) {
	wpdr_en_stop();
	// Deleting cannot be undone.
	if ( ! window.confirm( wpdr_en_obj.confirm_delete ) ) {
		return;
	}
	jQuery.ajax({
		type     : 'POST',
		dataType : 'json',
		url      : wpdr_en_obj.ajaxurl,
		data     : {
			action	      : 'wpdr_en_del_address',
			post_id       : id,
			del_rec       : rec_num,
			wpdr_en_nonce : wpdr_en_obj.wpdr_en_nonce
		},
		success  : function ( response ) {
			// The Delete button pressed is gone: focus the table heading.
			wpdr_en_address_done( response, wpdr_en_obj.address_deleted, 'wpdr-en-list-heading' );
		}
	});
}

function wpdr_en_edit( btn ) {
	// Values come from the button's data attributes (escaped server-side).
	document.getElementById("wpdr-en-user-name").value     = btn.dataset.userName;
	document.getElementById("wpdr-en-email").value         = btn.dataset.email;
	document.getElementById("wpdr-en-email").disabled      = true;
	document.getElementById("wpdr_pause").checked          = ( 1 == btn.dataset.pause );
	document.getElementById("clear_address").style.display = "block";
	document.getElementById("add_address").value           = wpdr_en_obj.edit_address;
	document.getElementById("add_address").disabled        = false;
	document.getElementById("user-entry").scrollIntoView();
	// Take keyboard and screen reader users to the values being edited.
	document.getElementById("wpdr-en-user-name").focus();
	wpdr_en_stop();
}

function wpdr_en_clear() {
	wpdr_en_reset_entry();
	document.getElementById("user-entry").scrollIntoView();
	document.getElementById("wpdr-en-user-name").focus();
	wpdr_en_stop();
}

/**
 * Re-applies a hierarchical term (un)selection that was lost when publishing the List was refused.
 *
 * Called from the inline script added by check_error_state(); the checkbox ids are WordPress's own.
 *
 * @param {string}  tax     Taxonomy name.
 * @param {number}  term    Term ID.
 * @param {boolean} checked Whether the term should be ticked.
 */
function revert_unsaved_hier( tax, term, checked ) {
	const box = document.getElementById( 'in-' + tax + '-' + term );
	if ( box ) {
		box.checked = checked;
	}
}

function check_address() {
	var user  = document.getElementById("wpdr-en-user-name").value.trim().length == 0;
	var email = document.getElementById("wpdr-en-email").value.trim().length == 0;
	if ( user || email ) {
		document.getElementById("add_address").disabled = true;
	} else {
		document.getElementById("add_address").disabled = false;
	}
}

function wpdr_en_search() {
	document.getElementById("hiddenaction").setAttribute("value", "edit");
}

function wpdr_en_attr_value( edit_row ) {
	const row = document.getElementById( edit_row );
	row.querySelector('[name="wpdr_en_attach"]').value = +row.querySelector('[name="wpdr_en_attach"]').checked;
	row.querySelector('[name="wpdr_en_pause"]').value  = +row.querySelector('[name="wpdr_en_pause"]').checked;
}

/**
 * Copies the current attribute values into the Quick Edit row.
 *
 * @param {Event} event Click on the list table (pressing Enter or Space on a button also clicks it).
 */
function set_qe_values( event ) {
	let tag = event.target.tagName.toLowerCase();
	let cls = event.target.classList;
	if ( tag === "button" && cls.contains( "editinline" ) ) {
		// clicked on Quick Edit.

		// open rows, look to validate the qe row.
		let open = document.getElementsByClassName( "inline-edit-row" );
		for( let item of open ) {
			if ( item.id.substring(0,5) !== 'edit-' ) {
				continue;
			}

			// propagate values from the post row into the edit row..
			let post_id  = item.id.substring(5);
			let post_row = document.getElementById( "post-" + post_id );
			let edit_row = document.getElementById( "edit-" + post_id );
			let attr_val = post_row.querySelector( "#tm-rule-" + post_id ).value;
			// By value, as the radios have no ids (they would be repeated in every Quick Edit row).
			edit_row.querySelector('[name="tm_rule"][value="0"]').checked = ( 0 == attr_val );
			edit_row.querySelector('[name="tm_rule"][value="1"]').checked = ( 1 == attr_val );
			attr_val = post_row.querySelector( "#attach-" + post_id ).value;
			let att     = edit_row.querySelector('[name="wpdr_en_attach"]');
			att.value   = attr_val;
			att.checked = ( 1 == attr_val );
			att.addEventListener( "click", event => { wpdr_en_attr_value(item.id) } );
			attr_val = post_row.querySelector( "#pause-" + post_id ).value;
			let pse     = edit_row.querySelector('[name="wpdr_en_pause"]');
			pse.value   = attr_val;
			pse.checked = ( 1 == attr_val );
			pse.addEventListener( "click", event => { wpdr_en_attr_value(item.id) } );
		}
	}
}

document.addEventListener('DOMContentLoaded', function() {
	// Note that we are in the same form.
	var search = document.getElementById("search-submit");
	if ( search !== null ) {
		search.addEventListener( "click", wpdr_en_search );
	}
	// if host-side update failed, suppress any erroneous sucess message.
	var msg = document.getElementById("message");
	if ( msg !== null && msg.classList.contains("updated") ) {
		var err = document.getElementById("wpdr_en_message");
		if ( err !== null && err.classList.contains("notice-error") ) {
			msg.remove();
		}
	}

	// make sure quick edit parameters are shown with the correct values.
	const list = document.getElementById("the-list");
	if ( list !== null ) {
		// add to parent to ensure elements attached to this are executed first.
		list.parentElement.addEventListener('click', event => {
			set_qe_values( event );
		});
	}

	// Enable or disable Add as the user types (not only when leaving a field).
	for ( const id of [ 'wpdr-en-user-name', 'wpdr-en-email' ] ) {
		const field = document.getElementById( id );
		if ( field ) {
			field.addEventListener( 'input', function () {
				field.removeAttribute( 'aria-invalid' );
				check_address();
			} );
		}
	}

})
