/**
 * Build the WordPress.org Live Preview blueprint (assets/blueprints/blueprint.json).
 *
 * Run with `npm run assets-blueprint` when the Live Preview should change, then copy the file to
 * the plugin's WordPress.org SVN assets/blueprints folder.
 *
 * The Live Preview cannot load other files from the plugin's WordPress.org assets folder, so the
 * demo code is put inside the blueprint. The blueprint is made from:
 *  - playground/blueprint.json (the GitHub demo), with the Email Notice install step replaced by
 *    one that installs the released plugin from WordPress.org (WP Document Revisions already is);
 *  - playground/demo-mu-plugin.php, written to wp-content/mu-plugins by a step of its own;
 *  - playground/demo-content.php, whose code replaces the step that loads it.
 *
 * The demo code then runs against the released plugin, so only build this blueprint once the
 * plugin code the demo needs (4.0.0) has been released.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const ROOT = path.join( __dirname, '..' );
const SOURCE = path.join( __dirname, 'blueprint.json' );
const DEMO = path.join( __dirname, 'demo-content.php' );
const MU = path.join( __dirname, 'demo-mu-plugin.php' );
const TARGET = path.join( ROOT, 'assets', 'blueprints', 'blueprint.json' );
const SLUG = 'email-notice-wp-document-revisions';

// WordPress.org refuses blueprints larger than this.
const MAX_BYTES = 100 * 1024;

const read = ( file ) => fs.readFileSync( file, 'utf8' ).replace( /\r\n/g, '\n' );
const blueprint = JSON.parse( read( SOURCE ) );

// The released plugin, from WordPress.org.
const install = blueprint.steps.findIndex(
	( step ) =>
		'installPlugin' === step.step &&
		'git:directory' === step.pluginData.resource
);
if ( -1 === install ) {
	throw new Error(
		'playground/blueprint.json has no installPlugin step from GitHub.'
	);
}
blueprint.steps[ install ] = {
	step: 'installPlugin',
	pluginData: {
		resource: 'wordpress.org/plugins',
		slug: SLUG,
	},
	options: {
		activate: true,
	},
};

// The demo code itself, in place of loading playground/demo-content.php, after the helper mu-plugin.
const load = blueprint.steps.findIndex(
	( step ) =>
		'runPHP' === step.step && step.code.includes( 'demo-content.php' )
);
if ( -1 === load ) {
	throw new Error(
		'playground/blueprint.json has no runPHP step that loads demo-content.php.'
	);
}
const demo = read( DEMO ).replace( /^<\?php\s*/, '' );
blueprint.steps.splice(
	load,
	1,
	{
		step: 'writeFile',
		path: '/wordpress/wp-content/mu-plugins/wpdr-en-demo.php',
		data: read( MU ),
	},
	{
		step: 'runPHP',
		code:
			"<?php\ndefine( 'WP_ADMIN', true );\nrequire '/wordpress/wp-load.php';\n\n" +
			demo.trimEnd() +
			'\n\nwpdr_en_demo_load();\n',
	}
);

blueprint.meta = {
	...blueprint.meta,
	description:
		'The released plugin from WordPress.org, with WP Document Revisions, users of each role, Documents in several departments, Document External Lists and notifications already sent. Built from the playground folder by npm run assets-blueprint.',
};

const output = JSON.stringify( blueprint, null, '\t' ) + '\n';
const bytes = Buffer.byteLength( output, 'utf8' );
if ( bytes > MAX_BYTES ) {
	throw new Error(
		`The blueprint is ${ bytes } bytes; WordPress.org accepts at most ${ MAX_BYTES }.`
	);
}
fs.mkdirSync( path.dirname( TARGET ), { recursive: true } );
fs.writeFileSync( TARGET, output );
console.log( `Wrote ${ path.relative( ROOT, TARGET ) } (${ bytes } bytes).` );
