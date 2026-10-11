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

test( 'a row without extra text is ignored', () => {
	loadScript( 'wpdr-en-log', '<p>No extra text</p>' );

	expect( () => wpdr_en_extra( 5 ) ).not.toThrow();
} );
