<?php
/**
 * Flipbook custom post type, meta, and edit screen.
 *
 * @package Bindr
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the bindr_book post type and its admin edit experience.
 */
class Bindr_CPT {

	const POST_TYPE = 'bindr_book';

	/**
	 * Hook registration.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_block_editor' ), 10, 2 );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'list_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_list_column' ), 10, 2 );
	}

	/**
	 * Register the flipbook post type.
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'               => __( 'Books', 'wp-bindr' ),
					'singular_name'      => __( 'Book', 'wp-bindr' ),
					'add_new'            => __( 'Add New', 'wp-bindr' ),
					'add_new_item'       => __( 'Add New Book', 'wp-bindr' ),
					'edit_item'          => __( 'Edit Book', 'wp-bindr' ),
					'new_item'           => __( 'New Book', 'wp-bindr' ),
					'view_item'          => __( 'View Book', 'wp-bindr' ),
					'search_items'       => __( 'Search Books', 'wp-bindr' ),
					'not_found'          => __( 'No books found.', 'wp-bindr' ),
					'not_found_in_trash' => __( 'No books found in Trash.', 'wp-bindr' ),
					'all_items'          => __( 'All Books', 'wp-bindr' ),
					'menu_name'          => __( 'Bindr', 'wp-bindr' ),
				),
				'public'       => true,
				'has_archive'  => false,
				'show_in_rest' => true, // Needed for the block's flipbook picker (getEntityRecords).
				'menu_icon'    => 'dashicons-book',
				'supports'     => array( 'title', 'thumbnail' ),
				'rewrite'      => array(
					'slug'       => 'bindr',
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Register post meta (exposed to REST for the block editor preview).
	 */
	public function register_meta() {
		register_post_meta(
			self::POST_TYPE,
			'_bindr_pdf_id',
			array(
				'type'          => 'integer',
				'single'        => true,
				'default'       => 0,
				'show_in_rest'  => true,
				'auth_callback' => array( $this, 'can_edit_books' ),
			)
		);
		register_post_meta(
			self::POST_TYPE,
			'_bindr_page_count',
			array(
				'type'          => 'integer',
				'single'        => true,
				'default'       => 0,
				'show_in_rest'  => true,
				'auth_callback' => array( $this, 'can_edit_books' ),
			)
		);
	}

	/**
	 * Meta auth callback.
	 *
	 * @return bool
	 */
	public function can_edit_books() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Use the classic edit screen for flipbooks — the metabox flow is simpler
	 * for the target audience than a block canvas with no content area.
	 *
	 * @param bool   $use_block_editor Whether to use the block editor.
	 * @param string $post_type        Post type.
	 * @return bool
	 */
	public function disable_block_editor( $use_block_editor, $post_type ) {
		if ( self::POST_TYPE === $post_type ) {
			return false;
		}
		return $use_block_editor;
	}

