<?php
/**
 * Admin analytics dashboard + CSV export + per-book stats box.
 *
 * @package Bindr
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flipbooks → Analytics screen.
 */
class Bindr_Dashboard {

	/**
	 * Analytics store.
	 *
	 * @var Bindr_Analytics
	 */
	private $analytics;

	/**
	 * Constructor.
	 *
	 * @param Bindr_Analytics $analytics Analytics instance.
	 */
	public function __construct( Bindr_Analytics $analytics ) {
		$this->analytics = $analytics;
	}

	/**
	 * Hook registration.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_bindr_export_csv', array( $this, 'export_csv' ) );
		add_action( 'add_meta_boxes_' . Bindr_CPT::POST_TYPE, array( $this, 'add_stats_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Flipbooks → Analytics (admins only).
	 */
	public function add_page() {
		add_submenu_page(
			'edit.php?post_type=' . Bindr_CPT::POST_TYPE,
			__( 'Bindr Analytics', 'wp-bindr' ),
			__( 'Analytics', 'wp-bindr' ),
			'manage_options',
			'bindr-analytics',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Resolve the requested date range (7/30/90 days).
	 *
	 * @return array{from:string,to:string,days:int}
	 */
	private function get_range() {
		$days = isset( $_GET['bindr_range'] ) ? absint( $_GET['bindr_range'] ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view filter.
		if ( ! in_array( $days, array( 7, 30, 90 ), true ) ) {
			$days = 30;
		}
		return array(
			'from' => gmdate( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS ),
			'to'   => gmdate( 'Y-m-d' ),
			'days' => $days,
		);
	}

	/**
	 * Render the dashboard.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$range  = $this->get_range();
		$series = $this->analytics->get_timeseries( $range['from'], $range['to'] );
		$books  = $this->analytics->get_book_totals( $range['from'], $range['to'] );

		$totals = array(
			'opens'     => 0,
			'uniques'   => 0,
			'completes' => 0,
			'downloads' => 0,
		);
		foreach ( $series as $row ) {
			$totals['opens']     += $row['opens'];
			$totals['uniques']   += $row['uniques'];
			$totals['completes'] += $row['completes'];
			$totals['downloads'] += $row['downloads'];
		}
		$completion = $totals['opens'] > 0 ? round( 100 * $totals['completes'] / $totals['opens'] ) : 0;

		uasort(
			$books,
			static function ( $a, $b ) {
				return $b['opens'] <=> $a['opens'];
			}
		);

		$export_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'    => 'bindr_export_csv',
					'bindr_range' => $range['days'],
				),
				admin_url( 'admin-post.php' )
			),
			'bindr_export_csv'
		);
		?>
		<div class="wrap bindr-dashboard">
			<h1><?php esc_html_e( 'Bindr Analytics', 'wp-bindr' ); ?></h1>

			<p class="bindr-dashboard__range">
				<?php
				foreach ( array( 7, 30, 90 ) as $days ) {
					$url   = add_query_arg( 'bindr_range', $days );
					$class = $days === $range['days'] ? 'button button-primary' : 'button';
					printf(
						'<a class="%s" href="%s">%s</a> ',
						esc_attr( $class ),
						esc_url( $url ),
						/* translators: %s: number of days. */
						esc_html( sprintf( __( 'Last %s days', 'wp-bindr' ), number_format_i18n( $days ) ) )
					);
				}
				?>
				<a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'wp-bindr' ); ?></a>
			</p>

			<div class="bindr-cards">
				<div class="bindr-card">
					<span class="bindr-card__num"><?php echo esc_html( number_format_i18n( $totals['opens'] ) ); ?></span>
					<span class="bindr-card__label"><?php esc_html_e( 'Reads', 'wp-bindr' ); ?></span>
				</div>
				<div class="bindr-card">
					<span class="bindr-card__num"><?php echo esc_html( number_format_i18n( $totals['uniques'] ) ); ?></span>
					<span class="bindr-card__label"><?php esc_html_e( 'Unique readers (daily)', 'wp-bindr' ); ?></span>
				</div>
				<div class="bindr-card">
					<span class="bindr-card__num"><?php echo esc_html( $completion ); ?>%</span>
					<span class="bindr-card__label"><?php esc_html_e( 'Completion rate', 'wp-bindr' ); ?></span>
				</div>
				<div class="bindr-card">
					<span class="bindr-card__num"><?php echo esc_html( number_format_i18n( $totals['downloads'] ) ); ?></span>
					<span class="bindr-card__label"><?php esc_html_e( 'Downloads', 'wp-bindr' ); ?></span>
				</div>
			</div>

			<div class="bindr-chart-wrap">
				<h2><?php esc_html_e( 'Reads per day', 'wp-bindr' ); ?></h2>
				<div id="bindr-chart" data-series="<?php echo esc_attr( wp_json_encode( $series ) ); ?>" data-from="<?php echo esc_attr( $range['from'] ); ?>" data-to="<?php echo esc_attr( $range['to'] ); ?>"></div>
			</div>

