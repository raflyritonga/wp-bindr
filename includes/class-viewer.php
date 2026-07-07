<?php
/**
 * Viewer: asset registration, config, shared render output.
 *
 * @package Bindr
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end viewer. One render path shared by block, shortcode, and full-page mode.
 */
class Bindr_Viewer {

	/**
	 * Hook registration.
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_filter( 'script_loader_tag', array( $this, 'defer_scripts' ), 10, 2 );
	}

	/**
	 * Register (not enqueue) front-end assets. They are enqueued only when a
	 * flipbook actually renders, so pages without flipbooks load zero bytes.
	 */
	public function register_assets() {
		self::register_pdfjs();

		wp_register_script(
			'bindr-pageflip',
			BINDR_PLUGIN_URL . 'build/vendor/page-flip.browser.js',
			array(),
			BINDR_VERSION,
			true
		);

		$asset = include BINDR_PLUGIN_DIR . 'build/viewer/index.asset.php';
		wp_register_script(
			'bindr-viewer',
			BINDR_PLUGIN_URL . 'build/viewer/index.js',
			array( 'bindr-pdfjs', 'bindr-pageflip' ),
			$asset['version'],
			true
		);
		wp_register_style(
			'bindr-viewer',
			BINDR_PLUGIN_URL . 'build/viewer/index.css',
			array(),
			$asset['version']
		);
	}

	/**
	 * Register the bundled PDF.js library (shared with the admin edit screen).
	 */
	public static function register_pdfjs() {
		if ( ! wp_script_is( 'bindr-pdfjs', 'registered' ) ) {
			wp_register_script(
				'bindr-pdfjs',
				BINDR_PLUGIN_URL . 'build/vendor/pdf.min.js',
				array(),
				BINDR_VERSION,
				true
			);
		}
	}

	/**
	 * URL of the bundled PDF.js worker.
	 *
	 * @return string
	 */
	public static function pdfjs_worker_url() {
		return BINDR_PLUGIN_URL . 'build/vendor/pdf.worker.min.js';
	}

	/**
	 * Load viewer scripts with defer. Uses the script_loader_tag filter for
	 * WP < 6.3 compatibility (the strategy arg only exists since 6.3).
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Script handle.
	 * @return string
	 */
	public function defer_scripts( $tag, $handle ) {
		if ( in_array( $handle, array( 'bindr-pdfjs', 'bindr-pageflip', 'bindr-viewer' ), true ) && false === strpos( $tag, ' defer' ) ) {
			$tag = str_replace( ' src=', ' defer src=', $tag );
		}
		return $tag;
	}