	/**
	 * Register edit-screen metaboxes.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'bindr-pdf',
			__( 'PDF Document', 'wp-bindr' ),
			array( $this, 'render_pdf_metabox' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
		add_meta_box(
			'bindr-options',
			__( 'Viewer Options', 'wp-bindr' ),
			array( $this, 'render_options_metabox' ),
			self::POST_TYPE,
			'normal',
			'default'
		);
		add_meta_box(
			'bindr-usage',
			__( 'Use This Book', 'wp-bindr' ),
			array( $this, 'render_usage_metabox' ),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * PDF picker metabox.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_pdf_metabox( $post ) {
		wp_nonce_field( 'bindr_save_book', 'bindr_nonce' );

		$pdf_id     = (int) get_post_meta( $post->ID, '_bindr_pdf_id', true );
		$page_count = (int) get_post_meta( $post->ID, '_bindr_page_count', true );
		$pdf_url    = $pdf_id ? wp_get_attachment_url( $pdf_id ) : '';
		$filename   = $pdf_id ? wp_basename( (string) get_attached_file( $pdf_id ) ) : '';
		?>
		<div class="bindr-pdf-picker" data-bindr-pdf-picker>
			<input type="hidden" name="bindr_pdf_id" value="<?php echo esc_attr( (string) $pdf_id ); ?>" data-bindr-field="pdf_id" />
			<input type="hidden" name="bindr_page_count" value="<?php echo esc_attr( (string) $page_count ); ?>" data-bindr-field="page_count" />
			<input type="hidden" name="bindr_page_size" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, '_bindr_page_size', true ) ); ?>" data-bindr-field="page_size" />

			<div class="bindr-pdf-picker__current" data-bindr-current <?php echo $pdf_id ? '' : 'hidden'; ?>>
				<p>
					<strong data-bindr-filename><?php echo esc_html( $filename ); ?></strong>
					<span data-bindr-pagecount>
					<?php
					if ( $page_count ) {
						/* translators: %s: number of pages in the PDF. */
						echo esc_html( sprintf( _n( '%s page', '%s pages', $page_count, 'wp-bindr' ), number_format_i18n( $page_count ) ) );
					}
					?>
					</span>
				</p>
				<div class="bindr-pdf-picker__preview" data-bindr-preview data-pdf-url="<?php echo esc_url( $pdf_url ); ?>"></div>
			</div>

			<p>
				<button type="button" class="button button-primary" data-bindr-select>
					<?php esc_html_e( 'Select PDF from Media Library', 'wp-bindr' ); ?>
				</button>
				<button type="button" class="button" data-bindr-remove <?php echo $pdf_id ? '' : 'hidden'; ?>>
					<?php esc_html_e( 'Remove PDF', 'wp-bindr' ); ?>
				</button>
			</p>
			<p class="description">
				<?php esc_html_e( 'Upload the PDF to your Media Library first, then select it here. The page count and preview are generated automatically.', 'wp-bindr' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Per-book viewer options metabox.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_options_metabox( $post ) {
		$options = self::get_options( $post->ID );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Page display', 'wp-bindr' ); ?></th>
				<td>
					<label>
						<input type="radio" name="bindr_options[display]" value="double" <?php checked( $options['display'], 'double' ); ?> />
						<?php esc_html_e( 'Double page (book spread)', 'wp-bindr' ); ?>
					</label><br />
					<label>
						<input type="radio" name="bindr_options[display]" value="single" <?php checked( $options['display'], 'single' ); ?> />
						<?php esc_html_e( 'Single page', 'wp-bindr' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'On small screens the viewer always uses single-page mode.', 'wp-bindr' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="bindr-option-background"><?php esc_html_e( 'Background color', 'wp-bindr' ); ?></label>
				</th>
				<td>
					<input type="text" id="bindr-option-background" name="bindr_options[background]" value="<?php echo esc_attr( $options['background'] ); ?>" class="bindr-color-field" data-default-color="#404040" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Download', 'wp-bindr' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bindr_options[allow_download]" value="1" <?php checked( $options['allow_download'] ); ?> />
						<?php esc_html_e( 'Show a download button for the original PDF', 'wp-bindr' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="bindr-option-ratio"><?php esc_html_e( 'Aspect ratio', 'wp-bindr' ); ?></label>
				</th>
				<td>
					<select id="bindr-option-ratio" name="bindr_options[ratio]">
						<option value="auto" <?php selected( $options['ratio'], 'auto' ); ?>><?php esc_html_e( 'Automatic (from PDF)', 'wp-bindr' ); ?></option>
						<option value="1.414" <?php selected( $options['ratio'], '1.414' ); ?>><?php esc_html_e( 'A4 portrait', 'wp-bindr' ); ?></option>
						<option value="0.707" <?php selected( $options['ratio'], '0.707' ); ?>><?php esc_html_e( 'A4 landscape', 'wp-bindr' ); ?></option>
						<option value="1.333" <?php selected( $options['ratio'], '1.333' ); ?>><?php esc_html_e( '3:4 portrait', 'wp-bindr' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Height-to-width ratio of a single page. Automatic reads it from the PDF itself.', 'wp-bindr' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * "Use this flipbook" metabox: shortcode, block hint, permalink.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_usage_metabox( $post ) {
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p>' . esc_html__( 'Save the book first to get its shortcode and link.', 'wp-bindr' ) . '</p>';
			return;
		}
		$shortcode = sprintf( '[bindr id="%d"]', $post->ID );
		?>
		<p><strong><?php esc_html_e( 'Shortcode', 'wp-bindr' ); ?></strong></p>
		<p>
			<input type="text" class="widefat code" readonly value="<?php echo esc_attr( $shortcode ); ?>" onfocus="this.select();" />
		</p>
		<p class="description"><?php esc_html_e( 'Paste this into any post, page, or page builder text widget.', 'wp-bindr' ); ?></p>

		<p><strong><?php esc_html_e( 'Block editor', 'wp-bindr' ); ?></strong></p>
		<p class="description"><?php esc_html_e( 'Add the “Bindr Book” block and pick this book from the list.', 'wp-bindr' ); ?></p>

		<?php if ( 'publish' === $post->post_status ) : ?>
			<p><strong><?php esc_html_e( 'Full-page reading mode', 'wp-bindr' ); ?></strong></p>
			<p>
				<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" target="_blank" rel="noopener">
					<?php echo esc_html( get_permalink( $post ) ); ?>
				</a>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Save flipbook meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['bindr_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['bindr_nonce'] ), 'bindr_save_book' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// PDF attachment: must exist and be a PDF.
		$pdf_id = isset( $_POST['bindr_pdf_id'] ) ? absint( $_POST['bindr_pdf_id'] ) : 0;
		if ( $pdf_id && 'application/pdf' !== get_post_mime_type( $pdf_id ) ) {
			$pdf_id = 0;
		}
		update_post_meta( $post_id, '_bindr_pdf_id', $pdf_id );

		// Page count/size come from the client-side PDF.js probe (no Imagick required).
		$page_count = isset( $_POST['bindr_page_count'] ) ? absint( $_POST['bindr_page_count'] ) : 0;
		update_post_meta( $post_id, '_bindr_page_count', $page_count );

		$page_size = isset( $_POST['bindr_page_size'] ) ? sanitize_text_field( wp_unslash( $_POST['bindr_page_size'] ) ) : '';
		if ( ! preg_match( '/^\d+(\.\d+)?x\d+(\.\d+)?$/', $page_size ) ) {
			$page_size = '';
		}
		update_post_meta( $post_id, '_bindr_page_size', $page_size );

		// Options.
		$raw     = isset( $_POST['bindr_options'] ) && is_array( $_POST['bindr_options'] ) ? map_deep( wp_unslash( $_POST['bindr_options'] ), 'sanitize_text_field' ) : array();
		$options = array(
			'display'        => ( isset( $raw['display'] ) && 'single' === $raw['display'] ) ? 'single' : 'double',
			'background'     => isset( $raw['background'] ) ? (string) sanitize_hex_color( $raw['background'] ) : '',
			'allow_download' => ! empty( $raw['allow_download'] ) ? 1 : 0,
			'ratio'          => isset( $raw['ratio'] ) && in_array( $raw['ratio'], array( 'auto', '1.414', '0.707', '1.333' ), true ) ? $raw['ratio'] : 'auto',
		);

		/**
		 * Filter per-book options before saving.
		 *
		 * @param array $options Sanitized options.
		 * @param int   $post_id Flipbook post ID.
		 */
		$options = apply_filters( 'bindr_save_options', $options, $post_id );
		update_post_meta( $post_id, '_bindr_options', $options );

		if ( $pdf_id && ! has_post_thumbnail( $post_id ) ) {
			$this->maybe_generate_cover( $post_id, $pdf_id );
		}

		/**
		 * Fires after a flipbook is saved with valid data.
		 *
		 * @param int     $post_id Flipbook post ID.
		 * @param int     $pdf_id  PDF attachment ID.
		 * @param WP_Post $post    Post object.
		 */
		do_action( 'bindr_book_saved', $post_id, $pdf_id, $post );
	}

	/**
	 * Server-side cover generation from page 1, only if Imagick can read PDFs.
	 * Skips silently otherwise — the viewer renders covers client-side.
	 *
	 * @param int $post_id Flipbook post ID.
	 * @param int $pdf_id  PDF attachment ID.
	 */
	private function maybe_generate_cover( $post_id, $pdf_id ) {
		// WordPress core already generates PDF preview images on upload when
		// Imagick+Ghostscript are available; reuse that instead of rasterizing again.
		$preview = wp_get_attachment_image_url( $pdf_id, 'medium' );
		if ( ! $preview ) {
			return;
		}

		// The preview belongs to the PDF attachment itself; expose it as the
		// flipbook cover by storing the attachment as featured image.
		set_post_thumbnail( $post_id, $pdf_id );
	}

	/**
	 * Get merged per-book options (book meta over global defaults).
	 *
	 * @param int $post_id Flipbook post ID.
	 * @return array
	 */
	public static function get_options( $post_id ) {
		$defaults = array(
			'display'        => bindr_get_setting( 'default_display' ),
			'background'     => bindr_get_setting( 'default_background' ),
			'allow_download' => (int) bindr_get_setting( 'default_allow_download' ),
			'ratio'          => 'auto',
		);
		$saved    = get_post_meta( $post_id, '_bindr_options', true );
		$options  = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
		if ( '' === $options['background'] ) {
			$options['background'] = $defaults['background'];
		}

		/**
		 * Filter resolved per-book viewer options.
		 *
		 * @param array $options Options.
		 * @param int   $post_id Flipbook post ID.
		 */
		return apply_filters( 'bindr_book_options', $options, $post_id );
	}

	/**
	 * Admin assets for the flipbook edit screen only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );

		Bindr_Viewer::register_pdfjs();

		$asset = include BINDR_PLUGIN_DIR . 'build/admin-edit/index.asset.php';
		wp_enqueue_script(
			'bindr-admin-edit',
			BINDR_PLUGIN_URL . 'build/admin-edit/index.js',
			array_merge( $asset['dependencies'], array( 'jquery', 'wp-color-picker', 'bindr-pdfjs' ) ),
			$asset['version'],
			true
		);
		wp_enqueue_style(
			'bindr-admin-edit',
			BINDR_PLUGIN_URL . 'build/admin-edit/index.css',
			array(),
			$asset['version']
		);
		wp_localize_script(
			'bindr-admin-edit',
			'bindrAdminEdit',
			array(
				'workerSrc' => Bindr_Viewer::pdfjs_worker_url(),
				'strings'   => array(
					'selectTitle'  => __( 'Select a PDF', 'wp-bindr' ),
					'selectButton' => __( 'Use this PDF', 'wp-bindr' ),
					/* translators: %s: number of pages in the PDF. */
					'pages'        => __( '%s pages', 'wp-bindr' ),
					'loadError'    => __( 'Could not read this PDF. The file may be corrupted or protected.', 'wp-bindr' ),
				),
			)
		);
	}

	/**
	 * Custom admin list columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function list_columns( $columns ) {
		$date = isset( $columns['date'] ) ? $columns['date'] : '';
		unset( $columns['date'] );
		$columns['bindr_pages']     = __( 'Pages', 'wp-bindr' );
		$columns['bindr_shortcode'] = __( 'Shortcode', 'wp-bindr' );
		if ( $date ) {
			$columns['date'] = $date;
		}
		return $columns;
	}

	/**
	 * Render custom list column values.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_list_column( $column, $post_id ) {
		if ( 'bindr_pages' === $column ) {
			$count = (int) get_post_meta( $post_id, '_bindr_page_count', true );
			echo $count ? esc_html( number_format_i18n( $count ) ) : '&mdash;';
		}
		if ( 'bindr_shortcode' === $column ) {
			printf( '<code>[bindr id="%d"]</code>', (int) $post_id );
		}
	}
}
