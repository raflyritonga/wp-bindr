/**
 * Compile languages/*.po into:
 *  - .mo files (binary gettext, read by WordPress for PHP strings)
 *  - JED .json files for the block editor script (wp.i18n), named
 *    {domain}-{locale}-{md5 of script path}.json per WP convention.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const crypto = require( 'crypto' );

const root = path.resolve( __dirname, '..' );
const langDir = path.join( root, 'languages' );
const DOMAIN = 'wp-bindr';
// Scripts registered with wp_set_script_translations (block editor only —
// front-end viewer strings are localized server-side via the config JSON).
const JS_HANDLE_PATHS = [ 'build/block/index.js' ];

function parsePo( src ) {
	const entries = [];
	let cur = null;
	let mode = null;

	const flush = () => {
		if ( cur && cur.msgid !== undefined ) {
			entries.push( cur );
		}
		cur = null;
	};

	for ( const rawLine of src.split( '\n' ) ) {
		const line = rawLine.trim();
		if ( ! line || line.startsWith( '#' ) ) {
			continue;
		}
		let m;
		if ( ( m = line.match( /^msgctxt\s+"(.*)"$/ ) ) ) {
			flush();
			cur = { msgctxt: unescape_( m[ 1 ] ) };
			mode = 'msgctxt';
		} else if ( ( m = line.match( /^msgid\s+"(.*)"$/ ) ) ) {
			if ( ! cur || cur.msgid !== undefined ) {
				flush();
				cur = cur || {};
			}
			cur =
				cur && cur.msgctxt !== undefined && cur.msgid === undefined
					? cur
					: cur || {};
			if ( cur.msgid !== undefined ) {
				flush();
				cur = {};
			}
			cur.msgid = unescape_( m[ 1 ] );
			mode = 'msgid';
		} else if ( ( m = line.match( /^msgid_plural\s+"(.*)"$/ ) ) ) {
			cur.msgid_plural = unescape_( m[ 1 ] );
			mode = 'msgid_plural';
		} else if ( ( m = line.match( /^msgstr(\[(\d+)\])?\s+"(.*)"$/ ) ) ) {
			cur.msgstr = cur.msgstr || [];
			const idx = m[ 2 ] ? parseInt( m[ 2 ], 10 ) : 0;
			cur.msgstr[ idx ] = unescape_( m[ 3 ] );
			mode = `msgstr:${ idx }`;
		} else if ( ( m = line.match( /^"(.*)"$/ ) ) ) {
			const text = unescape_( m[ 1 ] );
			if ( 'msgid' === mode ) {
				cur.msgid += text;
			} else if ( 'msgid_plural' === mode ) {
				cur.msgid_plural += text;
			} else if ( 'msgctxt' === mode ) {
				cur.msgctxt += text;
			} else if ( mode && mode.startsWith( 'msgstr' ) ) {
				const idx = parseInt( mode.split( ':' )[ 1 ], 10 );
				cur.msgstr[ idx ] += text;
			}
		}
	}
	flush();
	return entries;
}

function unescape_( s ) {
	return s
		.replace( /\\n/g, '\n' )
		.replace( /\\t/g, '\t' )
		.replace( /\\"/g, '"' )
		.replace( /\\\\/g, '\\' );
}

function buildMo( entries ) {
	const items = entries
		.map( ( e ) => {
			let id = e.msgid;
			if ( e.msgctxt !== undefined ) {
				id = `${ e.msgctxt }\u0004${ id }`;
			}
			if ( e.msgid_plural !== undefined ) {
				id = `${ id }\u0000${ e.msgid_plural }`;
			}
			const str = ( e.msgstr || [ '' ] ).join( '\u0000' );
			return {
				id: Buffer.from( id, 'utf8' ),
				str: Buffer.from( str, 'utf8' ),
			};
		} )
		.sort( ( a, b ) => Buffer.compare( a.id, b.id ) );

	const n = items.length;
	const header = 28;
	const tableSize = n * 8;
	let offset = header + 2 * tableSize;

	const idOffsets = [];
	for ( const item of items ) {
		idOffsets.push( [ item.id.length, offset ] );
		offset += item.id.length + 1;
	}
	const strOffsets = [];
	for ( const item of items ) {
		strOffsets.push( [ item.str.length, offset ] );
		offset += item.str.length + 1;
	}

	const buf = Buffer.alloc( offset );
	buf.writeUInt32LE( 0x950412de, 0 ); // magic
	buf.writeUInt32LE( 0, 4 ); // revision
	buf.writeUInt32LE( n, 8 );
	buf.writeUInt32LE( header, 12 ); // originals table
	buf.writeUInt32LE( header + tableSize, 16 ); // translations table
	buf.writeUInt32LE( 0, 20 ); // hash table size
	buf.writeUInt32LE( header + 2 * tableSize, 24 ); // hash table offset

	items.forEach( ( item, i ) => {
		buf.writeUInt32LE( idOffsets[ i ][ 0 ], header + i * 8 );
		buf.writeUInt32LE( idOffsets[ i ][ 1 ], header + i * 8 + 4 );
		buf.writeUInt32LE( strOffsets[ i ][ 0 ], header + tableSize + i * 8 );
		buf.writeUInt32LE(
			strOffsets[ i ][ 1 ],
			header + tableSize + i * 8 + 4
		);
		item.id.copy( buf, idOffsets[ i ][ 1 ] );
		item.str.copy( buf, strOffsets[ i ][ 1 ] );
	} );

	return buf;
}

function buildJed( entries, locale ) {
	const header = entries.find( ( e ) => '' === e.msgid );
	const pluralForms =
		( header &&
			( header.msgstr[ 0 ].match( /Plural-Forms:\s*(.+)/ ) ||
				[] )[ 1 ] ) ||
		'nplurals=2; plural=(n != 1);';

	const messages = {
		'': {
			domain: 'messages',
			'plural-forms': pluralForms.trim(),
			lang: locale,
		},
	};
	for ( const e of entries ) {
		if ( '' === e.msgid || ! e.msgstr || ! e.msgstr[ 0 ] ) {
			continue;
		}
		const jedKey =
			e.msgctxt !== undefined
				? `${ e.msgctxt }\u0004${ e.msgid }`
				: e.msgid;
		messages[ jedKey ] = e.msgstr;
	}
	return {
		'translation-revision-date': new Date().toISOString(),
		generator: 'wp-bindr/tools/compile-lang.js',
		domain: 'messages',
		locale_data: { messages },
	};
}

for ( const file of fs.readdirSync( langDir ) ) {
	if ( ! file.endsWith( '.po' ) ) {
		continue;
	}
	const locale = file.replace( `${ DOMAIN }-`, '' ).replace( '.po', '' );
	const entries = parsePo(
		fs.readFileSync( path.join( langDir, file ), 'utf8' )
	);

	const mo = buildMo( entries );
	fs.writeFileSync( path.join( langDir, file.replace( '.po', '.mo' ) ), mo );
	console.log(
		`mo: ${ file.replace( '.po', '.mo' ) } (${ entries.length } entries)`
	);

	const jed = JSON.stringify( buildJed( entries, locale ) );
	for ( const scriptPath of JS_HANDLE_PATHS ) {
		const md5 = crypto
			.createHash( 'md5' )
			.update( scriptPath )
			.digest( 'hex' );
		const jsonName = `${ DOMAIN }-${ locale }-${ md5 }.json`;
		fs.writeFileSync( path.join( langDir, jsonName ), jed );
		console.log( `jed: ${ jsonName }` );
	}
}
