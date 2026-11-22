/*
// Scripts for WPDR Custom Email Logs.
// @version: 4.0
*/

/**
 * Show or hide a log row's extra text.
 *
 * @param {number}            id     Log row ID.
 * @param {HTMLButtonElement} button The Extra Text button (its aria-expanded follows the text box).
 */
function wpdr_en_extra( id, button ) {
	const text = document.getElementById( 'extra_' + id );
	if ( ! text ) {
		return;
	}
	const show = ( 'none' === getComputedStyle( text ).display );
	text.style.display = ( show ? 'block' : 'none' );
	if ( button ) {
		button.setAttribute( 'aria-expanded', show ? 'true' : 'false' );
	}
}
