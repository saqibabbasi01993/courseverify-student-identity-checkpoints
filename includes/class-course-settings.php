<?php
/**
 * Per-course CourseVerify settings.
 *
 * @package CourseVerify
 */

namespace CourseVerify;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Course_Settings
 *
 * Reads/writes course-level configuration as post meta on the LearnDash
 * course post. Falls back to the global courseverify_settings option where
 * a course hasn't overridden a value. All values are validated on write —
 * numeric, non-negative, unique, sorted, within reasonable bounds — per the
 * spec's course-settings validation requirements.
 */
class Course_Settings {

	const META_ENABLED             = '_courseverify_enabled';
	const META_QUESTION_COUNT      = '_courseverify_question_count';
	const META_CHECKPOINT_HOURS    = '_courseverify_checkpoint_hours';
	const META_MAX_ATTEMPTS        = '_courseverify_max_attempts';
	const META_LOCK_ON_FAILURE     = '_courseverify_lock_on_failure';
	const META_FINAL_EXAM_ENABLED  = '_courseverify_final_exam_enabled';
	const META_FINAL_EXAM_QUIZ_ID  = '_courseverify_final_exam_quiz_id';

	/**
	 * Whether CourseVerify is enabled for a course.
	 *
	 * @param int $course_id LearnDash course ID.
	 * @return bool
	 */
	public static function is_enabled( $course_id ) {
		return '1' === get_post_meta( $course_id, self::META_ENABLED, true );
	}

	/**
	 * Set whether CourseVerify is enabled for a course.
	 *
	 * @param int  $course_id LearnDash course ID.
	 * @param bool $enabled   Whether to enable.
	 * @return void
	 */
	public static function set_enabled( $course_id, $enabled ) {
		update_post_meta( $course_id, self::META_ENABLED, $enabled ? '1' : '0' );
	}

	/**
	 * Number of security questions required at setup.
	 *
	 * @param int $course_id LearnDash course ID.
	 * @return int
	 */
	public static function get_question_count( $course_id ) {
		$value = get_post_meta( $course_id, self::META_QUESTION_COUNT, true );

		if ( '' === $value ) {
			$global = self::get_global_defaults();
			return absint( $global['default_question_count'] );
		}

		return max( 1, absint( $value ) );
	}

	/**
	 * Checkpoint schedule, in hours from course start (e.g. [0,5,10,15,20,25]).
	 * Validated: numeric, non-negative, unique, sorted ascending, capped at
	 * a reasonable maximum to avoid pathological schedules.
	 *
	 * @param int $course_id LearnDash course ID.
	 * @return int[]
	 */
	public static function get_checkpoint_hours( $course_id ) {
		$stored = get_post_meta( $course_id, self::META_CHECKPOINT_HOURS, true );

		if ( ! is_array( $stored ) || empty( $stored ) ) {
			$global = self::get_global_defaults();
			$stored = $global['default_checkpoint_schedule_hrs'];
		}

		return self::sanitize_checkpoint_hours( $stored );
	}

	/**
	 * Validate and store a checkpoint schedule.
	 *
	 * @param int   $course_id LearnDash course ID.
	 * @param array $hours     Raw submitted hours.
	 * @return void
	 */
	public static function set_checkpoint_hours( $course_id, array $hours ) {
		update_post_meta( $course_id, self::META_CHECKPOINT_HOURS, self::sanitize_checkpoint_hours( $hours ) );
	}

	/**
	 * Sanitize a checkpoint-hours array: numeric, non-negative, unique,
	 * sorted, capped at 200 checkpoints and 10,000 hours per value.
	 *
	 * @param array $hours Raw hours.
	 * @return int[]
	 */
	private static function sanitize_checkpoint_hours( array $hours ) {
		$clean = array();

		foreach ( $hours as $hour ) {
			if ( ! is_numeric( $hour ) ) {
				continue;
			}

			$hour = absint( $hour );

			if ( $hour > 10000 ) {
				continue;
			}

			$clean[] = $hour;
		}

		$clean = array_values( array_unique( $clean ) );
		sort( $clean, SORT_NUMERIC );

		return array_slice( $clean, 0, 200 );
	}

