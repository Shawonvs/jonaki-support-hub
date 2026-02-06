<?php
/**
 * Plugin activator class.
 *
 * @package JonakiSupportHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation routines.
 */
class JSH_Activator {

	/**
	 * Activate plugin and create required tables.
	 *
	 * @return void
	 */
	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$tickets_table    = $wpdb->prefix . 'jsh_tickets';
		$messages_table   = $wpdb->prefix . 'jsh_messages';
		$kb_answers_table = $wpdb->prefix . 'jsh_kb_answers';

		$sql = array();

		$sql[] = "CREATE TABLE {$tickets_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ticket_number varchar(50) NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			subject varchar(255) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			priority varchar(20) NOT NULL DEFAULT 'medium',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY ticket_number (ticket_number),
			KEY status (status),
			KEY user_id (user_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$messages_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ticket_id bigint(20) unsigned NOT NULL,
			sender_id bigint(20) unsigned DEFAULT NULL,
			sender_type varchar(20) NOT NULL DEFAULT 'user',
			message longtext NOT NULL,
			is_internal tinyint(1) NOT NULL DEFAULT 0,
			rating tinyint(1) unsigned DEFAULT NULL,
			rated_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY ticket_id (ticket_id),
			KEY sender_id (sender_id),
			KEY sender_type (sender_type)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$kb_answers_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_message_id bigint(20) unsigned DEFAULT NULL,
			question varchar(255) NOT NULL,
			answer longtext NOT NULL,
			category varchar(100) DEFAULT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			rating_avg decimal(5,2) NOT NULL DEFAULT 0.00,
			rating_count bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY source_message_id (source_message_id),
			KEY is_active (is_active),
			KEY category (category)
		) {$charset_collate};";

		foreach ( $sql as $query ) {
			dbDelta( $query );
		}

		if ( false === get_option( 'jsh_cleanup_on_uninstall', false ) ) {
			add_option( 'jsh_cleanup_on_uninstall', 0 );
		}

		if ( false === get_option( 'jsh_api_key', false ) ) {
			add_option( 'jsh_api_key', wp_generate_password( 32, false, false ) );
		}

		if ( false === get_option( 'jsh_n8n_webhook_url', false ) ) {
			add_option( 'jsh_n8n_webhook_url', '' );
		}
	}
}
