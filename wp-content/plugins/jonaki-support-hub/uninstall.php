<?php
/**
 * Uninstall routines for Jonaki Support Hub.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'jsh_api_key' );
delete_option( 'jsh_n8n_webhook_url' );

$cleanup_enabled = (bool) get_option( 'jsh_cleanup_on_uninstall', 0 );

if ( ! $cleanup_enabled ) {
	delete_option( 'jsh_cleanup_on_uninstall' );
	return;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'jsh_messages',
	$wpdb->prefix . 'jsh_tickets',
	$wpdb->prefix . 'jsh_kb_answers',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

delete_option( 'jsh_cleanup_on_uninstall' );