	/**
	 * Maximum verification attempts before locking.
	 *
	 * @param int $course_id LearnDash course ID.
	 * @return int
	 */
	public static function get_max_attempts( $course_id ) {
		$value = get_post_meta( $course_id, self::META_MAX_ATTEMPTS, true );

		if ( '' === $value ) {
			$global = self::get_global_defaults();
			return absint( $global['default_max_attempts'] );
		}

		return max( 1, absint( $value ) );
	}

	/**
	 * Whether a max-attempts failure locks the student (vs. some future
	 * alternative behavior).
	 *
	 * @param int $course_id LearnDash course ID.
	 * @return bool
	 */
	public static function locks_on_failure( $course_id ) {
		$value = get_post_meta( $course_id, self::META_LOCK_ON_FAILURE, true );

		if ( '' === $value ) {
			$global = self::get_global_defaults();
			return (bool) $global['default_lock_on_max_failures'];
		}

		return '1' === $value;
	}

	/**
	 * Whether final-exam verification is enabled for this course.
	 *
	 * @param int $course_id LearnDash course ID.
	 * @return bool
	 */
	public static function is_final_exam_enabled( $course_id ) {
		return '1' === get_post_meta( $course_id, self::META_FINAL_EXAM_ENABLED, true );
	}

	/**
	 * The configured final-exam quiz post ID, explicitly chosen by the
	 * administrator — never assumed to be "the last quiz".
	 *
	 * @param int $course_id LearnDash course ID.
	 * @return int
	 */
	public static function get_final_exam_quiz_id( $course_id ) {
		return absint( get_post_meta( $course_id, self::META_FINAL_EXAM_QUIZ_ID, true ) );
	}

	/**
	 * Save the full set of per-course settings from a sanitized admin
	 * submission. Callers are responsible for capability/nonce checks
	 * before calling this.
	 *
	 * @param int   $course_id LearnDash course ID.
	 * @param array $data      Raw $_POST-derived data, already unslashed.
	 * @return void
	 */
	public static function save_from_submission( $course_id, array $data ) {
		self::set_enabled( $course_id, ! empty( $data['enabled'] ) );

		update_post_meta( $course_id, self::META_QUESTION_COUNT, max( 1, absint( $data['question_count'] ?? 3 ) ) );

		$hours = isset( $data['checkpoint_hours'] ) && is_array( $data['checkpoint_hours'] ) ? $data['checkpoint_hours'] : array();
		self::set_checkpoint_hours( $course_id, $hours );

		update_post_meta( $course_id, self::META_MAX_ATTEMPTS, max( 1, absint( $data['max_attempts'] ?? 3 ) ) );
		update_post_meta( $course_id, self::META_LOCK_ON_FAILURE, ! empty( $data['lock_on_failure'] ) ? '1' : '0' );
		update_post_meta( $course_id, self::META_FINAL_EXAM_ENABLED, ! empty( $data['final_exam_enabled'] ) ? '1' : '0' );
		update_post_meta( $course_id, self::META_FINAL_EXAM_QUIZ_ID, absint( $data['final_exam_quiz_id'] ?? 0 ) );
	}

	/**
	 * Get the admin-configured pool of security questions students choose
	 * from during setup. One question per line in Settings.
	 *
	 * @return string[]
	 */
	public static function get_question_pool() {
		$settings = get_option( 'courseverify_settings', array() );

		if ( ! array_key_exists( 'question_pool', $settings ) ) {
			// Settings saved before this option existed (or a fresh
			// activation edge case) — seed with a small example set so
			// setup isn't blank; once the admin saves Settings once
			// (even with the same values), this branch no longer applies.
			return array(
				__( 'What was the name of your first school?', 'courseverify-student-identity-checkpoints' ),
				__( 'What city were you born in?', 'courseverify-student-identity-checkpoints' ),
				__( 'What was the name of your first pet?', 'courseverify-student-identity-checkpoints' ),
			);
		}

		$pool = is_array( $settings['question_pool'] ) ? $settings['question_pool'] : array();

		return array_values( array_filter( array_map( 'trim', $pool ) ) );
	}

	/**
	 * Global default settings (the courseverify_settings option).
	 *
	 * @return array
	 */
	private static function get_global_defaults() {
		$defaults = array(
			'default_max_attempts'            => 3,
			'default_question_count'          => 3,
			'default_checkpoint_schedule_hrs' => array( 0, 5, 10, 15, 20, 25 ),
			'default_lock_on_max_failures'    => true,
		);

		return wp_parse_args( get_option( 'courseverify_settings', array() ), $defaults );
	}
}
