<?php
/**
 * Full-page reading mode.
 *
 * @package Bindr
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves flipbook permalinks with the plugin's own blank-canvas template.
 */
class Bindr_Fullpage {

	/**
	 * Viewer renderer.
	 *
	 * @var Bindr_Viewer
	 */
	private $viewer;

	/**
	 * Constructor.
	 *
	 * @param Bindr_Viewer $viewer Viewer instance.
	 */
	public function __construct( Bindr_Viewer $viewer ) {
		$this->viewer = $viewer;
	}

	/**
	 * Hook registration.
	 */
	public function register() {
		add_filter( 'template_include', array( $this, 'template' ), 99 );
		add_action( 'wp_head', array( $this, 'seo_meta' ), 5 );
	}

	/**
	 * Whether the current main query is a flipbook permalink.
	 *
	 * @return bool
	 */
	private function is_fullpage() {
		return is_singular( Bindr_CPT::POST_TYPE );
	}

	/**
	 * Swap in our blank-canvas template. Never uses the theme's templates —
	 * this is the core of the theme-compatibility strategy (§7).
	 *
	 * @param string $template Template path chosen by WP.
	 * @return string
	 */
	public function template( $template ) {
		if ( $this->is_fullpage() ) {
			/**
			 * Filter the full-page template path.
			 *
			 * @param string $path Template path.
			 */
			return apply_filters( 'bindr_fullpage_template', BINDR_PLUGIN_DIR . 'templates/fullpage.php' );
		}
		return $template;
	}

	/**
	 * Basic SEO/OG meta for shared flipbook URLs.
	 */
	public function seo_meta() {
		if ( ! $this->is_fullpage() ) {
			return;
		}
		$post  = get_queried_object();
		$title = get_the_title( $post );
		$cover = get_the_post_thumbnail_url( $post->ID, 'large' );
		?>
		<meta property="og:type" content="article" />
		<meta property="og:title" content="<?php echo esc_attr( $title ); ?>" />
		<meta property="og:url" content="<?php echo esc_url( get_permalink( $post ) ); ?>" />
		<?php if ( $cover ) : ?>
		<meta property="og:image" content="<?php echo esc_url( $cover ); ?>" />
		<?php endif; ?>
		<?php
	}

	/**
	 * Render the viewer for the template.
	 *
	 * @param int $post_id Flipbook post ID.
	 * @return string
	 */
	public function render_viewer( $post_id ) {
		return $this->viewer->render(
			array(
				'id'       => $post_id,
				'fullpage' => true,
			)
		);
	}

	/**
	 * Back link URL fallback (used when there is no same-origin referrer).
	 *
	 * @return string
	 */
	public static function fallback_url() {
		$url = (string) bindr_get_setting( 'fallback_url' );
		if ( '' === $url ) {
			$url = home_url( '/' );
		}

		/**
		 * Filter the full-page back button fallback URL.
		 *
		 * @param string $url Fallback URL.
		 */
		return apply_filters( 'bindr_fallback_url', $url );
	}
}
