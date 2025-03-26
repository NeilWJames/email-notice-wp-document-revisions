/*
// Scripts for WPDR Custom Email Settings
// @version: 3.1
*/
document.addEventListener('DOMContentLoaded', function() {
	/************************************************************************************************************/
	/* Invoke notification sending */
	/************************************************************************************************************/	    
	if ( ! !! document.getElementById("wpdr-en-notify") ) {
		return;
	}

	document.getElementById("wpdr-en-notify").addEventListener('click', event => {
		//Disable button
		document.getElementById("wpdr-en-notify").disabled = true;
	
		//Display loading icon while it is sending out emails
		document.getElementById('dProgress').style.display = 'inline-block';		
		document.getElementById('wpdr-en-message').style.display = 'inline-block';	
		document.getElementById('wpdr-en-message').innerHTML=wpdr_en_obj.sending_mails;		
				
		jQuery('#dProgress')
			.ajaxStart(function() {								
				$(this).show();
			})
			.ajaxStop(function() {
				$(this).hide();				
				$(this).unbind("ajaxStart"); // added to fix unnecessary turning on the loading icon by other scripts running on the page.
			})
		;

		//Get the post id from post hidden input
		var postid = document.getElementById('post_ID').value;
		
		// Get extra text
		var extra = document.getElementById('wpdr-en-extra').value;
		if ( ! document.getElementById('wpdr-en-int-extra').checked ) {
			extra = '';
		};
		if ( extra.length > 0 ) {
			extra = '<br/>' + extra;
		}

		//Send emails
		jQuery.ajax({
			type     : 'POST',
			dataType : 'json',
			url      : wpdr_en_obj.ajaxurl,
			data     : {
				 action	       : 'wpdr_en_send_notification_manual',
				 post_id       : postid,
				 extra         : extra,
				 wpdr_en_nonce : wpdr_en_obj.wpdr_en_nonce
			},
			success  : function(response) {
				//to debug uncomment the following line
				/*
				alert (	'error:'+response.error+'\n'+
						'error_msg:'+response.error_msg+'\n'+					
						'logged_count: '+response.logged_count+'\n'+
						'sent_count:'+response.sent_count+'\n'+
						'sending_error_count:'+response.sending_error_count+'\n'+
						'error_code:'+response.error_code+'\n'+
						'log_page_url:'+response.log_page_url);
				*/
				if (response.error) {
					if (response.logged_count==0 || response.sent_count==0) {
						document.getElementById('wpdr-en-message').innerHTML=wpdr_en_obj.error_sending;
						alert (response.error_msg + '\n\n[Error: '+ response.error_code + ']');
					}
					else {
						document.getElementById('wpdr-en-message').innerHTML=response.sent_count + ' ' + wpdr_en_obj.emails_out_of + ' ' + (response.sent_count+response.sending_error_count) + ' '+wpdr_en_obj.sent_with+' ' + (response.sent_count+response.sending_error_count-response.logged_count) + ' '+wpdr_en_obj.log_issues+' <a href="' + response.log_page_url + '">' + wpdr_en_obj.log + '</a> '+wpdr_en_obj.for_details;					
						document.getElementById('wpdr-en-notify').value = wpdr_en_obj.resend;
					}
				} else {					
						//Emails sent successfully					
						document.getElementById('wpdr-en-message').innerHTML='<br>' + response.sent_count + ' ' + wpdr_en_obj.notif_sent_check + ' <a href="' + response.log_page_url + '">'+ wpdr_en_obj.log + '</a> ' +wpdr_en_obj.for_details;
						document.getElementById('wpdr-en-notify').value = wpdr_en_obj.resend;
				}
			}
		}); 		
		//Re-enable button
		document.getElementById('dProgress').style.display = 'none';		
		document.getElementById("wpdr-en-notify").disabled = false;		
	});			

	document.getElementById("wpdr-en-ext-note").addEventListener('click', event => {
		//Disable button
		document.getElementById("wpdr-en-ext-note").disabled = true;
	
		//Display loading icon while it is sending out emails
		document.getElementById('dProgress').style.display = 'inline-block';		
		document.getElementById('wpdr-en-message').style.display = 'inline-block';	
		document.getElementById('wpdr-en-message').innerHTML=wpdr_en_obj.sending_mails;		
				
		jQuery('#dProgress')
			.ajaxStart(function() {								
				$(this).show();
			})
			.ajaxStop(function() {
				$(this).hide();				
				$(this).unbind("ajaxStart"); // added to fix unnecessary turning on the loading icon by other scripts running on the page.
			})
		;

		//Get the post id from post hidden input
		var postid = document.getElementById('post_ID').value;

		// Get the lists.Return empty array when all wanted, otherwise the array of wanted.
		// Note. A valid empty list is not allowed.
		var olist = [];
		var lists = document.querySelectorAll("input[name='wpdr-en-ext-list']:checked");
		if ( lists.length > 0 ) {
			for ( let list of lists ) {
				olist.push(list.value);
			}
		}

		// Get extra text
		var extra = document.getElementById('wpdr-en-extra').value;
		if ( ! document.getElementById('wpdr-en-ext-extra').checked ) {
			extra = '';
		};
		if ( extra.length > 0 ) {
			extra = '<br/>' + extra;
		}

		//Send emails
		jQuery.ajax({
			type     : 'POST',
			dataType : 'json',
			url      : wpdr_en_obj.ajaxurl,
			data     : {
				 action	       : 'wpdr_en_send_ext_notice_manual',
				 post_id       : postid,
				 lists         : olist,
				 extra         : extra,
				 wpdr_en_nonce : wpdr_en_obj.wpdr_en_nonce
			},
			success  : function(response) {
				//to debug uncomment the following line
				/*
				alert (	'error:'+response.error+'\n'+
						'error_msg:'+response.error_msg+'\n'+					
						'logged_count: '+response.logged_count+'\n'+
						'sent_count:'+response.sent_count+'\n'+
						'sending_error_count:'+response.sending_error_count+'\n'+
						'error_code:'+response.error_code+'\n'+
						'log_page_url:'+response.log_page_url);
				*/
				if (response.error) {
					if (response.logged_count==0 || response.sent_count==0) {
						document.getElementById('wpdr-en-message').innerHTML=wpdr_en_obj.error_sending;
						alert (response.error_msg + '\n\n[Error: '+ response.error_code + ']');
					}
					else {
						document.getElementById('wpdr-en-message').innerHTML=response.sent_count + ' ' + wpdr_en_obj.emails_out_of + ' ' + (response.sent_count+response.sending_error_count) + ' '+wpdr_en_obj.sent_with+' ' + (response.sent_count+response.sending_error_count-response.logged_count) + ' '+wpdr_en_obj.log_issues+' <a href="' + response.log_page_url + '">' + wpdr_en_obj.log + '</a> '+wpdr_en_obj.for_details;					
						document.getElementById('wpdr-en-ext-note').value = wpdr_en_obj.resend;
					}
				} else {					
						//Emails sent successfully					
						document.getElementById('wpdr-en-message').innerHTML='<br>' + response.sent_count + ' ' + wpdr_en_obj.notif_sent_check + ' <a href="' + response.log_page_url + '">'+ wpdr_en_obj.log + '</a> ' +wpdr_en_obj.for_details;
						document.getElementById('wpdr-en-ext-note').value = wpdr_en_obj.resend;
				}
			}
		}); 		
		//Re-enable button
		document.getElementById('dProgress').style.display = 'none';		
		document.getElementById("wpdr-en-ext-note").disabled = false;		
	});

	for ( let item of document.getElementsByName("wpdr-en-ext-list") ) {
		item.addEventListener('click', event => {
			// set button disabled if no list selected.
			document.getElementById("wpdr-en-ext-note").disabled = ( document.querySelectorAll("input[name^='wpdr-en-ext-list']:checked").length === 0 );
		});
	}

	// This is fireable only when one or other mail send button is not disabled.
	document.getElementById("wpdr-en-extra").addEventListener('input', event => {
		// The target is the textarea.
		const mty_text = ( 0 === event.target.value.length );
		const int_butt = document.getElementById("wpdr-en-notify").disabled;
		const ext_butt = document.getElementById("wpdr-en-ext-note").disabled;
		if ( ! int_butt ) {
			const int_add = document.getElementById("wpdr-en-int-extra");
			//check that it is active.
			if ( ! int_add.classList.contains("wpdr_en_not_use") ) {
				int_add.disabled = mty_text;
				if ( mty_text ) {
					int_add.checked = false;
				}
			}
		}

		if ( ! ext_butt ) {
			//check that it is active.
			const ext_add = document.getElementById("wpdr-en-ext-extra");
			if ( ! ext_add.classList.contains("wpdr_en_not_use") ) {
				ext_add.disabled = mty_text;
				if ( mty_text ) {
					ext_add.checked = false;
				}
			}
		}
	});
});
