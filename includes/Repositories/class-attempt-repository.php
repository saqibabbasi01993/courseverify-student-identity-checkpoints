<?php
/**
 * Repository for the courseverify_attempts table.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Repositories;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Attempt_Repository
 */
class Attempt_Repository {

	/**
	 * Record a verification attempt outcome. Never accepts or stores the
	 * submitted answer itself.
	 *
	 * @param int    $user_id       WordPress user ID.
	 * @param int    $course_id     LearnDash course ID.
	 * @param int    $checkpoint_id Checkpoint row ID.
	 * @param string $result        One of: success, failure, locked, admin_unlock.
	 * @return int|false Inserted row ID, or false on failure.
	 */
	public function record( $user_id, $course_id, $checkpoint_id, $result ) {
		global $wpdb;

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'courseverify_attempts',
			array(
				'user_id'       => $user_id,
				'course_id'     => $course_id,
				'checkpoint_id' => $checkpoint_id,
				'result'        => $result,
				'attempted_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get recent attempts for a checkpoint (used on the admin student
	 * detail view — timestamps and results only, never answer content).
	 *
	 * @param int $checkpoint_id Checkpoint row ID.
	 * @param int $limit         Max rows to return. Default 20.
	 * @return array[]
	 */
	public function get_for_checkpoint( $checkpoint_id, $limit = 20 ) {
		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare( "SELECT id, result, attempted_at FROM {$wpdb->prefix}courseverify_attempts WHERE checkpoint_id = %d ORDER BY attempted_at DESC LIMIT %d", $checkpoint_id, absint( $limit ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return $results ? $results : array();
	}
}
