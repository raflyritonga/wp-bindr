<?php
/**
 * @package Bindr
 */

namespace Bindr\Tests\Unit;

use Bindr\Tests\TestCase;
use Bindr_Settings;
use Brain\Monkey\Functions;

class SettingsSanitizeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubs(
			array(
				'sanitize_hex_color' => static function ( $color ) {
					return preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', (string) $color ) ? $color : '';
				},
				'esc_url_raw'        => static function ( $url ) {
					return false === strpos( (string) $url, 'javascript:' ) ? (string) $url : '';
				},
				'absint'             => static function ( $value ) {
					return abs( (int) $value );
				},
			)
		);
	}

	private function sanitize( array $input ) {
		return ( new Bindr_Settings() )->sanitize( $input );
	}

	public function test_empty_input_yields_safe_values() {
		$clean = $this->sanitize( array() );

		$this->assertSame( 'double', $clean['default_display'] );
		$this->assertSame( '#404040', $clean['default_background'] );
		$this->assertSame( 0, $clean['default_allow_download'] );
		$this->assertSame( 90, $clean['retention_days'] );
		$this->assertSame( 0, $clean['delete_on_uninstall'] );
	}

	public function test_invalid_display_falls_back_to_double() {
		$this->assertSame( 'double', $this->sanitize( array( 'default_display' => 'triple' ) )['default_display'] );
		$this->assertSame( 'single', $this->sanitize( array( 'default_display' => 'single' ) )['default_display'] );
	}

	public function test_invalid_background_falls_back_to_default() {
		$this->assertSame( '#404040', $this->sanitize( array( 'default_background' => 'red;}body{' ) )['default_background'] );
		$this->assertSame( '#ffffff', $this->sanitize( array( 'default_background' => '#ffffff' ) )['default_background'] );
	}

	public function test_retention_days_are_clamped() {
		$this->assertSame( 7, $this->sanitize( array( 'retention_days' => 2 ) )['retention_days'] );
		$this->assertSame( 3650, $this->sanitize( array( 'retention_days' => 99999 ) )['retention_days'] );
		$this->assertSame( 30, $this->sanitize( array( 'retention_days' => '30' ) )['retention_days'] );
	}

	public function test_checkboxes_normalize_to_int_flags() {
		$clean = $this->sanitize(
			array(
				'default_allow_download' => 'on',
				'delete_on_uninstall'    => '1',
			)
		);

		$this->assertSame( 1, $clean['default_allow_download'] );
		$this->assertSame( 1, $clean['delete_on_uninstall'] );
	}

	public function test_fallback_url_is_sanitized() {
		$this->assertSame( '', $this->sanitize( array( 'fallback_url' => 'javascript:alert(1)' ) )['fallback_url'] );
		$this->assertSame( 'https://example.test/', $this->sanitize( array( 'fallback_url' => 'https://example.test/' ) )['fallback_url'] );
	}
}
