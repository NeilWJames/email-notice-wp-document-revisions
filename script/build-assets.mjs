/**
 * Minify the plugin's JS and CSS: src/js/*.js -> build/js/*.js, src/css/*.css -> build/css/*.css (same names).
 *
 * Usage (from the plugin folder):
 *   npm run build            rebuild only files whose source is newer than the built file
 *   npm run build -- --force rebuild everything
 *   npm run build -- --check list out-of-date files and exit 1 if there are any (no writing)
 *
 * The CSS is never transpiled; the JS is minified with terser (ES2020 output, WordPress supports
 * no browsers older than that).
 */
import { readdir, readFile, writeFile, mkdir, stat } from 'node:fs/promises';
import { join, basename, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { minify } from 'terser';
import CleanCSS from 'clean-css';

const root  = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const force = process.argv.includes( '--force' );
const check = process.argv.includes( '--check' );

const jobs = [
	{ src: 'src/js', out: 'build/js', ext: '.js', run: minifyJs },
	{ src: 'src/css', out: 'build/css', ext: '.css', run: minifyCss },
];

async function mtime( file ) {
	try {
		return ( await stat( file ) ).mtimeMs;
	} catch {
		return 0;
	}
}

async function minifyJs( code, file ) {
	const result = await minify( code, {
		ecma: 2020,
		compress: true,
		mangle: true,
		format: { comments: false },
		sourceMap: false,
	} );
	if ( ! result.code ) {
		throw new Error( `terser produced no output for ${ file }` );
	}
	return result.code + '\n';
}

async function minifyCss( code, file ) {
	const result = new CleanCSS( { level: 1 } ).minify( code );
	if ( result.errors.length ) {
		throw new Error( `${ file }: ${ result.errors.join( '; ' ) }` );
	}
	return result.styles + '\n';
}

let built    = 0;
let outdated = [];

for ( const job of jobs ) {
	const srcDir = join( root, job.src );
	const outDir = join( root, job.out );
	const files  = ( await readdir( srcDir ) ).filter( ( f ) => f.endsWith( job.ext ) ).sort();
	await mkdir( outDir, { recursive: true } );

	for ( const file of files ) {
		const src = join( srcDir, file );
		const out = join( outDir, file );
		if ( ! force && ( await mtime( out ) ) >= ( await mtime( src ) ) ) {
			continue;
		}
		if ( check ) {
			outdated.push( `${ job.src }/${ file }` );
			continue;
		}
		const code = await readFile( src, 'utf8' );
		await writeFile( out, await job.run( code, file ) );
		console.log( `built ${ job.out }/${ basename( out ) }` );
		built++;
	}
}

if ( check ) {
	if ( outdated.length ) {
		console.error( 'Out of date: ' + outdated.join( ', ' ) );
		process.exit( 1 );
	}
	console.log( 'build/ is up to date.' );
} else {
	console.log( built ? `${ built } file(s) built.` : 'Nothing to build (use --force to rebuild all).' );
}
