<?php
/**
 * LearnDash compatibility layer.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Integrations;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LearnDash
 *
 * COMPATIBILITY NOTE:
 * These calls use LearnDash's public template/API functions. In practice
 * (confirmed against a real installation), not every function assumed to
 * be universally available actually is — learndash_is_user_in_course()
 * specifically was undefined on one tested site. Every call in this class
 * is now guarded with function_exists() and falls back through
 * alternative LearnDash functions rather than assuming any single one
 * exists; nothing here should ever fatal regardless of LearnDash version
 * or build. If you hit a case where CourseVerify silently treats a
 * genuinely-enrolled student as not enrolled, check your WP_DEBUG log for
 * the diagnostic message this class writes when no known function could
 * be found, and tell me — I'll add that specific function to the chain.
 *
 * No LearnDash core file, table, or option is ever written to by this
 * class — read-only.
 */
class LearnDash {

	/**
	 * Whether LearnDash is active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return defined( 'LEARNDASH_VERSION' ) && function_exists( 'learndash_get_course_id' );
	}

	/**
	 * Get the course ID for a given post (course, lesson, topic, or quiz).
	 *
	 * @param int $post_id Post ID.
	 * @return int Course ID, or 0 if not part of a course.
	 */
	public static function get_course_id( $post_id ) {
		if ( ! self::is_active() ) {
			return 0;
		}

		return (int) learndash_get_course_id( $post_id );
	}

	/**
	 * Whether a user is enrolled in a course.
	 *
	 * COMPATIBILITY: learndash_is_user_in_course() turned out not to be
	 * defined on at least one real installation (reported error), despite
	 * being long-documented — likely a version/build difference. Rather
	 * than assume any single function is always present, this tries each
	 * known LearnDash enrollment-check function in turn and never fatals
	 * if none are available.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id LearnDash course ID.
	 * @return bool
	 */
	public static function is_user_enrolled( $user_id, $course_id ) {
		if ( ! self::is_active() ) {
			return false;
		}

		if ( function_exists( 'learndash_is_user_in_course' ) ) {
			return (bool) learndash_is_user_in_course( $user_id, $course_id );
		}

		if ( function_exists( 'sfwd_lms_has_access' ) ) {
			return (bool) sfwd_lms_has_access( $course_id, $user_id );
		}

		if ( function_exists( 'learndash_user_get_enrolled_courses' ) ) {
			$enrolled = learndash_user_get_enrolled_courses( $user_id );
			return is_array( $enrolled ) && in_array( (int) $course_id, array_map( 'absint', $enrolled ), true );
		}

		if ( function_exists( 'ld_course_check_user_access' ) ) {
			return (bool) ld_course_check_user_access( $course_id, $user_id );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'CourseVerify: no known LearnDash enrollment-check function was found; treating user ' . $user_id . ' as not enrolled in course ' . $course_id . ' for CourseVerify purposes only (LearnDash\'s own access control is unaffected).' );
		}

		return false;
	}

	/**
	 * Whether a user currently has LearnDash-level access to a piece of
	 * content (lesson/topic/quiz). This is LearnDash's own access check
	 * (drip, prerequisites, etc.) and is independent of CourseVerify's
	 * checkpoint gate — CourseVerify only ever adds an additional gate on
	 * top of a "yes" from LearnDash, never overrides a LearnDash "no".
	 *
	 * @param int $post_id Content post ID.
	 * @param int $user_id WordPress user ID.
	 * @return bool
	 */
	public static function has_learndash_access( $post_id, $user_id ) {
		if ( ! self::is_active() || ! function_exists( 'sfwd_lms_has_access' ) ) {
			return false;
		}

		return (bool) sfwd_lms_has_access( $post_id, $user_id );
	}

	/**
	 * Get all quiz post IDs belonging to a course, for populating the
	 * "final exam quiz" course-settings dropdown. The administrator picks
	 * explicitly — CourseVerify never assumes the last quiz is the final
	 * exam.
	 *
	 * COMPATIBILITY: learndash_get_course_steps_by_type() turned out not to
	 * return usable results on at least one real installation (reported:
	 * empty dropdown). Rather than depend on that single function, this
	 * tries it first, then falls back to directly querying `sfwd-quiz`
	 * posts and filtering by learndash_get_course_id() — which is the same
	 * course-association function used everywhere else in this class and
	 * has proven reliable — so the dropdown still populates even where the
	 * steps function doesn't cooperate.
	 *
	 * @param int $course_id LearnDash course ID.
	 * @return int[]
	 */
	public static function get_course_quiz_ids( $course_id ) {
		if ( ! self::is_active() ) {
			return array();
		}

		$quiz_ids = array();

		if ( function_exists( 'learndash_get_course_steps_by_type' ) ) {
			$quizzes = learndash_get_course_steps_by_type( $course_id, 'sfwd-quiz' );

			if ( ! empty( $quizzes ) && is_array( $quizzes ) ) {
				$quiz_ids = array_map( 'absint', array_keys( $quizzes ) );
			}
		}

		if ( ! empty( $quiz_ids ) ) {
			return $quiz_ids;
		}

		// Fallback: query every quiz post directly and keep the ones whose
		// resolved course matches, using the same get_course_id() logic
		// the rest of this class already relies on.
		$all_quizzes = get_posts(
			array(
				'post_type'      => 'sfwd-quiz',
				'post_status'    => array( 'publish', 'private', 'draft' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		foreach ( (array) $all_quizzes as $quiz_id ) {
			if ( (int) self::get_course_id( $quiz_id ) === (int) $course_id ) {
				$quiz_ids[] = (int) $quiz_id;
			}
		}

		if ( empty( $quiz_ids ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'CourseVerify: no quizzes could be associated with course ' . $course_id . ' via either learndash_get_course_steps_by_type() or a direct sfwd-quiz query.' );
		}

		return $quiz_ids;
	}

	/**
	 * Determine the post type of a given post ID, restricted to the
	 * LearnDash content types CourseVerify cares about.
	 *
	 * @param int $post_id Post ID.
	 * @return string One of 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic',
	 *                'sfwd-quiz', or '' if not a recognized LearnDash type.
	 */
	public static function get_content_type( $post_id ) {
		$type = get_post_type( $post_id );

		$learndash_types = array( 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' );

		return in_array( $type, $learndash_types, true ) ? $type : '';
	}

	/**
	 * Whether CourseVerify is enabled for a given course, per that course's
	 * own settings (set by the admin, not assumed).
	 *
	 * @param int $course_id LearnDash course ID.
	 * @return bool
	 */
	public static function is_course_protected( $course_id ) {
		$enabled = get_post_meta( $course_id, '_courseverify_enabled', true );

		return '1' === $enabled;
	}
}
