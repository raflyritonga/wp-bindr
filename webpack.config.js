const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

// Extra entries on top of the default block.json-driven build:
// the front-end viewer app and the two small admin apps.
module.exports = {
	...defaultConfig,
	entry: {
		...( typeof defaultConfig.entry === 'function'
			? defaultConfig.entry()
			: defaultConfig.entry ),
		'viewer/index': path.resolve( __dirname, 'src/viewer/index.js' ),
		'admin-edit/index': path.resolve( __dirname, 'src/admin-edit/index.js' ),
		'admin-dashboard/index': path.resolve(
			__dirname,
			'src/admin-dashboard/index.js'
		),
		'admin-settings/index': path.resolve(
			__dirname,
			'src/admin-settings/index.js'
		),
	},
};
