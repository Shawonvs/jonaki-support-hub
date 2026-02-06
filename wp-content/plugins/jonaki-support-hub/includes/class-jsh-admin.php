<?php
/**
 * Admin UI for Jonaki Support Hub.
 *
 * @package JonakiSupportHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WP admin pages.
 */
class JSH_Admin {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_jsh_send_reply', array( __CLASS__, 'handle_send_reply' ) );
	}

	/**
	 * Register admin menu and subpages.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_menu_page(
			'Support Hub',
			'Support Hub',
			'manage_options',
			'jsh-tickets',
			array( __CLASS__, 'render_tickets_page' ),
			'dashicons-sos',
			56
		);

		add_submenu_page(
			'jsh-tickets',
			'Tickets',
			'Tickets',
			'manage_options',
			'jsh-tickets',
			array( __CLASS__, 'render_tickets_page' )
		);

		add_submenu_page(
			'jsh-tickets',
			'KB',
			'KB',
			'manage_options',
			'jsh-kb',
			array( __CLASS__, 'render_kb_page' )
		);

		add_submenu_page(
			null,
			'Ticket Detail',
			'Ticket Detail',
			'manage_options',
			'jsh-ticket-detail',
			array( __CLASS__, 'render_ticket_detail_page' )
		);
	}

	/**
	 * Render tickets list page.
	 *
	 * @return void
	 */
	public static function render_tickets_page() {
		global $wpdb;

		$status = sanitize_key( (string) filter_input( INPUT_GET, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );

		$where_clause = '';
		$params       = array();
		if ( '' !== $status ) {
			$where_clause = 'WHERE t.status = %s';
			$params[]     = $status;
		}

		$sql = "SELECT t.id, t.ticket_number, t.subject, t.status, t.priority, t.created_at,
			m.message AS latest_message, m.sender_type AS latest_sender_type, m.created_at AS latest_message_at
		FROM {$wpdb->prefix}jsh_tickets t
		LEFT JOIN {$wpdb->prefix}jsh_messages m ON m.id = (
			SELECT id FROM {$wpdb->prefix}jsh_messages WHERE ticket_id = t.id ORDER BY id DESC LIMIT 1
		)
		{$where_clause}
		ORDER BY t.id DESC";

		$tickets = empty( $params ) ? $wpdb->get_results( $sql, ARRAY_A ) : $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		$statuses = array( 'open', 'pending', 'resolved', 'closed' );
		?>
		<div class="wrap">
			<h1>Support Hub - Tickets</h1>
			<form method="get" style="margin:10px 0 20px;">
				<input type="hidden" name="page" value="jsh-tickets" />
				<label for="jsh-status-filter">Filter by status:</label>
				<select id="jsh-status-filter" name="status">
					<option value="">All</option>
					<?php foreach ( $statuses as $status_item ) : ?>
						<option value="<?php echo esc_attr( $status_item ); ?>" <?php selected( $status, $status_item ); ?>>
							<?php echo esc_html( ucfirst( $status_item ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button class="button button-primary" type="submit">Apply</button>
			</form>

			<table class="widefat striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>Ticket</th>
						<th>Subject</th>
						<th>Status</th>
						<th>Priority</th>
						<th>Latest Message Preview</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $tickets ) ) : ?>
					<tr><td colspan="7">No tickets found.</td></tr>
				<?php else : ?>
					<?php foreach ( $tickets as $ticket ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $ticket['id'] ); ?></td>
						<td><?php echo esc_html( $ticket['ticket_number'] ); ?></td>
						<td><?php echo esc_html( $ticket['subject'] ); ?></td>
						<td><?php echo esc_html( ucfirst( $ticket['status'] ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $ticket['priority'] ) ); ?></td>
						<td>
							<?php
							$preview = isset( $ticket['latest_message'] ) ? wp_trim_words( wp_strip_all_tags( (string) $ticket['latest_message'] ), 14 ) : '-';
							echo esc_html( '[' . strtoupper( (string) ( $ticket['latest_sender_type'] ?: 'n/a' ) ) . '] ' . $preview );
							?>
						</td>
						<td>
							<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=jsh-ticket-detail&ticket_id=' . absint( $ticket['id'] ) ) ); ?>">View</a>
						</td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render KB and settings page.
	 *
	 * @return void
	 */
	public static function render_kb_page() {
		if ( isset( $_POST['jsh_save_settings'] ) ) {
			check_admin_referer( 'jsh_kb_settings' );
			update_option( 'jsh_n8n_webhook_url', esc_url_raw( (string) wp_unslash( $_POST['jsh_n8n_webhook_url'] ?? '' ) ) );
			echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
		}

		$webhook_url = (string) get_option( 'jsh_n8n_webhook_url', '' );
		$api_key     = (string) get_option( 'jsh_api_key', '' );
		?>
		<div class="wrap">
			<h1>Support Hub - KB</h1>
			<p>Configure integration and use REST API key for external apps.</p>
			<form method="post">
				<?php wp_nonce_field( 'jsh_kb_settings' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="jsh_n8n_webhook_url">n8n Webhook URL</label></th>
						<td><input class="regular-text" type="url" id="jsh_n8n_webhook_url" name="jsh_n8n_webhook_url" value="<?php echo esc_attr( $webhook_url ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row">API Key (X-JSH-KEY)</th>
						<td><code><?php echo esc_html( $api_key ); ?></code></td>
					</tr>
				</table>
				<p><button class="button button-primary" type="submit" name="jsh_save_settings" value="1">Save Settings</button></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render ticket detail page.
	 *
	 * @return void
	 */
	public static function render_ticket_detail_page() {
		global $wpdb;

		$ticket_id = absint( filter_input( INPUT_GET, 'ticket_id', FILTER_SANITIZE_NUMBER_INT ) );
		if ( ! $ticket_id ) {
			echo '<div class="wrap"><h1>Ticket Detail</h1><p>Invalid ticket.</p></div>';
			return;
		}

		$ticket = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}jsh_tickets WHERE id = %d", $ticket_id ), ARRAY_A );
		if ( ! $ticket ) {
			echo '<div class="wrap"><h1>Ticket Detail</h1><p>Ticket not found.</p></div>';
			return;
		}

		$messages = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}jsh_messages WHERE ticket_id = %d ORDER BY id ASC", $ticket_id ), ARRAY_A );
		$notice   = sanitize_key( (string) filter_input( INPUT_GET, 'jsh_notice', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		?>
		<div class="wrap">
			<h1>Ticket #<?php echo esc_html( $ticket['ticket_number'] ); ?></h1>
			<p><strong>Subject:</strong> <?php echo esc_html( $ticket['subject'] ); ?> | <strong>Status:</strong> <?php echo esc_html( ucfirst( $ticket['status'] ) ); ?></p>

			<?php if ( 'sent' === $notice ) : ?>
				<div class="notice notice-success"><p>Reply sent and logged successfully.</p></div>
			<?php elseif ( 'failed' === $notice ) : ?>
				<div class="notice notice-error"><p>Failed to send reply. Please check webhook and API key settings.</p></div>
			<?php endif; ?>

			<h2>Thread</h2>
			<div style="background:#fff;border:1px solid #ccd0d4;padding:15px;max-height:500px;overflow:auto;">
				<?php if ( empty( $messages ) ) : ?>
					<p>No messages found.</p>
				<?php else : ?>
					<?php foreach ( $messages as $message ) : ?>
						<div style="border-bottom:1px solid #eee;padding:8px 0;">
							<p style="margin:0 0 4px;"><strong><?php echo esc_html( strtoupper( (string) ( $message['sender_type'] ?: 'user' ) ) ); ?></strong> <span style="color:#666;">at <?php echo esc_html( $message['created_at'] ); ?></span></p>
							<div><?php echo wp_kses_post( wpautop( $message['message'] ) ); ?></div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<h2>Send Reply</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'jsh_send_reply_' . $ticket_id ); ?>
				<input type="hidden" name="action" value="jsh_send_reply" />
				<input type="hidden" name="ticket_id" value="<?php echo esc_attr( (string) $ticket_id ); ?>" />
				<textarea name="message" rows="5" style="width:100%;max-width:900px;" required></textarea>
				<p><button class="button button-primary" type="submit">Send</button></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle sending reply to n8n and internal REST logging.
	 *
	 * @return void
	 */
	public static function handle_send_reply() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized access.' );
		}

		$ticket_id = absint( $_POST['ticket_id'] ?? 0 );
		check_admin_referer( 'jsh_send_reply_' . $ticket_id );

		$message = wp_kses_post( (string) wp_unslash( $_POST['message'] ?? '' ) );
		if ( ! $ticket_id || '' === trim( wp_strip_all_tags( $message ) ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=jsh-ticket-detail&ticket_id=' . $ticket_id . '&jsh_notice=failed' ) );
			exit;
		}

		$webhook_url = (string) get_option( 'jsh_n8n_webhook_url', '' );
		$success     = true;

		if ( '' !== $webhook_url ) {
			$http_response = wp_remote_post(
				$webhook_url,
				array(
					'timeout' => 20,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode(
						array(
							'ticket_id'   => $ticket_id,
							'message'     => wp_strip_all_tags( $message ),
							'agent_id'    => get_current_user_id(),
							'agent_email' => wp_get_current_user()->user_email,
						)
					),
				)
			);
			if ( is_wp_error( $http_response ) || wp_remote_retrieve_response_code( $http_response ) >= 300 ) {
				$success = false;
			}
		}

		$rest_request = new WP_REST_Request( 'POST', '/jsh/v1/messages' );
		$rest_request->set_header( 'X-JSH-KEY', (string) get_option( 'jsh_api_key', '' ) );
		$rest_request->set_body_params(
			array(
				'ticket_id'   => $ticket_id,
				'message'     => $message,
				'sender_id'   => get_current_user_id(),
				'sender_type' => 'agent',
			)
		);
		$rest_response = rest_do_request( $rest_request );
		if ( $rest_response->is_error() || 201 !== $rest_response->get_status() ) {
			$success = false;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=jsh-ticket-detail&ticket_id=' . $ticket_id . '&jsh_notice=' . ( $success ? 'sent' : 'failed' ) ) );
		exit;
	}
}
