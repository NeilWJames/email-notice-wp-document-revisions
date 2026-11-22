/**
 * Email log pages (src/js/wpdr-en-log.js).
 */
const { loadScript } = require( './helpers' );

test( 'the Extra Text button shows and hides the row\'s extra text', () => {
	loadScript( 'wpdr-en-log', '<textarea id="extra_4" style="display: none">Please review</textarea>' );
	const text = document.getElementById( 'extra_4' );

	wpdr_en_extra( 4 );
	expect( text.style.display ).toBe( 'block' );

	wpdr_en_extra( 4 );
	expect( text.style.display ).toBe( 'none' );
} );

test( 'the button says whether the text is shown (aria-expanded)', () => {
	loadScript(
		'wpdr-en-log',
		'<button type="button" aria-expanded="false" aria-controls="extra_4" onclick="wpdr_en_extra( 4, this )">Extra Text</button>' +
			'<textarea id="extra_4" readonly style="display: none">Please review</textarea>'
	);
	const button = document.querySelector( 'button' );

	button.click();
	expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
	expect( document.getElementById( 'extra_4' ).style.display ).toBe( 'block' );

	// Clicking in the text box (to select text) no longer hides it.
	document.getElementById( 'extra_4' ).click();
	expect( document.getElementById( 'extra_4' ).style.display ).toBe( 'block' );

	button.click();
	expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
} );

test( 'a row without extra text is ignored', () => {
	loadScript( 'wpdr-en-log', '<p>No extra text</p>' );

	expect( () => wpdr_en_extra( 5 ) ).not.toThrow();
} );
