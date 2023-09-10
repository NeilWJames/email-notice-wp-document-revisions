/*
// Scripts for WPDR Custom Email Settings
// @version: 1.0
*/
document.addEventListener('DOMContentLoaded', function() {
	/************************************************************************************************************/
	/* Invoke notification sending */
	/************************************************************************************************************/	    
	if ( ! !!document.getElementById("wpdr-en-notify") ) {
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

		//Get the post id from hidden input
		var postid = document.getElementById('wpdr-en-notification-postid').value;			
		
		//Send e-mails
		jQuery.ajax({
			type     : 'POST',
			dataType : 'json',
			url      : wpdr_en_obj.ajaxurl,
			data     : {
				 action	       : 'wpdr_en_send_notification_manual',
				 post_id       : postid,
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

		//Get the post id from hidden input
		var postid = document.getElementById('wpdr-en-notification-postid').value;			
		
		//Send e-mails
		jQuery.ajax({
			type     : 'POST',
			dataType : 'json',
			url      : wpdr_en_obj.ajaxurl,
			data     : {
				 action	       : 'wpdr_en_send_ext_notice_manual',
				 post_id       : postid,
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
});