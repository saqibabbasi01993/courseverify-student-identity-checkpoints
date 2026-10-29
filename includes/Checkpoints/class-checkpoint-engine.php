<?php
/**
 * Determines which checkpoint, if any, currently requires verification.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Checkpoints;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Checkpoint_Engine
 *
 * Pure logic: given the tracked course time and the course's configured
 * checkpoint schedule and current per-checkpoint statuses, decide which
 * checkpoint (if any) is the one currently blocking the student. Does not
 * touch the database and does not render HTML — Checkpoint_Manager wires
 * this to the repository, and the UI layer renders whatever this returns.
 */
class Checkpoint_Engine {

	/**
	 * Evaluate a student's checkpoint schedule against their tracked course
	 * time and current checkpoint statuses.
	 *
	 * @param int   $course_time_seconds Tracked course time, in seconds.
	 * @param array $schedule            Ordered array of
	 *                                   ['key' => string, 'threshold_seconds' => int],
	 *                                   sorted ascending by threshold.
	 * @param array $current_statuses    Map of checkpoint_key => status, for
	 *                                   checkpoints that already have a row.
	 *                                   Checkpoints with no row yet are
	 *                                   treated as 'not_reached'.
	 * @return array {
	 *     @type string|null $blocking_checkpoint_key Key of the checkpoint
	 *                       currently requiring verification, or null if
	 *                       the student may proceed.
	 *     @type array       $reached_keys Keys of every checkpoint whose
	 *                       time threshold has been reached (used by the
	 *                       manager to ensure rows exist / transition
	 *                       not_reached -> required).
	 * }
	 */
	public function evaluate( $course_time_seconds, array $schedule, array $current_statuses ) {

		$reached_keys      = array();
		$blocking_checkpoint = null;

		foreach ( $schedule as $checkpoint ) {
			$key       = $checkpoint['key'];
			$threshold = (int) $checkpoint['threshold_seconds'];
			$status    = isset( $current_statuses[ $key ] ) ? $current_statuses[ $key ] : 'not_reached';

			$time_reached = $course_time_seconds >= $threshold;

			if ( $time_reached ) {
				$reached_keys[] = $key;
			}

			// Already resolved as passed — never blocks, regardless of time.
			if ( 'passed' === $status ) {
				continue;
			}

			// A locked or in-progress/required checkpoint blocks immediately,
			// independent of whether later checkpoints have also been
			// reached — the student must resolve checkpoints in order.
			if ( $time_reached && null === $blocking_checkpoint && in_array( $status, array( 'required', 'in_progress', 'failed', 'locked', 'admin_unlocked', 'not_reached' ), true ) ) {
				$blocking_checkpoint = $key;
			}
		}

		return array(
			'blocking_checkpoint_key' => $blocking_checkpoint,
			'reached_keys'            => $reached_keys,
		);
	}

	/**
	 * Build the internal-seconds schedule from an admin-configured array of
	 * hours (e.g. [0, 5, 10, 15, 20, 25]) plus a final-exam pseudo-checkpoint.
	 *
	 * @param int[] $hours               Checkpoint hours, ascending, e.g. [0,5,10,15,20,25].
	 * @param bool  $include_final_exam  Whether to append a "final_exam" checkpoint.
	 * @return array Ordered array of ['key' => string, 'threshold_seconds' => int].
	 */
	public function build_schedule_from_hours( array $hours, $include_final_exam = true ) {
		$hours = array_values( array_unique( array_map( 'absint', $hours ) ) );
		sort( $hours );

		$schedule = array();

		foreach ( $hours as $hour ) {
			$schedule[] = array(
				'key'               => 0 === $hour ? 'start' : $hour . 'h',
				'threshold_seconds' => $hour * HOUR_IN_SECONDS,
			);
		}

		if ( $include_final_exam ) {
			// Final exam has no time threshold of its own — it is gated on
			// "all prior checkpoints passed" by the Access_Controller, not
			// on course time, so it is represented with a threshold of
			// PHP_INT_MAX so it never becomes the "reached" time-blocker on
			// its own; the final-exam-specific check happens separately.
			$schedule[] = array(
				'key'               => 'final_exam',
				'threshold_seconds' => PHP_INT_MAX,
			);
		}

		return $schedule;
	}
}
