<?php
/**
 * Analytics: tables, REST ingestion, rollups, pruning.
 *
 * Privacy model: no cookies, no PII. Sessions are identified by a salted
 * daily hash of IP + user agent, computed server-side. The salt includes the
 * date, so the hash cannot link a visitor across days and is not reversible.
 *
 * @package Bindr
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Event storage and aggregation.
 */
class Bindr_Analytics {

	const EVENTS = array( 'open', 'page_turn', 'complete', 'download', 'fullpage_open' );

	/**
	 * Max events accepted per request and per session/minute.
	 */
	const MAX_BATCH  = 25;
	const RATE_LIMIT = 120;

	/**
	 * Hook registration.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'bindr_daily_rollup', array( $this, 'run_rollup' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade_db' ) );
	}

	/**
	 * Events table name.
	 *
	 * @return string
	 */
	public static function events_table() {
		global $wpdb;
		return $wpdb->prefix . 'bindr_events';
	}

	/**
	 * Daily rollup table name.
	 *
	 * @return string
	 */
	public static function daily_table() {
		global $wpdb;
		return $wpdb->prefix . 'bindr_daily';
	}

	/**
	 * Create/upgrade tables via dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$events  = self::events_table();
		$daily   = self::daily_table();

		dbDelta(
			"CREATE TABLE {$events} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				flipbook_id bigint(20) unsigned NOT NULL,
				event varchar(20) NOT NULL,
				page smallint(5) unsigned NOT NULL DEFAULT 0,
				session_hash char(32) NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY flipbook_created (flipbook_id, created_at),
				KEY created_at (created_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$daily} (
				flipbook_id bigint(20) unsigned NOT NULL,
				day date NOT NULL,
				opens int(10) unsigned NOT NULL DEFAULT 0,
				uniques int(10) unsigned NOT NULL DEFAULT 0,
				completes int(10) unsigned NOT NULL DEFAULT 0,
				downloads int(10) unsigned NOT NULL DEFAULT 0,
				avg_max_page decimal(6,2) NOT NULL DEFAULT 0,
				PRIMARY KEY  (flipbook_id, day),
				KEY day (day)
			) {$charset};"
		);
	}

	/**
	 * Run dbDelta again if the schema version changed on plugin update.
	 */
	public function maybe_upgrade_db() {
		if ( (string) get_option( 'bindr_db_version' ) !== BINDR_DB_VERSION ) {
			self::create_tables();
			update_option( 'bindr_db_version', BINDR_DB_VERSION );
		}
	}

