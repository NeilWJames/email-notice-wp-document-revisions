/*
// Scripts for WPDR Custom Email Settings (Document Email Notifications metabox).
// @version: 4.0
*/

/**
 * Send notifications for the Document being edited and show the result.
 *
 * The button is disabled and the progress icon shown until the request finishes.
 *
 * @param {HTMLInputElement} button   The Send button clicked.
 * @param {string}           action   AJAX action.
 * @param {Object}           data     Extra request fields (lists).
 * @param {HTMLInputElement} use_extra Checkbox that adds the extra text (may be missing).
 */
function wpdr_en_send( button, action, data, use_extra ) {
	const progress = document.getElementById( 'dProgress' );
	const message  = document.getElementById( 'wpdr-en-message' );

	button.disabled          = true;
	progress.style.display   = 'inline-block';
	message.style.display    = 'inline-block';
	message.textContent      = wpdr_en_obj.sending_mails;

	// Extra text is only sent when its checkbox is ticked.
	const extra_box = document.getElementById( 'wpdr-en-extra' );
	let extra       = ( extra_box && use_extra && use_extra.checked ? extra_box.value : '' );
	if ( extra.length > 0 ) {
		extra = '<br/>' + extra;
	}

	jQuery.ajax( {
		type     : 'POST',
		dataType : 'json',
		url      : wpdr_en_obj.ajaxurl,
		data     : Object.assign(
			{
				action        : action,
				post_id       : document.getElementById( 'post_ID' ).value,
				extra         : extra,
				wpdr_en_nonce : wpdr_en_obj.wpdr_en_nonce
			},
			data
		),
		success  : function ( response ) {
			message.innerHTML = wpdr_en_result( response );
			if ( ! response.error || ( response.logged_count > 0 && response.sent_count > 0 ) ) {
				// Something was sent: the button now re-sends.
				button.value = wpdr_en_obj.resend;
			} else {
				alert( response.error_msg + '\n\n[Error: ' + response.error_code + ']' );
			}
		},
		error    : function () {
			message.textContent = wpdr_en_obj.error_sending;
		},
		complete : function () {
			progress.style.display = 'none';
			button.disabled        = false;
		}
	} );
}

/**
 * The message (HTML) for a send result.
 *
 * Counts are numbers and the log link comes from the site, so neither can add markup.
 *
 * @param {Object} response AJAX response.
 * @return {string} HTML.
 */
function wpdr_en_result( response ) {
	const sent   = parseInt( response.sent_count, 10 ) || 0;
	const errors = parseInt( response.sending_error_count, 10 ) || 0;
	const logged = parseInt( response.logged_count, 10 ) || 0;
	const link   = document.createElement( 'a' );
	link.href        = response.log_page_url || '';
	link.textContent = wpdr_en_obj.log;

	if ( ! response.error ) {
		// Emails sent successfully.
		return '<br>' + sent + ' ' + wpdr_en_obj.notif_sent_check + ' ' + link.outerHTML + ' ' + wpdr_en_obj.for_details;
	}
	if ( 0 === logged || 0 === sent ) {
		return wpdr_en_obj.error_sending;
	}
	// Some sent: say how many, and how many were not logged.
	return sent + ' ' + wpdr_en_obj.email_out_of + ' ' + ( sent + errors ) + ' ' + wpdr_en_obj.sent_with + ' ' +
		( sent + errors - logged ) + ' ' + wpdr_en_obj.log_issues + ' ' + link.outerHTML + ' ' + wpdr_en_obj.for_details;
}

document.addEventListener( 'DOMContentLoaded', function () {
	// Either section may be absent: internal if no roles can receive mail, external without read_doc_ext_lists.
	const notify   = document.getElementById( 'wpdr-en-notify' );
	const ext_note = document.getElementById( 'wpdr-en-ext-note' );
	if ( ! notify && ! ext_note ) {
		return;
	}

	if ( notify ) {
		notify.addEventListener( 'click', function () {
			wpdr_en_send( notify, 'wpdr_en_send_notification_manual', {}, document.getElementById( 'wpdr-en-int-extra' ) );
		} );
	}

	if ( ext_note ) {
		ext_note.addEventListener( 'click', function () {
			// The ticked lists (the server sends to all matching lists when none is given).
			const lists = [];
			for ( const list of document.querySelectorAll( "input[name='wpdr-en-ext-list']:checked" ) ) {
				lists.push( list.value );
			}
			wpdr_en_send( ext_note, 'wpdr_en_send_ext_notice_manual', { lists: lists }, document.getElementById( 'wpdr-en-ext-extra' ) );
		} );
	}

	// No list ticked: nothing to send to.
	for ( const item of document.getElementsByName( 'wpdr-en-ext-list' ) ) {
		item.addEventListener( 'click', function () {
			ext_note.disabled = ( 0 === document.querySelectorAll( "input[name='wpdr-en-ext-list']:checked" ).length );
		} );
	}

	// The "Add Extra text" boxes can only be ticked when there is extra text.
	const extra_text = document.getElementById( 'wpdr-en-extra' );
	if ( extra_text ) {
		extra_text.addEventListener( 'input', function ( event ) {
			const empty = ( 0 === event.target.value.length );
			const boxes = [
				[ notify, document.getElementById( 'wpdr-en-int-extra' ) ],
				[ ext_note, document.getElementById( 'wpdr-en-ext-extra' ) ]
			];
			for ( const [ button, box ] of boxes ) {
				// Only for a usable button, and a template that has %extra%.
				if ( ! button || button.disabled || ! box || box.classList.contains( 'wpdr_en_not_use' ) ) {
					continue;
				}
				box.disabled = empty;
				if ( empty ) {
					box.checked = false;
				}
			}
		} );
	}
} );
