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
	const { bookId, heightMode, height, display } = attributes;

	const { books, selected, cover } = useSelect(
		( select ) => {
			const { getEntityRecords, getEntityRecord, getMedia } =
				select( coreStore );
			const records =
				getEntityRecords( 'postType', 'bindr_book', {
					per_page: 100,
					status: 'publish',
					orderby: 'title',
					order: 'asc',
				} ) || [];
			const current = bookId
				? getEntityRecord( 'postType', 'bindr_book', bookId )
				: null;
			return {
				books: records,
				selected: current,
				cover:
					current && current.featured_media
						? getMedia( current.featured_media )
						: null,
			};
		},
		[ bookId ]
	);

	const options = books.map( ( book ) => ( {
		value: String( book.id ),
		label: book.title.rendered || `#${ book.id }`,
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
				<PanelBody title={ __( 'Book', 'wp-bindr' ) }>
					<ComboboxControl
						label={ __( 'Choose a book', 'wp-bindr' ) }
						value={ bookId ? String( bookId ) : '' }
						options={ options }
						onChange={ ( value ) =>
							setAttributes( {
								bookId: value ? parseInt( value, 10 ) : 0,
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
								label: __( 'Book setting', 'wp-bindr' ),
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

			{ ! bookId && (
				<Placeholder
					icon="book"
					label={ __( 'Bindr Book', 'wp-bindr' ) }
					instructions={ __(
						'Choose a book in the block settings sidebar. Create books under Bindr in the admin menu.',
						'wp-bindr'
					) }
				>
					<ComboboxControl
						label={ __( 'Choose a book', 'wp-bindr' ) }
						value=""
						options={ options }
						onChange={ ( value ) =>
							setAttributes( {
								bookId: value ? parseInt( value, 10 ) : 0,
							} )
						}
					/>
				</Placeholder>
			) }

			{ !! bookId && (
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
							'Interactive book — preview it on the front end.',
							'wp-bindr'
						) }
					</span>
				</div>
			) }
		</div>
	);
}
