/**
 * Extract translatable strings from PHP and JS sources into
 * languages/wp-bindr.pot. Covers the gettext calls this plugin uses:
 * __(), _e(), esc_html__(), esc_html_e(), esc_attr__(), esc_attr_e(),
 * _n(), _x() — with translator comments.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '..' );
const DOMAIN = 'wp-bindr';

const entries = new Map(); // key -> { msgid, plural, context, refs, comments }

function key( msgid, context ) {
	return `${ context || '' }${ msgid }`;
}

function addEntry( { msgid, plural, context, ref, comment } ) {
	const k = key( msgid, context );
	if ( ! entries.has( k ) ) {
		entries.set( k, {
			msgid,
			plural,
			context,
			refs: [],
			comments: new Set(),
		} );
	}
	const entry = entries.get( k );
	entry.refs.push( ref );
	if ( comment ) {
		entry.comments.add( comment );
	}
	if ( plural ) {
		entry.plural = plural;
	}
}

const STR = `'((?:[^'\\\\]|\\\\.)*)'|"((?:[^"\\\\]|\\\\.)*)"`;

function unescapePhp( s ) {
	return s.replace( /\\(['"\\])/g, '$1' );
}

function scanFile( file ) {
	const src = fs.readFileSync( file, 'utf8' );
	const rel = path.relative( root, file );
	const lines = src.split( '\n' );

	const fnSingle = new RegExp(
		`(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\\(\\s*(?:${ STR })\\s*,\\s*(?:${ STR })\\s*\\)`,
		'g'
	);
	const fnPlural = new RegExp(
		`_n\\(\\s*(?:${ STR })\\s*,\\s*(?:${ STR })\\s*,[^,]+,\\s*(?:${ STR })\\s*\\)`,
		'g'
	);
	const fnContext = new RegExp(
		`_x\\(\\s*(?:${ STR })\\s*,\\s*(?:${ STR })\\s*,\\s*(?:${ STR })\\s*\\)`,
		'g'
	);

	lines.forEach( ( line, i ) => {
		const ref = `${ rel }:${ i + 1 }`;
		// Translator comment on the previous line(s).
		let comment = null;
		for ( let back = 1; back <= 2; back++ ) {
			const prev = lines[ i - back ] || '';
			const match = prev.match(
				/(?:\/\*|\/\/)\s*(translators:.*?)(?:\*\/)?\s*$/i
			);
			if ( match ) {
				comment = match[ 1 ].trim();
				break;
			}
		}

		for ( const m of line.matchAll( fnSingle ) ) {
			const msgid = unescapePhp( m[ 1 ] !== undefined ? m[ 1 ] : m[ 2 ] );
			const domain = unescapePhp(
				m[ 3 ] !== undefined ? m[ 3 ] : m[ 4 ]
			);
			if ( domain === DOMAIN ) {
				addEntry( { msgid, ref, comment } );
			}
		}
		for ( const m of line.matchAll( fnPlural ) ) {
			const msgid = unescapePhp( m[ 1 ] !== undefined ? m[ 1 ] : m[ 2 ] );
			const plural = unescapePhp(
				m[ 3 ] !== undefined ? m[ 3 ] : m[ 4 ]
			);
			const domain = unescapePhp(
				m[ 5 ] !== undefined ? m[ 5 ] : m[ 6 ]
			);
			if ( domain === DOMAIN ) {
				addEntry( { msgid, plural, ref, comment } );
			}
		}
		for ( const m of line.matchAll( fnContext ) ) {
			const msgid = unescapePhp( m[ 1 ] !== undefined ? m[ 1 ] : m[ 2 ] );
			const context = unescapePhp(
				m[ 3 ] !== undefined ? m[ 3 ] : m[ 4 ]
			);
			const domain = unescapePhp(
				m[ 5 ] !== undefined ? m[ 5 ] : m[ 6 ]
			);
			if ( domain === DOMAIN ) {
				addEntry( { msgid, context, ref, comment } );
			}
		}
	} );
}

function walk( dir, exts ) {
	for ( const item of fs.readdirSync( dir, { withFileTypes: true } ) ) {
		if ( item.name === 'node_modules' || item.name === 'build' ) {
			continue;
		}
		const full = path.join( dir, item.name );
		if ( item.isDirectory() ) {
			walk( full, exts );
		} else if ( exts.includes( path.extname( item.name ) ) ) {
			scanFile( full );
		}
	}
}

// Multi-line calls: also scan whole-file with newlines collapsed for PHP
// (WPCS formats some calls across lines).
function scanMultiline( file ) {
	const src = fs.readFileSync( file, 'utf8' ).replace( /\s*\n\s*/g, ' ' );
	const rel = path.relative( root, file );
	const fnSingle = new RegExp(
		`(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\\(\\s*(?:${ STR })\\s*,\\s*(?:${ STR })\\s*\\)`,
		'g'
	);
	for ( const m of src.matchAll( fnSingle ) ) {
		const msgid = unescapePhp( m[ 1 ] !== undefined ? m[ 1 ] : m[ 2 ] );
		const domain = unescapePhp( m[ 3 ] !== undefined ? m[ 3 ] : m[ 4 ] );
		if ( domain === DOMAIN && ! entries.has( key( msgid ) ) ) {
			addEntry( { msgid, ref: rel } );
		}
	}
	const fnPlural = new RegExp(
		`_n\\(\\s*(?:${ STR })\\s*,\\s*(?:${ STR })\\s*,[^,]+,\\s*(?:${ STR })\\s*\\)`,
		'g'
	);
	for ( const m of src.matchAll( fnPlural ) ) {
		const msgid = unescapePhp( m[ 1 ] !== undefined ? m[ 1 ] : m[ 2 ] );
		const plural = unescapePhp( m[ 3 ] !== undefined ? m[ 3 ] : m[ 4 ] );
		const domain = unescapePhp( m[ 5 ] !== undefined ? m[ 5 ] : m[ 6 ] );
		if ( domain === DOMAIN && ! entries.has( key( msgid ) ) ) {
			addEntry( { msgid, plural, ref: rel } );
		}
	}
}

