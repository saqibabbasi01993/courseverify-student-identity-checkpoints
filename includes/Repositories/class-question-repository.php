<?php
/**
 * Repository for the courseverify_questions table.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Repositories;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Question_Repository
 *
 * Pure data access — no hashing, no validation, no course-access logic.
 * Callers (Security\Question_Manager) are responsible for all of that.
 */
class Question_Repository {

	/**
	 * Get all configured questions for a student in a course.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id LearnDash course ID.
	 * @return array[] Array of row objects cast to arrays.
	 */
	public function get_for_user_course( $user_id, $course_id ) {
		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare( "SELECT id, question_key, question_text, answer_hash FROM {$wpdb->prefix}courseverify_questions WHERE user_id = %d AND course_id = %d ORDER BY id ASC", $user_id, $course_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return $results ? $results : array();
	}

	/**
	 * Whether the student has already completed identity setup for a
	 * course (i.e. has at least one configured question).
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id LearnDash course ID.
	 * @return bool
	 */
	public function has_configured_questions( $user_id, $course_id ) {
		global $wpdb;

		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$wpdb->prefix}courseverify_questions WHERE user_id = %d AND course_id = %d", $user_id, $course_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return $count > 0;
	}

	/**
	 * Insert a single question/answer-hash pair.
	 *
	 * @param int    $user_id       WordPress user ID.
	 * @param int    $course_id     LearnDash course ID.
	 * @param string $question_key  Short key identifying the question.
	 * @param string $question_text Question text shown to the student.
	 * @param string $answer_hash   Hashed answer (wp_hash_password() output).
	 * @return int|false Inserted row ID, or false on failure.
	 */
	public function insert( $user_id, $course_id, $question_key, $question_text, $answer_hash ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'courseverify_questions',
			array(
				'user_id'       => $user_id,
				'course_id'     => $course_id,
				'question_key'  => $question_key,
				'question_text' => $question_text,
				'answer_hash'   => $answer_hash,
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Delete all configured questions for a student in a course (used on
	 * admin-initiated question reset).
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id LearnDash course ID.
	 * @return int|false Number of rows deleted, or false on failure.
	 */
	public function delete_for_user_course( $user_id, $course_id ) {
		global $wpdb;

		return $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'courseverify_questions',
			array(
				'user_id'   => $user_id,
				'course_id' => $course_id,
			),
			array( '%d', '%d' )
		);
	}
}
