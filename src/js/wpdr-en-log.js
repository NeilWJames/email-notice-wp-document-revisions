/*
// Scripts for WPDR Custom Email Logs.
// @version: 3.1
*/
function wpdr_en_extra( id ) {
	var attr = 'extra_' + id;
	var text = document.getElementById(attr);
	var hide = getComputedStyle(text).display;
	if ( hide == 'none' ) {
		text.style.display = 'block';
	} else {
		text.style.display = 'none';
	}
}

 		
