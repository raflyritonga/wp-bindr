<?php
/**
 * Uninstall handler. Respects the "delete data on uninstall" setting —
 * by default all flipbooks, settings, and analytics are kept.
 *
 * @package Bindr
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$bindr_settings = (array) get_option( 'bindr_settings', array() );
if ( empty( $bindr_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;

// Custom tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bindr_events" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bindr_daily" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Flipbook posts and their meta.
$bindr_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'bindr_book' )
);
foreach ( $bindr_ids as $bindr_id ) {
	wp_delete_post( (int) $bindr_id, true );
}

// Options and transients.
delete_option( 'bindr_settings' );
delete_option( 'bindr_db_version' );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_bindr\_rl\_%' OR option_name LIKE '\_transient\_timeout\_bindr\_rl\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
