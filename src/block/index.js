/**
 * Flipbook block registration. Rendering happens server-side through the
 * same callback as the shortcode, so editor and front end never diverge.
 */
import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import metadata from './block.json';
import './editor.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	// Server-rendered: no save output.
	save: () => null,
} );
