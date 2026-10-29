<?php
/**
 * Strict HH:MM:SS to seconds parser.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Integrations;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Time_Parser
 *
 * Converts course-timer display strings such as "05:23:14" or "125:15:45"
 * into whole seconds. Deliberately strict: anything that isn't a clean
 * HH:MM:SS format with valid minute/second ranges is rejected rather than
 * loosely coerced, since a mis-parsed value could silently skip or falsely
 * trigger a checkpoint.
 */
class Time_Parser {

	/**
	 * Parse an HH:MM:SS string into total seconds.
	 *
	 * Hours may exceed 23 (multi-day courses), minutes and seconds must be
	 * strictly between 00 and 59.
	 *
	 * @param string $time Time string, e.g. "05:23:14" or "125:15:45".
	 * @return int|\WP_Error Total seconds, or WP_Error on invalid input.
	 */
	public static function parse_time_to_seconds( $time ) {

		if ( ! is_string( $time ) ) {
			return new \WP_Error(
				'courseverify_invalid_time_type',
				__( 'Course time value must be a string.', 'courseverify-student-identity-checkpoints' )
			);
		}

		$time = trim( $time );

		// Strict HH:MM:SS — hours: 1+ digits, minutes/seconds: exactly 2 digits, 00-59.
		if ( ! preg_match( '/^(\d+):([0-5]\d):([0-5]\d)$/', $time, $matches ) ) {
			return new \WP_Error(
				'courseverify_invalid_time_format',
				__( 'Course time value is not a valid HH:MM:SS string.', 'courseverify-student-identity-checkpoints' )
			);
		}

		$hours   = (int) $matches[1];
		$minutes = (int) $matches[2];
		$seconds = (int) $matches[3];

		$total_seconds = ( $hours * HOUR_IN_SECONDS ) + ( $minutes * MINUTE_IN_SECONDS ) + $seconds;

		return $total_seconds;
	}

	/**
	 * Format total seconds back into an HH:MM:SS string, for display only.
	 * Never used as a source of truth — only for showing time to admins.
	 *
	 * @param int $total_seconds Total seconds.
	 * @return string
	 */
	public static function format_seconds_to_time( $total_seconds ) {
		$total_seconds = max( 0, (int) $total_seconds );

		$hours   = (int) floor( $total_seconds / HOUR_IN_SECONDS );
		$minutes = (int) floor( ( $total_seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
		$seconds = $total_seconds % MINUTE_IN_SECONDS;

		return sprintf( '%02d:%02d:%02d', $hours, $minutes, $seconds );
	}
}
