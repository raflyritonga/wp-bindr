<?php
/**
 * @package Bindr
 */

namespace Bindr\Tests\Unit;

use Bindr\Tests\TestCase;
use Bindr_CPT;
use Brain\Monkey\Functions;

class CptOptionsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults ) {
				return array_merge( $defaults, (array) $args );
			}
		);
	}

	public function test_defaults_apply_without_saved_meta() {
		Functions\when( 'get_post_meta' )->justReturn( '' );

		$options = Bindr_CPT::get_options( 1 );

		$this->assertSame( 'double', $options['display'] );
		$this->assertSame( '#404040', $options['background'] );
		$this->assertSame( 1, $options['allow_download'] );
		$this->assertSame( 'auto', $options['ratio'] );
	}

	public function test_saved_meta_overrides_defaults() {
		Functions\when( 'get_post_meta' )->justReturn( array( 'display' => 'single' ) );

		$options = Bindr_CPT::get_options( 1 );

		$this->assertSame( 'single', $options['display'] );
		$this->assertSame( '#404040', $options['background'] );
	}

	public function test_empty_saved_background_falls_back_to_default() {
		Functions\when( 'get_post_meta' )->justReturn( array( 'background' => '' ) );

		$this->assertSame( '#404040', Bindr_CPT::get_options( 1 )['background'] );
	}

	public function test_global_setting_changes_the_default() {
		$GLOBALS['bindr_test_settings'] = array( 'default_display' => 'single' );
		Functions\when( 'get_post_meta' )->justReturn( '' );

		$this->assertSame( 'single', Bindr_CPT::get_options( 1 )['display'] );
	}
}
