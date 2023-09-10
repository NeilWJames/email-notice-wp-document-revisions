function wpdr_en_insert() {
	id        = document.getElementById("post_ID").value;
	user_name = document.getElementById("wpdr-en-user-name").value;
	email     = document.getElementById("wpdr-en-email").value;
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

function wpdr_en_edit( user_name, email ) {
	document.getElementById("wpdr-en-user-name").value     = user_name;
	document.getElementById("wpdr-en-email").value         = email;
	document.getElementById("wpdr-en-email").disabled      = true;
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
	document.getElementById("clear_address").style.display = "none";
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

function revert_unsaved_hier( tax, term, state ) {
	document.getElementById("in-"+tax+"-"+term).checked = state;
	document.getElementById("in-popular-"+tax+"-"+term).checked = state;
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

})