	/**
	 * REST routes. Versioned namespace (v1) — v2 seam for a future aggregator.
	 */
	public function register_routes() {
		register_rest_route(
			'bindr/v1',
			'/event',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'ingest' ),
				'permission_callback' => array( $this, 'check_nonce' ),
				'args'                => array(
					'events' => array(
						'required' => true,
						'type'     => 'array',
					),
				),
			)
		);
	}

	/**
	 * Nonce check for the event route. Anonymous-friendly: the nonce is minted
	 * into the viewer config at render time.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function check_nonce( $request ) {
		$nonce = $request->get_header( 'X-Bindr-Nonce' );
		if ( ! $nonce && isset( $request['nonce'] ) ) {
			$nonce = (string) $request['nonce'];
		}
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'bindr_event' ) ) {
			return new WP_Error( 'bindr_bad_nonce', __( 'Invalid or expired analytics token.', 'wp-bindr' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Ingest a batch of events.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function ingest( $request ) {
		$events = $request['events'];
		if ( ! is_array( $events ) || empty( $events ) ) {
			return new WP_Error( 'bindr_bad_batch', __( 'Empty event batch.', 'wp-bindr' ), array( 'status' => 400 ) );
		}
		$events = array_slice( $events, 0, self::MAX_BATCH );

		$session = self::session_hash();

		// Transient-based rate limit per session hash per minute.
		$key   = 'bindr_rl_' . $session;
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
			return new WP_Error( 'bindr_rate_limited', __( 'Too many events.', 'wp-bindr' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $count + count( $events ), MINUTE_IN_SECONDS );

		global $wpdb;
		$table    = self::events_table();
		$now      = current_time( 'mysql', true );
		$accepted = 0;

		// Validate flipbook IDs once per batch; only published books count.
		$valid_books = array();

		foreach ( $events as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}
			$flipbook_id = isset( $event['flipbook'] ) ? absint( $event['flipbook'] ) : 0;
			$type        = isset( $event['event'] ) ? sanitize_key( $event['event'] ) : '';
			$page        = isset( $event['page'] ) ? absint( $event['page'] ) : 0;

			if ( ! $flipbook_id || ! in_array( $type, self::EVENTS, true ) ) {
				continue;
			}

			if ( ! isset( $valid_books[ $flipbook_id ] ) ) {
				// Must not leak private/draft books: publish status only.
				$valid_books[ $flipbook_id ] = (
					Bindr_CPT::POST_TYPE === get_post_type( $flipbook_id )
					&& 'publish' === get_post_status( $flipbook_id )
				);
			}
			if ( ! $valid_books[ $flipbook_id ] ) {
				continue;
			}

			$max_page = (int) get_post_meta( $flipbook_id, '_bindr_page_count', true );
			if ( $max_page && $page > $max_page ) {
				$page = $max_page;
			}

			/**
			 * Fires before an analytics event is stored.
			 *
			 * @param string $type        Event type.
			 * @param int    $flipbook_id Flipbook post ID.
			 * @param int    $page        Page number.
			 */
			do_action( 'bindr_event_received', $type, $flipbook_id, $page );

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom analytics table.
				$table,
				array(
					'flipbook_id'  => $flipbook_id,
					'event'        => $type,
					'page'         => $page,
					'session_hash' => $session,
					'created_at'   => $now,
				),
				array( '%d', '%s', '%d', '%s', '%s' )
			);
			++$accepted;
		}

		return new WP_REST_Response( array( 'accepted' => $accepted ), 202 );
	}

	/**
	 * Salted daily session hash. Server-side only; the client never supplies it.
	 * wp_hash() mixes in the site's auth salts; the date makes it rotate daily.
	 *
	 * @return string 32-char hash.
	 */
	public static function session_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return substr( wp_hash( $ip . '|' . $ua . '|' . gmdate( 'Y-m-d' ) ), 0, 32 );
	}

	/**
	 * Daily cron: roll up all fully-elapsed days that still have raw events,
	 * then prune old raw events. Recovers automatically from missed cron runs.
	 */
	public function run_rollup() {
		global $wpdb;
		$events = self::events_table();
		$daily  = self::daily_table();
		$today  = gmdate( 'Y-m-d' );

		// Days before today that still have raw events.
		$days = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT DISTINCT DATE(created_at) FROM {$events} WHERE created_at < %s ORDER BY 1 ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name built from $wpdb->prefix.
				$today . ' 00:00:00'
			)
		);

		foreach ( $days as $day ) {
			$this->rollup_day( $day );
		}

		$this->prune();

		/**
		 * Fires after the daily rollup completes.
		 *
		 * @param array $days Days that were aggregated.
		 */
		do_action( 'bindr_rollup_complete', $days );
	}

	/**
	 * Aggregate one day of raw events into the daily table.
	 *
	 * @param string $day Day in Y-m-d.
	 */
	public function rollup_day( $day ) {
		global $wpdb;
		$events = self::events_table();
		$daily  = self::daily_table();
		$start  = $day . ' 00:00:00';
		$end    = $day . ' 23:59:59';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom tables; names from $wpdb->prefix.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT flipbook_id,
					SUM(event = 'open') AS opens,
					COUNT(DISTINCT session_hash) AS uniques,
					SUM(event = 'complete') AS completes,
					SUM(event = 'download') AS downloads
				FROM {$events}
				WHERE created_at BETWEEN %s AND %s
				GROUP BY flipbook_id",
				$start,
				$end
			)
		);

		foreach ( $rows as $row ) {
			$avg_max = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT AVG(max_page) FROM (
						SELECT MAX(page) AS max_page FROM {$events}
						WHERE flipbook_id = %d AND created_at BETWEEN %s AND %s AND event = 'page_turn'
						GROUP BY session_hash
					) t",
					$row->flipbook_id,
					$start,
					$end
				)
			);

			$wpdb->query(
				$wpdb->prepare(
					"REPLACE INTO {$daily} (flipbook_id, day, opens, uniques, completes, downloads, avg_max_page)
					VALUES (%d, %s, %d, %d, %d, %d, %f)",
					$row->flipbook_id,
					$day,
					(int) $row->opens,
					(int) $row->uniques,
					(int) $row->completes,
					(int) $row->downloads,
					round( $avg_max, 2 )
				)
			);
		}
		// phpcs:enable
	}

	/**
	 * Delete raw events past the retention window.
	 */
	public function prune() {
		global $wpdb;

		/**
		 * Filter raw event retention in days.
		 *
		 * @param int $days Retention days.
		 */
		$days   = (int) apply_filters( 'bindr_event_retention_days', (int) bindr_get_setting( 'retention_days' ) );
		$days   = max( 1, $days );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$events = self::events_table();

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "DELETE FROM {$events} WHERE created_at < %s", $cutoff ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Dashboard data: per-day totals for a range. Uses rollups for past days
	 * plus a live aggregation of today's raw events.
	 *
	 * @param string $from        Y-m-d inclusive.
	 * @param string $to          Y-m-d inclusive.
	 * @param int    $flipbook_id Optional single book filter.
	 * @return array[] Rows: day, opens, uniques, completes, downloads.
	 */
	public function get_timeseries( $from, $to, $flipbook_id = 0 ) {
		global $wpdb;
		$daily  = self::daily_table();
		$events = self::events_table();
		$today  = gmdate( 'Y-m-d' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$book_sql = $flipbook_id ? $wpdb->prepare( ' AND flipbook_id = %d', $flipbook_id ) : '';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT day, SUM(opens) AS opens, SUM(uniques) AS uniques, SUM(completes) AS completes, SUM(downloads) AS downloads
				FROM {$daily} WHERE day BETWEEN %s AND %s{$book_sql}
				GROUP BY day ORDER BY day ASC",
				$from,
				$to
			),
			ARRAY_A
		);

		if ( $to >= $today && $from <= $today ) {
			$live = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT SUM(event = 'open') AS opens, COUNT(DISTINCT session_hash) AS uniques,
						SUM(event = 'complete') AS completes, SUM(event = 'download') AS downloads
					FROM {$events} WHERE created_at >= %s{$book_sql}",
					$today . ' 00:00:00'
				),
				ARRAY_A
			);
			if ( $live && (int) $live['uniques'] > 0 ) {
				$rows[] = array_merge( array( 'day' => $today ), array_map( 'intval', $live ) );
			}
		}
		// phpcs:enable

		return array_map(
			static function ( $row ) {
				return array(
					'day'       => $row['day'],
					'opens'     => (int) $row['opens'],
					'uniques'   => (int) $row['uniques'],
					'completes' => (int) $row['completes'],
					'downloads' => (int) $row['downloads'],
				);
			},
			$rows
		);
	}

	/**
	 * Per-book totals for a range (rollups + today's live events).
	 *
	 * @param string $from Y-m-d inclusive.
	 * @param string $to   Y-m-d inclusive.
	 * @return array[] Rows keyed by flipbook_id.
	 */
	public function get_book_totals( $from, $to ) {
		global $wpdb;
		$daily  = self::daily_table();
		$events = self::events_table();
		$today  = gmdate( 'Y-m-d' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT flipbook_id, SUM(opens) AS opens, SUM(uniques) AS uniques, SUM(completes) AS completes, SUM(downloads) AS downloads, AVG(avg_max_page) AS avg_max_page
				FROM {$daily} WHERE day BETWEEN %s AND %s GROUP BY flipbook_id",
				$from,
				$to
			),
			OBJECT_K
		);

		if ( $to >= $today && $from <= $today ) {
			$live = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT flipbook_id, SUM(event = 'open') AS opens, COUNT(DISTINCT session_hash) AS uniques,
						SUM(event = 'complete') AS completes, SUM(event = 'download') AS downloads
					FROM {$events} WHERE created_at >= %s GROUP BY flipbook_id",
					$today . ' 00:00:00'
				)
			);
			foreach ( $live as $row ) {
				$id = (int) $row->flipbook_id;
				if ( isset( $rows[ $id ] ) ) {
					$rows[ $id ]->opens     += (int) $row->opens;
					$rows[ $id ]->uniques   += (int) $row->uniques;
					$rows[ $id ]->completes += (int) $row->completes;
					$rows[ $id ]->downloads += (int) $row->downloads;
				} else {
					$row->avg_max_page = 0;
					$rows[ $id ]       = $row;
				}
			}
		}
		// phpcs:enable

		$totals = array();
		foreach ( $rows as $id => $row ) {
			$totals[ (int) $id ] = array(
				'opens'        => (int) $row->opens,
				'uniques'      => (int) $row->uniques,
				'completes'    => (int) $row->completes,
				'downloads'    => (int) $row->downloads,
				'avg_max_page' => (float) $row->avg_max_page,
			);
		}
		return $totals;
	}
}
