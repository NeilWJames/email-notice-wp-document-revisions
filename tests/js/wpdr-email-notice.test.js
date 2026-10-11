/**
 * Document Email Notifications metabox (src/js/wpdr-email-notice.js).
 */
const { loadScript, lastRequest } = require( './helpers' );

/**
 * The metabox as add_metabox() writes it (both sections, two matched lists).
 *
 * @param {Object} opts Options: extra (extra text box), notUse (template without %extra%).
 * @return {string} HTML.
 */
function metabox( opts = {} ) {
	const notUse = opts.notUse ? ' class="wpdr_en_not_use"' : '';
	return `
		<input type="hidden" id="post_ID" value="42">
		${ false === opts.extra ? '' : '<textarea id="wpdr-en-extra"></textarea>' }
		<input type="button" id="wpdr-en-notify" value="Send notification email(s)">
		<input type="checkbox" id="wpdr-en-int-extra" value="0" disabled${ notUse }>
		<span id="dProgress" style="display:none"></span>
		<span id="wpdr-en-message" style="display:none"></span>
		<input type="button" id="wpdr-en-ext-note" value="Send external list email(s)">
		<input type="checkbox" id="wpdr-en-ext-extra" value="0" disabled>
		<input name="wpdr-en-ext-list" type="checkbox" value="7" checked>
		<input name="wpdr-en-ext-list" type="checkbox" value="9" checked>`;
}

/**
 * Type into the extra text box.
 *
 * @param {string} text Text.
 */
function typeExtra( text ) {
	const box = document.getElementById( 'wpdr-en-extra' );
	box.value = text;
	box.dispatchEvent( new Event( 'input' ) );
}

