<?php
/**
 * REST API routes for Jonaki Support Hub.
 *
 * @package JonakiSupportHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles REST API route registration and callbacks.
 */
class JSH_REST_API {

	/**
	 * Register all REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'jsh/v1',
			'/tickets',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_ticket' ),
					'permission_callback' => array( __CLASS__, 'authorize_request' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_tickets' ),
					'permission_callback' => array( __CLASS__, 'authorize_request' ),
				),
			)
		);

		register_rest_route(
			'jsh/v1',
			'/tickets/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_ticket' ),
				'permission_callback' => array( __CLASS__, 'authorize_request' ),
			)
		);

		register_rest_route(
			'jsh/v1',
			'/messages',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_message' ),
				'permission_callback' => array( __CLASS__, 'authorize_request' ),
			)
		);

		register_rest_route(
			'jsh/v1',
			'/messages/(?P<id>\d+)/rate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rate_message' ),
				'permission_callback' => array( __CLASS__, 'authorize_request' ),
			)
		);

		register_rest_route(
			'jsh/v1',
			'/kb/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'search_kb' ),
				'permission_callback' => array( __CLASS__, 'authorize_request' ),
			)
		);
	}

	/**
	 * Authenticate request using X-JSH-KEY header.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return true|WP_Error
	 */
	public static function authorize_request( $request ) {
		$stored_key = (string) get_option( 'jsh_api_key', '' );
		$header_key = (string) $request->get_header( 'X-JSH-KEY' );

		if ( '' === $stored_key ) {
			return new WP_Error( 'jsh_api_key_not_configured', 'API key is not configured.', array( 'status' => 503 ) );
		}

		if ( '' === $header_key || ! hash_equals( $stored_key, $header_key ) ) {
			return new WP_Error( 'jsh_unauthorized', 'Invalid API key.', array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Create a ticket.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function create_ticket( $request ) {
		global $wpdb;

		$subject = sanitize_text_field( (string) $request->get_param( 'subject' ) );
		if ( '' === $subject ) {
			return new WP_REST_Response( array( 'error' => 'Subject is required.' ), 400 );
		}

		$ticket_number = 'JSH-' . strtoupper( wp_generate_password( 10, false, false ) );
		$data          = array(
			'ticket_number' => $ticket_number,
			'user_id'       => absint( $request->get_param( 'user_id' ) ),
			'subject'       => $subject,
			'status'        => sanitize_text_field( (string) $request->get_param( 'status' ) ?: 'open' ),
			'priority'      => sanitize_text_field( (string) $request->get_param( 'priority' ) ?: 'medium' ),
		);

		$inserted = $wpdb->insert( $wpdb->prefix . 'jsh_tickets', $data, array( '%s', '%d', '%s', '%s', '%s' ) );
		if ( false === $inserted ) {
			return new WP_REST_Response( array( 'error' => 'Failed to create ticket.' ), 500 );
		}

		return new WP_REST_Response(
			array(
				'id'            => (int) $wpdb->insert_id,
				'ticket_number' => $ticket_number,
			),
			201
		);
	}

	/**
	 * Create a message.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function create_message( $request ) {
		global $wpdb;

		$ticket_id    = absint( $request->get_param( 'ticket_id' ) );
		$message      = wp_kses_post( (string) $request->get_param( 'message' ) );
		$sender_type  = sanitize_key( (string) $request->get_param( 'sender_type' ) ?: 'user' );
		$sender_types = array( 'user', 'ai', 'agent' );

		if ( ! in_array( $sender_type, $sender_types, true ) ) {
			$sender_type = 'user';
		}

		if ( ! $ticket_id || '' === trim( wp_strip_all_tags( $message ) ) ) {
			return new WP_REST_Response( array( 'error' => 'ticket_id and message are required.' ), 400 );
		}

		$ticket_exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}jsh_tickets WHERE id = %d", $ticket_id ) );
		if ( ! $ticket_exists ) {
			return new WP_REST_Response( array( 'error' => 'Ticket not found.' ), 404 );
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'jsh_messages',
			array(
				'ticket_id'    => $ticket_id,
				'sender_id'    => absint( $request->get_param( 'sender_id' ) ),
				'sender_type'  => $sender_type,
				'message'      => $message,
				'is_internal'  => (int) ! empty( $request->get_param( 'is_internal' ) ),
			),
			array( '%d', '%d', '%s', '%s', '%d' )
		);

		if ( false === $inserted ) {
			return new WP_REST_Response( array( 'error' => 'Failed to create message.' ), 500 );
		}

		return new WP_REST_Response( array( 'id' => (int) $wpdb->insert_id ), 201 );
	}

	/**
	 * List tickets.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function list_tickets( $request ) {
		global $wpdb;

		$limit  = min( 100, max( 1, absint( $request->get_param( 'limit' ) ?: 20 ) ) );
		$offset = max( 0, absint( $request->get_param( 'offset' ) ?: 0 ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, ticket_number, user_id, subject, status, priority, created_at, updated_at
				FROM {$wpdb->prefix}jsh_tickets
				ORDER BY id DESC
				LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);

		return new WP_REST_Response( array( 'tickets' => $rows ), 200 );
	}

	/**
	 * Get one ticket with messages.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function get_ticket( $request ) {
		global $wpdb;

		$id     = absint( $request['id'] );
		$ticket = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, ticket_number, user_id, subject, status, priority, created_at, updated_at
				FROM {$wpdb->prefix}jsh_tickets WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		if ( ! $ticket ) {
			return new WP_REST_Response( array( 'error' => 'Ticket not found.' ), 404 );
		}

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, ticket_id, sender_id, sender_type, message, is_internal, rating, created_at, rated_at
				FROM {$wpdb->prefix}jsh_messages WHERE ticket_id = %d ORDER BY id ASC",
				$id
			),
			ARRAY_A
		);

		$ticket['messages'] = $messages;

		return new WP_REST_Response( $ticket, 200 );
	}

	/**
	 * Rate a message.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function rate_message( $request ) {
		global $wpdb;

		$id     = absint( $request['id'] );
		$rating = absint( $request->get_param( 'rating' ) );
		if ( $rating < 1 || $rating > 5 ) {
			return new WP_REST_Response( array( 'error' => 'rating must be between 1 and 5.' ), 400 );
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'jsh_messages',
			array(
				'rating'   => $rating,
				'rated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_REST_Response( array( 'error' => 'Failed to rate message.' ), 500 );
		}

		if ( 0 === $updated ) {
			return new WP_REST_Response( array( 'error' => 'Message not found.' ), 404 );
		}

		if ( $rating >= 4 ) {
			$message_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT m.id, m.message, t.subject
					FROM {$wpdb->prefix}jsh_messages m
					INNER JOIN {$wpdb->prefix}jsh_tickets t ON t.id = m.ticket_id
					WHERE m.id = %d",
					$id
				),
				ARRAY_A
			);

			if ( $message_row ) {
				$kb_table = $wpdb->prefix . 'jsh_kb_answers';
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$kb_table} (source_message_id, question, answer, category, is_active, rating_avg, rating_count, created_at, updated_at)
						VALUES (%d, %s, %s, %s, 1, %f, 1, %s, %s)
						ON DUPLICATE KEY UPDATE
						question = VALUES(question),
						answer = VALUES(answer),
						is_active = 1,
						rating_avg = ((rating_avg * rating_count) + VALUES(rating_avg)) / (rating_count + 1),
						rating_count = rating_count + 1,
						updated_at = VALUES(updated_at)",
						(int) $message_row['id'],
						sanitize_text_field( (string) $message_row['subject'] ),
						wp_strip_all_tags( (string) $message_row['message'] ),
						'auto',
						(float) $rating,
						current_time( 'mysql' ),
						current_time( 'mysql' )
					)
				);
			}
		}

		return new WP_REST_Response( array( 'message' => 'Message rated successfully.' ), 200 );
	}

	/**
	 * Search KB answers.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function search_kb( $request ) {
		global $wpdb;

		$query = sanitize_text_field( (string) $request->get_param( 'q' ) );
		$limit = min( 50, max( 1, absint( $request->get_param( 'limit' ) ?: 10 ) ) );

		if ( '' === $query ) {
			return new WP_REST_Response( array( 'error' => 'q is required.' ), 400 );
		}

		$like = '%' . $wpdb->esc_like( $query ) . '%';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, source_message_id, question, answer, category, is_active, rating_avg, rating_count, created_at, updated_at,
				((CASE WHEN question LIKE %s THEN 2 ELSE 0 END) + (CASE WHEN answer LIKE %s THEN 1 ELSE 0 END) + rating_avg) AS rank_score
				FROM {$wpdb->prefix}jsh_kb_answers
				WHERE is_active = 1 AND (question LIKE %s OR answer LIKE %s)
				ORDER BY rank_score DESC, rating_avg DESC, id DESC
				LIMIT %d",
				$like,
				$like,
				$like,
				$like,
				$limit
			),
			ARRAY_A
		);

		return new WP_REST_Response( array( 'results' => $results ), 200 );
	}
}
