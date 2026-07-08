<?php
/**
 * @package Bindr
 */

namespace Bindr\Tests\Unit;

use Bindr\Tests\TestCase;
use Bindr_Analytics;
use Bindr_Test_Request;
use Brain\Monkey\Functions;
use WP_Error;

class AnalyticsNonceTest extends TestCase {

	private $analytics;

	protected function setUp(): void {
		parent::setUp();
		$this->analytics = new Bindr_Analytics();

		Functions\when( 'wp_verify_nonce' )->alias(
			static function ( $nonce, $action ) {
				return 'valid-nonce' === $nonce && 'bindr_event' === $action ? 1 : false;
			}
		);
	}

	public function test_header_nonce_passes() {
		$request = new Bindr_Test_Request( array(), array( 'X-Bindr-Nonce' => 'valid-nonce' ) );

		$this->assertTrue( $this->analytics->check_nonce( $request ) );
	}

	public function test_body_nonce_is_fallback_for_sendbeacon() {
		$request = new Bindr_Test_Request( array( 'nonce' => 'valid-nonce' ) );

		$this->assertTrue( $this->analytics->check_nonce( $request ) );
	}

	public function test_missing_nonce_returns_403() {
		$result = $this->analytics->check_nonce( new Bindr_Test_Request() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'bindr_bad_nonce', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	public function test_invalid_nonce_returns_403() {
		$request = new Bindr_Test_Request( array( 'nonce' => 'stale-nonce' ) );

		$this->assertInstanceOf( WP_Error::class, $this->analytics->check_nonce( $request ) );
	}
}