describe( 'sending', () => {
	test( 'internal send posts the document, nonce and no extra text', () => {
		const ajax = loadScript( 'wpdr-email-notice', metabox() );

		document.getElementById( 'wpdr-en-notify' ).click();

		const req = lastRequest( ajax );
		expect( req.url ).toBe( '/wp-admin/admin-ajax.php' );
		expect( req.data ).toEqual( {
			action: 'wpdr_en_send_notification_manual',
			post_id: '42',
			extra: '',
			wpdr_en_nonce: 'nonce123',
		} );
	} );

	test( 'external send posts the ticked lists', () => {
		const ajax = loadScript( 'wpdr-email-notice', metabox() );
		document.querySelector( "input[value='9']" ).checked = false;

		document.getElementById( 'wpdr-en-ext-note' ).click();

		const req = lastRequest( ajax );
		expect( req.data.action ).toBe( 'wpdr_en_send_ext_notice_manual' );
		expect( req.data.lists ).toEqual( [ '7' ] );
	} );

	test( 'extra text is sent only when its box is ticked', () => {
		const ajax = loadScript( 'wpdr-email-notice', metabox() );
		typeExtra( 'Please read' );

		document.getElementById( 'wpdr-en-notify' ).click();
		expect( lastRequest( ajax ).data.extra ).toBe( '' );
		lastRequest( ajax ).complete();

		document.getElementById( 'wpdr-en-int-extra' ).checked = true;
		document.getElementById( 'wpdr-en-notify' ).click();
		expect( lastRequest( ajax ).data.extra ).toBe( '<br/>Please read' );

		document.getElementById( 'wpdr-en-ext-extra' ).checked = true;
		document.getElementById( 'wpdr-en-ext-note' ).click();
		expect( lastRequest( ajax ).data.extra ).toBe( '<br/>Please read' );
	} );

	test( 'a second click while sending sends nothing more', () => {
		const ajax = loadScript( 'wpdr-email-notice', metabox() );

		document.getElementById( 'wpdr-en-notify' ).click();
		document.getElementById( 'wpdr-en-notify' ).click();

		expect( ajax ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'the button stays disabled and the progress shown until the request finishes', () => {
		const ajax   = loadScript( 'wpdr-email-notice', metabox() );
		const button = document.getElementById( 'wpdr-en-notify' );

		button.click();

		expect( button.disabled ).toBe( true );
		expect( document.getElementById( 'dProgress' ).style.display ).toBe( 'inline-block' );
		expect( document.getElementById( 'wpdr-en-message' ).textContent ).toBe( 'Sending email notifications...' );

		lastRequest( ajax ).complete();
		expect( button.disabled ).toBe( false );
		expect( document.getElementById( 'dProgress' ).style.display ).toBe( 'none' );
	} );
} );

describe( 'results', () => {
	/**
	 * Send internally and answer with a response.
	 *
	 * @param {Object} response AJAX response.
	 * @return {string} Message HTML.
	 */
	function sendWith( response ) {
		const ajax = loadScript( 'wpdr-email-notice', metabox() );
		document.getElementById( 'wpdr-en-notify' ).click();
		lastRequest( ajax ).success( response );
		return document.getElementById( 'wpdr-en-message' ).innerHTML;
	}

	test( 'success shows the count and log link; the button re-sends', () => {
		const html = sendWith( { error: false, sent_count: 3, logged_count: 3, sending_error_count: 0, log_page_url: '/log?a=1&b=2' } );

		expect( html ).toBe( '<br>3 notification(s) sent. Check <a href="/log?a=1&amp;b=2">log</a> for details.' );
		expect( document.getElementById( 'wpdr-en-notify' ).value ).toBe( 'Re-send notification email(s)' );
		expect( global.alert ).not.toHaveBeenCalled();
	} );

	test( 'partial success says how many were sent and not logged', () => {
		const html = sendWith( { error: true, sent_count: 2, logged_count: 1, sending_error_count: 1, log_page_url: '/log' } );

		// The "out of" text was missing (the script read emails_out_of; PHP sends email_out_of).
		expect( html ).toBe( '2 email(s) out of 3 sent with 2 log issues. Check <a href="/log">log</a> for details.' );
		expect( html ).not.toContain( 'undefined' );
		expect( document.getElementById( 'wpdr-en-notify' ).value ).toBe( 'Re-send notification email(s)' );
	} );

	test( 'nothing sent shows the error and an alert with its code', () => {
		const html = sendWith( { error: true, error_msg: 'No permission', error_code: 'F-08', sent_count: 0, logged_count: 0 } );

		expect( html ).toBe( 'Error sending emails.' );
		expect( global.alert ).toHaveBeenCalledWith( 'No permission\n\n[Error: F-08]' );
		expect( document.getElementById( 'wpdr-en-notify' ).value ).toBe( 'Send notification email(s)' );
	} );

	test( 'response values cannot add markup', () => {
		const html = sendWith( { error: false, sent_count: '<img src=x onerror=alert(1)>', log_page_url: '"><script>x</script>' } );

		const div = document.createElement( 'div' );
		div.innerHTML = html;
		expect( div.querySelector( 'img, script' ) ).toBeNull();
		expect( div.querySelectorAll( 'a' ) ).toHaveLength( 1 );
	} );

	test( 'a failed request shows the error', () => {
		const ajax = loadScript( 'wpdr-email-notice', metabox() );
		document.getElementById( 'wpdr-en-ext-note' ).click();

		lastRequest( ajax ).error();

		expect( document.getElementById( 'wpdr-en-message' ).textContent ).toBe( 'Error sending emails.' );
	} );
} );

describe( 'controls', () => {
	test( 'unticking every list disables the external button', () => {
		loadScript( 'wpdr-email-notice', metabox() );
		const lists  = document.getElementsByName( 'wpdr-en-ext-list' );
		const button = document.getElementById( 'wpdr-en-ext-note' );

		lists[ 0 ].click();
		expect( button.disabled ).toBe( false );
		lists[ 1 ].click();
		expect( button.disabled ).toBe( true );
		lists[ 1 ].click();
		expect( button.disabled ).toBe( false );
	} );

	test( 'extra text enables the "Add Extra text" boxes; emptying it clears them', () => {
		loadScript( 'wpdr-email-notice', metabox() );
		const intBox = document.getElementById( 'wpdr-en-int-extra' );
		const extBox = document.getElementById( 'wpdr-en-ext-extra' );

		typeExtra( 'x' );
		expect( intBox.disabled ).toBe( false );
		expect( extBox.disabled ).toBe( false );

		intBox.checked = true;
		typeExtra( '' );
		expect( intBox.disabled ).toBe( true );
		expect( intBox.checked ).toBe( false );
	} );

	test( 'a template without %extra% or a disabled button keeps its box disabled', () => {
		loadScript( 'wpdr-email-notice', metabox( { notUse: true } ) );
		document.getElementById( 'wpdr-en-ext-note' ).disabled = true;

		typeExtra( 'x' );

		expect( document.getElementById( 'wpdr-en-int-extra' ).disabled ).toBe( true );
		expect( document.getElementById( 'wpdr-en-ext-extra' ).disabled ).toBe( true );
	} );

	test( 'works with only the internal section, and with neither', () => {
		const ajax = loadScript( 'wpdr-email-notice', '<input type="hidden" id="post_ID" value="5"><input type="button" id="wpdr-en-notify"><span id="dProgress"></span><span id="wpdr-en-message"></span>' );
		document.getElementById( 'wpdr-en-notify' ).click();
		expect( lastRequest( ajax ).data.extra ).toBe( '' );

		expect( () => loadScript( 'wpdr-email-notice', '<p>Other screen</p>' ) ).not.toThrow();
	} );
} );
