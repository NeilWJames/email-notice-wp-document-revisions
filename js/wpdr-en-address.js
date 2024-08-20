function wpdr_en_insert() {
	id        = document.getElementById("post_ID").value;
	user_name = document.getElementById("wpdr-en-user-name").value;
	email     = document.getElementById("wpdr-en-email").value;
	pause     = + document.getElementById("wpdr_pause").checked;
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
			userid        : wpdr_en_obj.user,
			wpdr_en_nonce : wpdr_en_obj.wpdr_en_nonce
		},
		success  : function(response) {
			if (response.error) {
				document.getElementById('wpdr-en-message').innerHTML=response.error_msg + ' ( ' + response.error_code + ' )';					
			} else {					
				//Emails sent successfully					
				document.getElementById('wpdr-en-message').innerHTML   = '';					
				document.getElementById("wpdr-en-user-name").value     = "";
				document.getElementById("wpdr-en-email").value         = "";
				document.getElementById("wpdr-en-email").disabled      = false;
				document.getElementById("wpdr_pause").checked          = false;
				document.getElementById("wpdr_pause").disabled         = false;
				document.getElementById("clear_address").style.display = "none";
				document.getElementById("add_address").value           = wpdr_en_obj.add_address;
				document.getElementById("add_address").disabled        = true;
				var rpt = JSON.parse(response.error_msg);
				document.getElementById("current-list").innerHTML = rpt;
				document.getElementById("current-list").location.reload();
			}
		}
	}); 		
	event.stopPropagation();
	event.preventDefault();
}

function wpdr_en_delete( id, rec_num ) {
	// Add the message.
	jQuery.ajax({
		type     : 'POST',
		dataType : 'json',
		url      : wpdr_en_obj.ajaxurl,
		data     : {
			action	      : 'wpdr_en_del_address',
			post_id       : id,
			del_rec       : rec_num,
			userid        : wpdr_en_obj.user,
			wpdr_en_nonce : wpdr_en_obj.wpdr_en_nonce
		},
		success  : function(response) {
			if (response.error) {
				document.getElementById('wpdr-en-message').innerHTML=response.error_msg + ' ( ' + response.error_code + ' )';					
			} else {					
				//Emails sent successfully					
				document.getElementById('wpdr-en-message').innerHTML   = "";
				document.getElementById("wpdr-en-user-name").value     = "";
				document.getElementById("wpdr-en-email").value         = "";
				document.getElementById("wpdr-en-email").disabled      = false;
				document.getElementById("wpdr_pause").checked          = false;
				document.getElementById("wpdr_pause").disabled         = false;
				document.getElementById("clear_address").style.display = "none";
				document.getElementById("add_address").disabled        = true;
				var rpt = JSON.parse(response.error_msg);
				document.getElementById("current-list").innerHTML = rpt;
				document.getElementById("current-list").location.reload();
			}
		}
	});
	event.stopPropagation();
	event.preventDefault();
}

function wpdr_en_edit( user_name, email, pause ) {
	document.getElementById("wpdr-en-user-name").value     = user_name;
	document.getElementById("wpdr-en-email").value         = email;
	document.getElementById("wpdr-en-email").disabled      = true;
	document.getElementById("wpdr_pause").checked          = ( 1 == pause );
	document.getElementById("clear_address").style.display = "block";
	document.getElementById("add_address").value           = wpdr_en_obj.edit_address;
	document.getElementById("add_address").disabled        = false;
	document.getElementById("user-entry").scrollIntoView();
	event.stopPropagation();
	event.preventDefault();
}

function wpdr_en_clear() {
	document.getElementById("wpdr-en-user-name").value     = "";
	document.getElementById("wpdr-en-email").value         = "";
	document.getElementById("wpdr-en-email").disabled      = false;
	document.getElementById("wpdr_pause").checked          = false;
	document.getElementById("wpdr_pause").disabled         = false;
document.getElementById("clear_address").style.display     = "none";
	document.getElementById("add_address").value           = wpdr_en_obj.add_address;
	document.getElementById("add_address").disabled        = true;
	document.getElementById("user-entry").scrollIntoView();
	event.stopPropagation();
	event.preventDefault();
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
 * Copies the cueent attribute vales into the QE equivalent..
 */
function set_qe_values() {
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
			edit_row.querySelector('#tm_any').checked = ( 0 == attr_val );
			edit_row.querySelector('#tm_all').checked = ( 1 == attr_val );
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

	// Make sure attach/pause attributes value updated.
	//document.getElementById("wpdr_en_attach").addEventListener( "click", wpdr_en_attr_value );
	//document.getElementById("wpdr_en_pause").addEventListener( "click", wpdr_en_attr_value );

	// make sure quick edit parameters are shown with the correct values.
	const list = document.getElementById("the-list");
	if ( list !== null ) {
		// add to parent to ensure elements attached to this are executed first.
		list.parentElement.addEventListener('click', event => {
			set_qe_values();
		});
		list.parentElement.addEventListener('keypress', event => {
			set_qe_values();
		});
	}

})
