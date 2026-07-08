<?php
/**
 * @package Bindr
 */

namespace Bindr\Tests\Unit;

use Bindr\Tests\TestCase;
use Bindr_Analytics;
use Brain\Monkey\Functions;

class SessionHashTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubs(
			array(
				'wp_unslash'          => null,
				'sanitize_text_field' => null,
				'wp_hash'             => static function ( $data ) {
					return md5( 'salt' . $data );
				},
			)
		);
	}

	public function test_hash_is_32_chars_and_deterministic() {
		$_SERVER['REMOTE_ADDR']     = '203.0.113.7';
		$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

		$hash = Bindr_Analytics::session_hash();

		$this->assertSame( 32, strlen( $hash ) );
		$this->assertSame( $hash, Bindr_Analytics::session_hash() );
	}

	public function test_hash_varies_by_visitor() {
		$_SERVER['REMOTE_ADDR']     = '203.0.113.7';
		$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
		$first                      = Bindr_Analytics::session_hash();

		$_SERVER['HTTP_USER_AGENT'] = 'OtherAgent';
		$second                     = Bindr_Analytics::session_hash();

		$this->assertNotSame( $first, $second );
	}
}
