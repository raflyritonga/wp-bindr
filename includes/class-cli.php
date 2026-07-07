<?php
/**
 * WP-CLI commands.
 *
 * @package Bindr
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `wp bindr` commands.
 */
class Bindr_CLI {

	/**
	 * Register commands with WP-CLI.
	 *
	 * @param Bindr_Analytics $analytics Analytics instance.
	 */
	public static function register( Bindr_Analytics $analytics ) {
		WP_CLI::add_command(
			'bindr rollup',
			function () use ( $analytics ) {
				$analytics->run_rollup();
				WP_CLI::success( 'Rollup and pruning complete.' );
			},
			array( 'shortdesc' => 'Aggregate raw events into daily rollups and prune old events.' )
		);

		WP_CLI::add_command(
			'bindr prune',
			function () use ( $analytics ) {
				$analytics->prune();
				WP_CLI::success( 'Old events pruned.' );
			},
			array( 'shortdesc' => 'Delete raw analytics events past the retention window.' )
		);
	}
}