[ 'includes', 'templates', 'src' ].forEach( ( dir ) =>
	walk( path.join( root, dir ), [ '.php', '.js' ] )
);
scanFile( path.join( root, 'wp-bindr.php' ) );
function walkMultiline( dir, exts ) {
	for ( const item of fs.readdirSync( dir, { withFileTypes: true } ) ) {
		const full = path.join( dir, item.name );
		if ( item.isDirectory() ) {
			walkMultiline( full, exts );
		} else if ( exts.includes( path.extname( item.name ) ) ) {
			scanMultiline( full );
		}
	}
}
[ 'includes', 'templates' ].forEach( ( dir ) =>
	walkMultiline( path.join( root, dir ), [ '.php' ] )
);
walkMultiline( path.join( root, 'src' ), [ '.js' ] );

// block.json strings.
const blockJson = JSON.parse(
	fs.readFileSync( path.join( root, 'src/block/block.json' ), 'utf8' )
);
[
	[ blockJson.title, 'block title' ],
	[ blockJson.description, 'block description' ],
].forEach( ( [ msgid, what ] ) => {
	if ( msgid ) {
		addEntry( { msgid, ref: `src/block/block.json (${ what })` } );
	}
} );
( blockJson.keywords || [] ).forEach( ( msgid ) =>
	addEntry( { msgid, ref: 'src/block/block.json (keyword)' } )
);

function poEscape( s ) {
	return s
		.replace( /\\/g, '\\\\' )
		.replace( /"/g, '\\"' )
		.replace( /\n/g, '\\n' );
}

const now = new Date().toISOString().replace( 'T', ' ' ).slice( 0, 16 );
let pot = `# Copyright (C) 2026 Bindr Contributors
# This file is distributed under the GPL v2 or later.
msgid ""
msgstr ""
"Project-Id-Version: Bindr 1.0.0\\n"
"Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/wp-bindr\\n"
"POT-Creation-Date: ${ now }+00:00\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"
"X-Domain: wp-bindr\\n"

`;

const sorted = [ ...entries.values() ].sort( ( a, b ) =>
	a.msgid.localeCompare( b.msgid )
);
for ( const entry of sorted ) {
	for ( const comment of entry.comments ) {
		pot += `#. ${ comment }\n`;
	}
	for ( const ref of entry.refs.slice( 0, 3 ) ) {
		pot += `#: ${ ref }\n`;
	}
	if ( entry.context ) {
		pot += `msgctxt "${ poEscape( entry.context ) }"\n`;
	}
	pot += `msgid "${ poEscape( entry.msgid ) }"\n`;
	if ( entry.plural ) {
		pot += `msgid_plural "${ poEscape( entry.plural ) }"\n`;
		pot += `msgstr[0] ""\nmsgstr[1] ""\n\n`;
	} else {
		pot += `msgstr ""\n\n`;
	}
}

fs.mkdirSync( path.join( root, 'languages' ), { recursive: true } );
fs.writeFileSync( path.join( root, 'languages', 'wp-bindr.pot' ), pot );
console.log( `pot: ${ sorted.length } strings` );
