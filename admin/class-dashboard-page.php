<?php
/**
 * Admin dashboard.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Admin;

use CourseVerify\Security;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Dashboard_Page
 *
 * Read-only summary stats. Every query here is static (no request input
 * anywhere in it), so there is nothing to bind through $wpdb->prepare() —
 * the only variable part is the table name itself, which comes only from
 * $wpdb->prefix and is written directly into each query string rather
 * than through an intermediate variable.
 */
class Dashboard_Page {

	/**
	 * Render the dashboard screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! Security::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'courseverify-student-identity-checkpoints' ) );
		}

		$stats = $this->get_stats();

		include COURSEVERIFY_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Gather dashboard statistics.
	 *
	 * @return array
	 */
	private function get_stats() {
		global $wpdb;

		$protected_courses = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT meta.post_id) FROM {$wpdb->postmeta} AS meta WHERE meta.meta_key = '_courseverify_enabled' AND meta.meta_value = '1'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$students_verified = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}courseverify_checkpoints WHERE status = 'passed'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$pending_verifications = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$wpdb->prefix}courseverify_checkpoints WHERE status IN ('required','in_progress')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$locked_students = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}courseverify_checkpoints WHERE status = 'locked'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$failed_attempts = (int) $wpdb->get_var( "SELECT SUM(attempts) FROM {$wpdb->prefix}courseverify_checkpoints" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array(
			'protected_courses'     => $protected_courses,
			'students_verified'     => $students_verified,
			'pending_verifications' => $pending_verifications,
			'locked_students'       => $locked_students,
			'failed_attempts'       => $failed_attempts,
		);
	}
}
