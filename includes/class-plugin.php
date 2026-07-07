<?php
/**
 * Main plugin container.
 *
 * @package Bindr
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires all plugin components together.
 */
class Bindr_Plugin {

	/**
	 * Component instances.
	 *
	 * @var object[]
	 */
	private $components = array();

	/**
	 * Boot all components. Runs on plugins_loaded.
	 */
	public function init() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		$this->components['cpt']       = new Bindr_CPT();
		$this->components['viewer']    = new Bindr_Viewer();
		$this->components['block']     = new Bindr_Block( $this->components['viewer'] );
		$this->components['fullpage']  = new Bindr_Fullpage( $this->components['viewer'] );
		$this->components['analytics'] = new Bindr_Analytics();
		$this->components['settings']  = new Bindr_Settings();

		if ( is_admin() ) {
			$this->components['dashboard'] = new Bindr_Dashboard( $this->components['analytics'] );
		}

		foreach ( $this->components as $component ) {
			$component->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			Bindr_CLI::register( $this->components['analytics'] );
		}

		/**
		 * Fires after all Bindr components are registered.
		 *
		 * @param Bindr_Plugin $plugin Plugin container.
		 */
		do_action( 'bindr_loaded', $this );
	}

	/**
	 * Load bundled translations (id_ID ships with the plugin).
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wp-bindr', false, dirname( plugin_basename( BINDR_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Get a component by key.
	 *
	 * @param string $key Component key.
	 * @return object|null
	 */
	public function get( $key ) {
		return isset( $this->components[ $key ] ) ? $this->components[ $key ] : null;
	}

	/**
	 * Activation: create tables, register CPT, flush rewrites, schedule cron.
	 */
	public static function activate() {
		Bindr_Analytics::install_or_upgrade();

		// CPT must exist before flushing so its rewrite rules are included.
		$cpt = new Bindr_CPT();
		$cpt->register_post_type();
		flush_rewrite_rules();

		if ( ! wp_next_scheduled( 'bindr_daily_rollup' ) ) {
			// 03:10 site time keeps the aggregation off peak hours on shared hosts.
			$first = strtotime( 'tomorrow 03:10' ) - ( (int) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS );
			wp_schedule_event( $first, 'daily', 'bindr_daily_rollup' );
		}
	}

	/**
	 * Deactivation: unschedule cron, flush rewrites. Data is kept (lossless).
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'bindr_daily_rollup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'bindr_daily_rollup' );
		}
		flush_rewrite_rules();
	}
}
