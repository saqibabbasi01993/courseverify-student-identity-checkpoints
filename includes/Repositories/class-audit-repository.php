<?php
/**
 * Repository for the courseverify_audit table.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Repositories;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Audit_Repository
 */
class Audit_Repository {

	/**
	 * Record an audit event.
	 *
	 * @param array $data {
	 *     @type int    $user_id        Student user ID.
	 *     @type int    $course_id      Course ID.
	 *     @type string $checkpoint_key Optional checkpoint key.
	 *     @type string $event_type     Event type slug.
	 *     @type int    $actor_user_id  Who performed the action (0 = system).
	 *     @type string $message        Optional human-readable note.
	 * }
	 * @return int|false Inserted row ID, or false on failure.
	 */
	public function record( array $data ) {
		global $wpdb;

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'courseverify_audit',
			array(
				'user_id'        => absint( $data['user_id'] ?? 0 ),
				'course_id'      => absint( $data['course_id'] ?? 0 ),
				'checkpoint_key' => isset( $data['checkpoint_key'] ) ? sanitize_key( $data['checkpoint_key'] ) : null,
				'event_type'     => sanitize_key( $data['event_type'] ?? '' ),
				'actor_user_id'  => absint( $data['actor_user_id'] ?? 0 ),
				'message'        => isset( $data['message'] ) ? sanitize_textarea_field( $data['message'] ) : null,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Paginated, filterable audit log listing for the admin Logs screen.
	 *
	 * Every combination of the three optional filters (course_id, user_id,
	 * event_type) is written as its own explicit, fully literal
	 * $wpdb->prepare() call rather than assembled through an intermediate
	 * SQL-fragment variable — building SQL in a variable and preparing it
	 * later is flagged by static analysis (and is genuinely harder to
	 * audit) even when every value is placeholder-bound, so each branch is
	 * spelled out instead.
	 *
	 * @param array $args {
	 *     @type int    $course_id  Optional course filter.
	 *     @type int    $user_id    Optional student filter.
	 *     @type string $event_type Optional event-type filter.
	 *     @type int    $per_page   Rows per page. Default 20.
	 *     @type int    $page       1-indexed page number. Default 1.
	 * }
	 * @return array[]
	 */
	public function get_filtered( array $args = array() ) {
		global $wpdb;

		$per_page = isset( $args['per_page'] ) ? max( 1, absint( $args['per_page'] ) ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$course_id  = ! empty( $args['course_id'] ) ? absint( $args['course_id'] ) : 0;
		$user_id    = ! empty( $args['user_id'] ) ? absint( $args['user_id'] ) : 0;
		$event_type = ! empty( $args['event_type'] ) ? sanitize_key( $args['event_type'] ) : '';

		if ( $course_id && $user_id && $event_type ) {
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}courseverify_audit WHERE course_id = %d AND user_id = %d AND event_type = %s ORDER BY created_at DESC LIMIT %d OFFSET %d", $course_id, $user_id, $event_type, $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		} elseif ( $course_id && $user_id ) {
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}courseverify_audit WHERE course_id = %d AND user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", $course_id, $user_id, $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		} elseif ( $course_id && $event_type ) {
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}courseverify_audit WHERE course_id = %d AND event_type = %s ORDER BY created_at DESC LIMIT %d OFFSET %d", $course_id, $event_type, $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		} elseif ( $user_id && $event_type ) {
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}courseverify_audit WHERE user_id = %d AND event_type = %s ORDER BY created_at DESC LIMIT %d OFFSET %d", $user_id, $event_type, $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		} elseif ( $course_id ) {
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}courseverify_audit WHERE course_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", $course_id, $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		} elseif ( $user_id ) {
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}courseverify_audit WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", $user_id, $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		} elseif ( $event_type ) {
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}courseverify_audit WHERE event_type = %s ORDER BY created_at DESC LIMIT %d OFFSET %d", $event_type, $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}courseverify_audit ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		return $results ? $results : array();
	}

	/**
	 * Delete audit rows older than a cutoff date, in bounded batches so a
	 * large backlog can't time out a single request. Intended to be called
	 * repeatedly from a scheduled cron event (added when log-retention
	 * settings ship) until it reports zero rows deleted.
	 *
	 * @param string $cutoff_mysql_datetime Rows older than this are deleted.
	 * @param int    $batch_size            Max rows deleted per call. Default 500.
	 * @return int Number of rows deleted in this call.
	 */
	public function delete_older_than( $cutoff_mysql_datetime, $batch_size = 500 ) {
		global $wpdb;

		return (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}courseverify_audit WHERE created_at < %s LIMIT %d",
				$cutoff_mysql_datetime,
				absint( $batch_size )
			)
		);
	}
}
