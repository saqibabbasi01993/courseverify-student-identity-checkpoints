<?php
/**
 * Contract for course-time providers.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Contracts;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface Time_Provider_Interface
 *
 * Any source of "how long has this student spent in this course" (Uncanny
 * Toolkit Pro today, potentially LearnDash-native or a manual provider
 * later) implements this. The Checkpoint Engine only ever talks to this
 * interface, never to a concrete provider.
 */
interface Time_Provider_Interface {

	/**
	 * Whether this provider is currently usable (plugin active, module
	 * enabled, etc.).
	 *
	 * @return bool
	 */
	public function is_available();

	/**
	 * Get the tracked course time for a student, in whole seconds.
	 *
	 * Must never return a formatted string — formatting is the caller's
	 * responsibility, not the provider's.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id LearnDash course ID.
	 * @return int|\WP_Error Course time in seconds, or WP_Error if the time
	 *                        could not be determined.
	 */
	public function get_course_time( $user_id, $course_id );
}
