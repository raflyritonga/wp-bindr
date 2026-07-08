<?php
/**
 * Unit test bootstrap: Brain Monkey mocks WP functions, so no WordPress
 * install is required. WP classes used as values (WP_Error, requests,
 * responses, wpdb) get minimal stubs here.
 *
 * @package Bindr
 */

define( 'ABSPATH', sys_get_temp_dir() . '/wordpress/' );
define( 'BINDR_VERSION', '0.0.0-test' );
define( 'BINDR_DB_VERSION', '999' );
define( 'BINDR_PLUGIN_FILE', dirname( __DIR__ ) . '/wp-bindr.php' );
define( 'BINDR_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'BINDR_PLUGIN_URL', 'https://example.test/wp-content/plugins/wp-bindr/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/TestCase.php';

require_once BINDR_PLUGIN_DIR . 'includes/class-cpt.php';
require_once BINDR_PLUGIN_DIR . 'includes/class-viewer.php';
require_once BINDR_PLUGIN_DIR . 'includes/class-block.php';
require_once BINDR_PLUGIN_DIR . 'includes/class-analytics.php';
require_once BINDR_PLUGIN_DIR . 'includes/class-settings.php';

/**
 * Test double for the global settings accessor defined in wp-bindr.php.
 * Tests override values via $GLOBALS['bindr_test_settings'].
 *
 * @param string $key Setting key.
 * @return mixed
 */
function bindr_get_setting( $key ) {
	$overrides = isset( $GLOBALS['bindr_test_settings'] ) ? $GLOBALS['bindr_test_settings'] : array();
	if ( array_key_exists( $key, $overrides ) ) {
		return $overrides[ $key ];
	}
	$defaults = Bindr_Settings::defaults();
	return isset( $defaults[ $key ] ) ? $defaults[ $key ] : null;
}

// phpcs:disable -- WP core stubs, intentionally minimal.
class WP_Error {
	public $code;
	public $message;
	public $data;

	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_data() {
		return $this->data;
	}
}

class WP_REST_Response {
	public $data;
	public $status;

	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}
}

// Mirrors the WP_REST_Request surface used by Bindr_Analytics.
class Bindr_Test_Request implements ArrayAccess {
	private $params;
	private $headers;

	public function __construct( array $params = array(), array $headers = array() ) {
		$this->params  = $params;
		$this->headers = $headers;
	}

	public function get_header( $name ) {
		return isset( $this->headers[ $name ] ) ? $this->headers[ $name ] : null;
	}

	#[\ReturnTypeWillChange]
	public function offsetExists( $offset ): bool {
		return isset( $this->params[ $offset ] );
	}

	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		return isset( $this->params[ $offset ] ) ? $this->params[ $offset ] : null;
	}

	#[\ReturnTypeWillChange]
	public function offsetSet( $offset, $value ): void {
		$this->params[ $offset ] = $value;
	}

	#[\ReturnTypeWillChange]
	public function offsetUnset( $offset ): void {
		unset( $this->params[ $offset ] );
	}
}

// Records writes; canned responses for reads.
class Bindr_Test_WPDB {
	public $prefix  = 'wp_';
	public $inserts = array();
	public $queries = array();

	public function insert( $table, $data, $format = null ) {
		$this->inserts[] = array(
			'table' => $table,
			'data'  => $data,
		);
		return 1;
	}

	public function query( $sql ) {
		$this->queries[] = $sql;
		return true;
	}

	public function prepare( $sql, ...$args ) {
		return vsprintf( str_replace( array( '%s', '%d', '%f' ), array( "'%s'", '%d', '%F' ), $sql ), $args );
	}

	public function get_var( $sql ) {
		return null;
	}
}
// phpcs:enable
