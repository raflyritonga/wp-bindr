<?php
/**
 * Plugin Name:       Bindr
 * Plugin URI:        https://wordpress.org/plugins/wp-bindr/
 * Description:       Turn Media Library PDFs into interactive flipbooks. Self-hosted, privacy-friendly, no external services.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Bindr Contributors
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-bindr
 * Domain Path:       /languages
 *
 * @package Bindr
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BINDR_VERSION', '1.0.0' );
define( 'BINDR_DB_VERSION', '3' );
define( 'BINDR_PLUGIN_FILE', __FILE__ );
define( 'BINDR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BINDR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Class map — small enough that a hand-rolled autoloader beats Composer on shared hosting.
spl_autoload_register(
	function ( $class_name ) {
		$map = array(
			'Bindr_Plugin'    => 'includes/class-plugin.php',
			'Bindr_CPT'       => 'includes/class-cpt.php',
			'Bindr_Block'     => 'includes/class-block.php',
			'Bindr_Viewer'    => 'includes/class-viewer.php',
			'Bindr_Fullpage'  => 'includes/class-fullpage.php',
			'Bindr_Analytics' => 'includes/class-analytics.php',
			'Bindr_Dashboard' => 'includes/class-dashboard.php',
			'Bindr_Settings'  => 'includes/class-settings.php',
			'Bindr_CLI'       => 'includes/class-cli.php',
		);
		if ( isset( $map[ $class_name ] ) ) {
			require BINDR_PLUGIN_DIR . $map[ $class_name ];
		}
	}
);

/**
 * Return the shared plugin container.
 *
 * @return Bindr_Plugin
 */
function bindr_plugin() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new Bindr_Plugin();
	}
	return $instance;
}

/**
 * Get the PDF URL for a book. Filterable via `bindr_pdf_url` (e.g. CDN delivery).
 *
 * @param int $book_id Book post ID.
 * @return string PDF URL, empty string if none.
 */
function bindr_get_pdf_url( $book_id ) {
	$pdf_id = (int) get_post_meta( $book_id, '_bindr_pdf_id', true );
	$url    = $pdf_id ? (string) wp_get_attachment_url( $pdf_id ) : '';

	/**
	 * Filter the PDF URL served to the viewer.
	 *
	 * @param string $url         PDF URL.
	 * @param int    $book_id Book post ID.
	 * @param int    $pdf_id      Attachment ID.
	 */
	return apply_filters( 'bindr_pdf_url', $url, $book_id, $pdf_id );
}

/**
 * Get a plugin setting with default fallback.
 *
 * @param string $key Setting key.
 * @return mixed
 */
function bindr_get_setting( $key ) {
	$settings = wp_parse_args( (array) get_option( 'bindr_settings', array() ), Bindr_Settings::defaults() );
	$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : null;

	/**
	 * Filter a single plugin setting value.
	 *
	 * @param mixed  $value Setting value.
	 * @param string $key   Setting key.
	 */
	return apply_filters( 'bindr_setting', $value, $key );
}

register_activation_hook( __FILE__, array( 'Bindr_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Bindr_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( bindr_plugin(), 'init' ) );
