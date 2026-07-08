<?php
/**
 * @package Bindr
 */

namespace Bindr\Tests\Unit;

use Bindr\Tests\TestCase;
use Bindr_Analytics;
use Bindr_Test_Request;
use Bindr_Test_WPDB;
use Brain\Monkey\Functions;
use WP_Error;
use WP_REST_Response;

class AnalyticsIngestTest extends TestCase {

	private $wpdb;
	private $analytics;

	protected function setUp(): void {
		parent::setUp();

		$this->wpdb      = new Bindr_Test_WPDB();
		$GLOBALS['wpdb'] = $this->wpdb;
		$this->analytics = new Bindr_Analytics();

		$_SERVER['REMOTE_ADDR']     = '203.0.113.7';
		$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

		Functions\stubs(
			array(
				'wp_unslash'          => null, // Pass-through.
				'sanitize_text_field' => null,
				'wp_hash'             => static function ( $data ) {
					return md5( 'salt' . $data );
				},
				'absint'              => static function ( $value ) {
					return abs( (int) $value );
				},
				'sanitize_key'        => static function ( $key ) {
					return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
				},
				'get_transient'       => false,
				'set_transient'       => true,
				'current_time'        => '2026-01-01 00:00:00',
				'get_post_type'       => 'bindr_flipbook',
				'get_post_status'     => 'publish',
				'get_post_meta'       => 10, // _bindr_page_count.
			)
		);
	}

	private function ingest( array $events ) {
		return $this->analytics->ingest( new Bindr_Test_Request( array( 'events' => $events ) ) );
	}

	public function test_valid_event_is_stored() {
		$response = $this->ingest(
			array(
				array(
					'flipbook' => 5,
					'event'    => 'open',
					'page'     => 0,
				),
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 202, $response->status );
		$this->assertSame( 1, $response->data['accepted'] );

		$row = $this->wpdb->inserts[0]['data'];
		$this->assertSame( 5, $row['flipbook_id'] );
		$this->assertSame( 'open', $row['event'] );
		$this->assertSame( 32, strlen( $row['session_hash'] ) );
	}

	public function test_unknown_event_type_is_dropped() {
		$response = $this->ingest(
			array(
				array(
					'flipbook' => 5,
					'event'    => 'sql_injection',
					'page'     => 1,
				),
			)
		);

		$this->assertSame( 0, $response->data['accepted'] );
		$this->assertSame( array(), $this->wpdb->inserts );
	}

	public function test_unpublished_flipbook_is_dropped() {
		Functions\when( 'get_post_status' )->justReturn( 'draft' );

		$response = $this->ingest(
			array(
				array(
					'flipbook' => 5,
					'event'    => 'open',
				),
			)
		);

		$this->assertSame( 0, $response->data['accepted'] );
	}

	public function test_foreign_post_type_is_dropped() {
		Functions\when( 'get_post_type' )->justReturn( 'post' );

		$response = $this->ingest(
			array(
				array(
					'flipbook' => 5,
					'event'    => 'open',
				),
			)
		);

		$this->assertSame( 0, $response->data['accepted'] );
	}

	public function test_page_is_clamped_to_page_count() {
		$this->ingest(
			array(
				array(
					'flipbook' => 5,
					'event'    => 'page_turn',
					'page'     => 999,
				),
			)
		);

		$this->assertSame( 10, $this->wpdb->inserts[0]['data']['page'] );
	}

	public function test_empty_batch_is_rejected() {
		$response = $this->ingest( array() );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'bindr_bad_batch', $response->get_error_code() );
	}

	public function test_rate_limit_returns_429() {
		Functions\when( 'get_transient' )->justReturn( Bindr_Analytics::RATE_LIMIT );

		$response = $this->ingest(
			array(
				array(
					'flipbook' => 5,
					'event'    => 'open',
				),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'bindr_rate_limited', $response->get_error_code() );
		$this->assertSame( 429, $response->get_error_data()['status'] );
	}

	public function test_batch_is_capped_at_max_batch() {
		$events = array_fill(
			0,
			Bindr_Analytics::MAX_BATCH + 10,
			array(
				'flipbook' => 5,
				'event'    => 'open',
			)
		);

		$response = $this->ingest( $events );

		$this->assertSame( Bindr_Analytics::MAX_BATCH, $response->data['accepted'] );
		$this->assertCount( Bindr_Analytics::MAX_BATCH, $this->wpdb->inserts );
	}
}
