/**
 * Block editor UI: searchable flipbook picker + display options, with a
 * static cover-and-frame placeholder in the canvas. The full viewer never
 * runs inside the editor.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	ComboboxControl,
	PanelBody,
	Placeholder,
	RadioControl,
	RangeControl,
	SelectControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

export default function Edit( { attributes, setAttributes } ) {
	const { flipbookId, heightMode, height, display } = attributes;

	const { flipbooks, selected, cover } = useSelect(
		( select ) => {
			const { getEntityRecords, getEntityRecord, getMedia } =
				select( coreStore );
			const records =
				getEntityRecords( 'postType', 'bindr_flipbook', {
					per_page: 100,
					status: 'publish',
					orderby: 'title',
					order: 'asc',
				} ) || [];
			const current = flipbookId
				? getEntityRecord( 'postType', 'bindr_flipbook', flipbookId )
				: null;
			return {
				flipbooks: records,
				selected: current,
				cover:
					current && current.featured_media
						? getMedia( current.featured_media )
						: null,
			};
		},
		[ flipbookId ]
	);

	const options = flipbooks.map( ( flipbook ) => ( {
		value: String( flipbook.id ),
		label: flipbook.title.rendered || `#${ flipbook.id }`,
	} ) );

	const coverUrl =
		cover &&
		( ( cover.media_details &&
			cover.media_details.sizes &&
			cover.media_details.sizes.medium &&
			cover.media_details.sizes.medium.source_url ) ||
			cover.source_url );

	const blockProps = useBlockProps( { className: 'bindr-block-preview' } );

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Flipbook', 'wp-bindr' ) }>
					<ComboboxControl
						label={ __( 'Choose a flipbook', 'wp-bindr' ) }
						value={ flipbookId ? String( flipbookId ) : '' }
						options={ options }
						onChange={ ( value ) =>
							setAttributes( {
								flipbookId: value ? parseInt( value, 10 ) : 0,
							} )
						}
					/>
					<RadioControl
						label={ __( 'Height', 'wp-bindr' ) }
						selected={ heightMode }
						options={ [
							{
								label: __(
									'Responsive (from page ratio)',
									'wp-bindr'
								),
								value: 'ratio',
							},
							{
								label: __( 'Fixed height', 'wp-bindr' ),
								value: 'fixed',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { heightMode: value } )
						}
					/>
					{ 'fixed' === heightMode && (
						<RangeControl
							label={ __( 'Height (pixels)', 'wp-bindr' ) }
							min={ 300 }
							max={ 1200 }
							value={ height }
							onChange={ ( value ) =>
								setAttributes( { height: value } )
							}
						/>
					) }
					<SelectControl
						label={ __( 'Page display', 'wp-bindr' ) }
						value={ display }
						options={ [
							{
								label: __( 'Flipbook setting', 'wp-bindr' ),
								value: '',
							},
							{
								label: __( 'Double page', 'wp-bindr' ),
								value: 'double',
							},
							{
								label: __( 'Single page', 'wp-bindr' ),
								value: 'single',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { display: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			{ ! flipbookId && (
				<Placeholder
					icon="book"
					label={ __( 'Bindr Flipbook', 'wp-bindr' ) }
					instructions={ __(
						'Choose a flipbook in the block settings sidebar. Create flipbooks under Bindr in the admin menu.',
						'wp-bindr'
					) }
				>
					<ComboboxControl
						label={ __( 'Choose a flipbook', 'wp-bindr' ) }
						value=""
						options={ options }
						onChange={ ( value ) =>
							setAttributes( {
								flipbookId: value ? parseInt( value, 10 ) : 0,
							} )
						}
					/>
				</Placeholder>
			) }

			{ !! flipbookId && (
				<div className="bindr-block-preview__frame">
					{ coverUrl ? (
						<img
							className="bindr-block-preview__cover"
							src={ coverUrl }
							alt=""
						/>
					) : (
						<span
							className="bindr-block-preview__icon dashicons dashicons-book"
							aria-hidden="true"
						/>
					) }
					<span className="bindr-block-preview__title">
						{ selected
							? selected.title.rendered
							: __( 'Loading…', 'wp-bindr' ) }
					</span>
					<span className="bindr-block-preview__hint">
						{ __(
							'Interactive flipbook — preview it on the front end.',
							'wp-bindr'
						) }
					</span>
				</div>
			) }
		</div>
	);
}