			<h2><?php esc_html_e( 'Per book', 'wp-bindr' ); ?></h2>
			<table class="widefat striped bindr-books-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Book', 'wp-bindr' ); ?></th>
						<th><?php esc_html_e( 'Reads', 'wp-bindr' ); ?></th>
						<th><?php esc_html_e( 'Unique readers (daily)', 'wp-bindr' ); ?></th>
						<th><?php esc_html_e( 'Completions', 'wp-bindr' ); ?></th>
						<th><?php esc_html_e( 'Downloads', 'wp-bindr' ); ?></th>
						<th><?php esc_html_e( 'Avg. furthest page', 'wp-bindr' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $books ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No reading activity in this period yet.', 'wp-bindr' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $books as $id => $row ) : ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( (string) get_edit_post_link( $id ) ); ?>">
										<?php echo esc_html( get_the_title( $id ) ? get_the_title( $id ) : sprintf( '#%d', $id ) ); ?>
									</a>
								</td>
								<td><?php echo esc_html( number_format_i18n( $row['opens'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['uniques'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['completes'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['downloads'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['avg_max_page'], 1 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Stream the CSV export (UTF-8 BOM so Excel opens it correctly).
	 */
	public function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to export analytics.', 'wp-bindr' ) );
		}
		check_admin_referer( 'bindr_export_csv' );

		$range  = $this->get_range();
		$series = $this->analytics->get_timeseries( $range['from'], $range['to'] );
		$books  = $this->analytics->get_book_totals( $range['from'], $range['to'] );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=bindr-analytics-' . $range['from'] . '-' . $range['to'] . '.csv' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming CSV to the browser.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		fputcsv( $out, array( __( 'Day', 'wp-bindr' ), __( 'Reads', 'wp-bindr' ), __( 'Unique readers', 'wp-bindr' ), __( 'Completions', 'wp-bindr' ), __( 'Downloads', 'wp-bindr' ) ) );
		foreach ( $series as $row ) {
			fputcsv( $out, array( $row['day'], $row['opens'], $row['uniques'], $row['completes'], $row['downloads'] ) );
		}

		fputcsv( $out, array() );
		fputcsv( $out, array( __( 'Book', 'wp-bindr' ), __( 'Reads', 'wp-bindr' ), __( 'Unique readers', 'wp-bindr' ), __( 'Completions', 'wp-bindr' ), __( 'Downloads', 'wp-bindr' ), __( 'Avg. furthest page', 'wp-bindr' ) ) );
		foreach ( $books as $id => $row ) {
			fputcsv( $out, array( get_the_title( $id ) ? get_the_title( $id ) : "#{$id}", $row['opens'], $row['uniques'], $row['completes'], $row['downloads'], $row['avg_max_page'] ) );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Read-only stats box on the flipbook edit screen (editors can see it).
	 */
	public function add_stats_box() {
		add_meta_box(
			'bindr-stats',
			__( 'Reading Statistics (30 days)', 'wp-bindr' ),
			array( $this, 'render_stats_box' ),
			Bindr_CPT::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render the per-book stats box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_stats_box( $post ) {
		if ( 'publish' !== $post->post_status ) {
			echo '<p>' . esc_html__( 'Statistics appear once the book is published.', 'wp-bindr' ) . '</p>';
			return;
		}

		$from   = gmdate( 'Y-m-d', time() - 29 * DAY_IN_SECONDS );
		$series = $this->analytics->get_timeseries( $from, gmdate( 'Y-m-d' ), $post->ID );

		$opens     = 0;
		$uniques   = 0;
		$completes = 0;
		foreach ( $series as $row ) {
			$opens     += $row['opens'];
			$uniques   += $row['uniques'];
			$completes += $row['completes'];
		}
		?>
		<ul>
			<li>
			<?php
			/* translators: %s: number of reads. */
			echo esc_html( sprintf( __( 'Reads: %s', 'wp-bindr' ), number_format_i18n( $opens ) ) );
			?>
			</li>
			<li>
			<?php
			/* translators: %s: number of unique readers. */
			echo esc_html( sprintf( __( 'Unique readers: %s', 'wp-bindr' ), number_format_i18n( $uniques ) ) );
			?>
			</li>
			<li>
			<?php
			/* translators: %s: number of completed reads. */
			echo esc_html( sprintf( __( 'Completed reads: %s', 'wp-bindr' ), number_format_i18n( $completes ) ) );
			?>
			</li>
		</ul>
		<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<p>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Bindr_CPT::POST_TYPE . '&page=bindr-analytics' ) ); ?>">
					<?php esc_html_e( 'Open the full analytics dashboard', 'wp-bindr' ); ?>
				</a>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Dashboard assets (SVG chart) on the analytics screen only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'bindr_book_page_bindr-analytics' !== $hook_suffix ) {
			return;
		}
		$asset = include BINDR_PLUGIN_DIR . 'build/admin-dashboard/index.asset.php';
		wp_enqueue_script(
			'bindr-admin-dashboard',
			BINDR_PLUGIN_URL . 'build/admin-dashboard/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_enqueue_style(
			'bindr-admin-dashboard',
			BINDR_PLUGIN_URL . 'build/admin-dashboard/index.css',
			array(),
			$asset['version']
		);
	}
}
