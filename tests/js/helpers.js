/**
 * Helpers for the JavaScript tests: load a src/js script into the jsdom page and fake the
 * WordPress globals it uses (wpdr_en_obj from wp_localize_script(), jQuery.ajax()).
 */
const path = require( 'path' );

/**
 * Listeners the loaded script added to the document.
 *
 * @type {Array<Array>}
 */
const documentListeners = [];

/**
 * Strings and settings passed by wp_localize_script() (keys as in load_js_methods()).
 */
const strings = {
	wpdr_en_nonce: 'nonce123',
	ajaxurl: '/wp-admin/admin-ajax.php',
	sending_mails: 'Sending email notifications...',
	error_sending: 'Error sending emails.',
	resend: 'Re-send notification email(s)',
	email_out_of: 'email(s) out of',
	notif_sent_check: 'notification(s) sent. Check',
	sent_with: 'sent with',
	log_issues: 'log issues. Check',
	log: 'log',
	for_details: 'for details.',
	add_address: 'Add Address to List',
	edit_address: 'Edit existing Address',
};

/**
 * Set up the page and load a script, as WordPress would.
 *
 * @param {string} name Script name in src/js, without .js.
 * @param {string} html Body HTML.
 * @return {jest.Mock} The fake jQuery.ajax (its calls hold the request options).
 */
function loadScript( name, html ) {
	// A page loads each script once: drop the listeners a previous test's copy added to the document.
	for ( const [ type, listener ] of documentListeners.splice( 0 ) ) {
		document.removeEventListener( type, listener );
	}
	document.body.innerHTML = html;
	global.wpdr_en_obj      = { ...strings };
	const ajax              = jest.fn();
	global.jQuery           = { ajax };
	global.alert            = jest.fn();

	const add = document.addEventListener;
	document.addEventListener = function ( type, listener, options ) {
		documentListeners.push( [ type, listener ] );
		return add.call( this, type, listener, options );
	};
	try {
		jest.isolateModules( () => {
			require( path.join( __dirname, '../../src/js', name + '.js' ) );
		} );
	} finally {
		document.addEventListener = add;
	}
	document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
	return ajax;
}

/**
 * The options of the last jQuery.ajax() call.
 *
 * @param {jest.Mock} ajax The fake jQuery.ajax.
 * @return {Object} Options.
 */
function lastRequest( ajax ) {
	expect( ajax ).toHaveBeenCalled();
	return ajax.mock.calls[ ajax.mock.calls.length - 1 ][ 0 ];
}

/**
 * Call a function as an inline onclick handler would, with window.event set.
 *
 * @param {Function} fn Function to call.
 * @param {...*}     args Arguments.
 * @return {Event} The event (check defaultPrevented).
 */
function asOnclick( fn, ...args ) {
	const event = new MouseEvent( 'click', { bubbles: true, cancelable: true } );
	Object.defineProperty( window, 'event', { value: event, configurable: true } );
	try {
		fn( ...args );
	} finally {
		delete window.event;
	}
	return event;
}

module.exports = { loadScript, lastRequest, asOnclick, strings };
