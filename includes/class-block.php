<?php
/**
 * Gutenberg block + shortcode. Both delegate to Bindr_Viewer::render() so
 * output never diverges.
 *
 * @package Bindr
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The bindr/flipbook block and [flipbook] shortcode.
 */
class Bindr_Block {

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
		add_action( 'init', array( $this, 'register_block' ) );
		add_shortcode( 'flipbook', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Register the block from build metadata with a PHP render callback.
	 */
	public function register_block() {
		register_block_type(
			BINDR_PLUGIN_DIR . 'build/block',
			array(
				'render_callback' => array( $this, 'render_block' ),
			)
		);
		wp_set_script_translations(
			'bindr-flipbook-editor-script',
			'wp-bindr',
			BINDR_PLUGIN_DIR . 'languages'
		);
	}

	/**
	 * Block render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		return $this->viewer->render(
			array(
				'id'          => isset( $attributes['flipbookId'] ) ? (int) $attributes['flipbookId'] : 0,
				'height_mode' => ( isset( $attributes['heightMode'] ) && 'fixed' === $attributes['heightMode'] ) ? 'fixed' : 'ratio',
				'height'      => isset( $attributes['height'] ) ? (int) $attributes['height'] : 600,
				'display'     => isset( $attributes['display'] ) ? (string) $attributes['display'] : '',
			)
		);
	}

	/**
	 * Shortcode: [flipbook id="123" height="600" height_mode="ratio|fixed" display="single|double"].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'          => 0,
				'height_mode' => 'ratio',
				'height'      => 600,
				'display'     => '',
			),
			$atts,
			'flipbook'
		);

		return $this->viewer->render(
			array(
				'id'          => (int) $atts['id'],
				'height_mode' => 'fixed' === $atts['height_mode'] ? 'fixed' : 'ratio',
				'height'      => (int) $atts['height'],
				'display'     => in_array( $atts['display'], array( 'single', 'double' ), true ) ? $atts['display'] : '',
			)
		);
	}
}
