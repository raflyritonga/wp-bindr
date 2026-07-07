/**
 * Copy pinned vendor libraries into build/vendor/ so the plugin ships them
 * locally (wp.org rule: no CDN loading). Versions are pinned in package.json
 * and documented in LIBRARIES.md.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '..' );
const out = path.join( root, 'build', 'vendor' );
fs.mkdirSync( out, { recursive: true } );

const files = [
	// PDF.js legacy build: UMD + broad browser support (last UMD major).
	[ 'node_modules/pdfjs-dist/legacy/build/pdf.min.js', 'pdf.min.js' ],
	[
		'node_modules/pdfjs-dist/legacy/build/pdf.worker.min.js',
		'pdf.worker.min.js',
	],
	// StPageFlip UMD browser build (window.St.PageFlip).
	[
		'node_modules/page-flip/dist/js/page-flip.browser.js',
		'page-flip.browser.js',
	],
];

for ( const [ src, dest ] of files ) {
	const from = path.join( root, src );
	if ( ! fs.existsSync( from ) ) {
		console.error(
			`Missing vendor file: ${ src } — run npm install first.`
		);
		process.exit( 1 );
	}
	fs.copyFileSync( from, path.join( out, dest ) );
	console.log( `vendor: ${ dest }` );
}