	/**
	 * Render a flipbook viewer container.
	 *
	 * @param array $args {
	 *     Render arguments.
	 *
	 *     @type int    $id          Flipbook post ID (required).
	 *     @type string $height_mode 'ratio' (responsive) or 'fixed'.
	 *     @type int    $height      Fixed height in px (height_mode=fixed).
	 *     @type string $display     '', 'single', or 'double' ('' = book setting).
	 *     @type bool   $fullpage    Whether rendering inside full-page mode.
	 * }
	 * @return string HTML, empty string when the flipbook is unavailable.
	 */
	public function render( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'id'          => 0,
				'height_mode' => 'ratio',
				'height'      => 600,
				'display'     => '',
				'fullpage'    => false,
			)
		);

		$post = get_post( (int) $args['id'] );
		if ( ! $post || Bindr_CPT::POST_TYPE !== $post->post_type ) {
			return '';
		}
		// Draft/private books render only for users who can see them.
		if ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $post->ID ) ) {
			return '';
		}

		$pdf_url = bindr_get_pdf_url( $post->ID );
		if ( ! $pdf_url ) {
			return current_user_can( 'edit_post', $post->ID )
				? '<p class="bindr-viewer-notice">' . esc_html__( 'This book has no PDF attached yet. Edit the book and select one.', 'wp-bindr' ) . '</p>'
				: '';
		}

		wp_enqueue_script( 'bindr-viewer' );
		wp_enqueue_style( 'bindr-viewer' );

		$options = Bindr_CPT::get_options( $post->ID );
		if ( in_array( $args['display'], array( 'single', 'double' ), true ) ) {
			$options['display'] = $args['display'];
		}

		$page_count = (int) get_post_meta( $post->ID, '_bindr_page_count', true );
		$page_size  = (string) get_post_meta( $post->ID, '_bindr_page_size', true );
		$ratio      = $this->resolve_ratio( $options['ratio'], $page_size );
		$cover_url  = get_the_post_thumbnail_url( $post->ID, 'large' );

		$config = array(
			'id'         => $post->ID,
			'pdfUrl'     => esc_url_raw( $pdf_url ),
			'pageCount'  => $page_count,
			'ratio'      => $ratio,
			'display'    => $options['display'],
			'background' => $options['background'],
			'download'   => (bool) $options['allow_download'],
			'fullpage'   => (bool) $args['fullpage'],
			'title'      => get_the_title( $post ),
			'workerSrc'  => self::pdfjs_worker_url(),
			'analytics'  => array(
				// _wpnonce keeps REST cookie auth intact for logged-in
				// readers: without it WordPress demotes the request to
				// anonymous, and the bindr_event nonce (minted for the
				// logged-in user) would never verify — silently dropping
				// every event during logged-in testing.
				'endpoint' => esc_url_raw( add_query_arg( '_wpnonce', wp_create_nonce( 'wp_rest' ), rest_url( 'bindr/v1/event' ) ) ),
				'nonce'    => wp_create_nonce( 'bindr_event' ),
			),
			'strings'    => self::strings(),
		);

		/**
		 * Filter the viewer config JSON before output.
		 *
		 * @param array   $config Viewer config.
		 * @param WP_Post $post   Flipbook post.
		 * @param array   $args   Render args.
		 */
		$config = apply_filters( 'bindr_viewer_config', $config, $post, $args );

		$style = '--bindr-bg:' . $options['background'] . ';';
		if ( 'fixed' === $args['height_mode'] && ! $args['fullpage'] ) {
			$style .= 'height:' . max( 200, (int) $args['height'] ) . 'px;';
		} else {
			$style .= '--bindr-ratio:' . $ratio . ';';
		}

		$classes = 'bindr-viewer' . ( $args['fullpage'] ? ' bindr-viewer--fullpage' : '' ) . ( 'fixed' === $args['height_mode'] ? ' bindr-viewer--fixed' : '' );

		ob_start();
		?>
		<div
			class="<?php echo esc_attr( $classes ); ?>"
			style="<?php echo esc_attr( $style ); ?>"
			data-bindr-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"
			role="region"
			aria-label="<?php echo esc_attr( get_the_title( $post ) ); ?>"
		>
			<div class="bindr-viewer__placeholder">
				<?php if ( $cover_url ) : ?>
					<img class="bindr-viewer__cover" src="<?php echo esc_url( $cover_url ); ?>" alt="" loading="lazy" />
				<?php endif; ?>
				<noscript>
					<a href="<?php echo esc_url( $pdf_url ); ?>">
						<?php
						/* translators: %s: flipbook title. */
						echo esc_html( sprintf( __( 'Read “%s” (PDF)', 'wp-bindr' ), get_the_title( $post ) ) );
						?>
					</a>
				</noscript>
			</div>
		</div>
		<?php
		$html = ob_get_clean();

		/**
		 * Filter the rendered viewer HTML.
		 *
		 * @param string  $html Rendered markup.
		 * @param WP_Post $post Flipbook post.
		 * @param array   $args Render args.
		 */
		return apply_filters( 'bindr_viewer_html', $html, $post, $args );
	}

	/**
	 * Resolve the page height/width ratio.
	 *
	 * @param string $ratio_option Option value ('auto' or numeric string).
	 * @param string $page_size    Stored page size 'WxH' in PDF points.
	 * @return float
	 */
	private function resolve_ratio( $ratio_option, $page_size ) {
		if ( 'auto' !== $ratio_option && is_numeric( $ratio_option ) ) {
			return (float) $ratio_option;
		}
		if ( $page_size && preg_match( '/^(\d+(?:\.\d+)?)x(\d+(?:\.\d+)?)$/', $page_size, $m ) && (float) $m[1] > 0 ) {
			return round( (float) $m[2] / (float) $m[1], 4 );
		}
		return 1.414; // A4 portrait.
	}

	/**
	 * Translated viewer UI strings, passed via config so the front end needs
	 * no JS translation files.
	 *
	 * @return array
	 */
	public static function strings() {
		return array(
			'loading'      => __( 'Loading book…', 'wp-bindr' ),
			'prev'         => __( 'Previous page', 'wp-bindr' ),
			'next'         => __( 'Next page', 'wp-bindr' ),
			/* translators: %1$s: current page number, %2$s: total pages. */
			'pageOf'       => __( 'Page %1$s of %2$s', 'wp-bindr' ),
			'goToPage'     => __( 'Go to page', 'wp-bindr' ),
			'zoomIn'       => __( 'Zoom in', 'wp-bindr' ),
			'zoomOut'      => __( 'Zoom out', 'wp-bindr' ),
			'fullscreen'   => __( 'Toggle fullscreen', 'wp-bindr' ),
			'singleDouble' => __( 'Toggle single or double page', 'wp-bindr' ),
			'download'     => __( 'Download PDF', 'wp-bindr' ),
			'loadError'    => __( 'The book could not be loaded.', 'wp-bindr' ),
			'openPdf'      => __( 'Open the PDF instead', 'wp-bindr' ),
		);
	}
}
