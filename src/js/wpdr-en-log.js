/*
// Scripts for WPDR Custom Email Logs.
// @version: 4.0
*/

/**
 * Show or hide a log row's extra text.
 *
 * @param {number} id Log row ID.
 */
function wpdr_en_extra( id ) {
	const text = document.getElementById( 'extra_' + id );
	if ( ! text ) {
		return;
	}
	text.style.display = ( 'none' === getComputedStyle( text ).display ? 'block' : 'none' );
}
