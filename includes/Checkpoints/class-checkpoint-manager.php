<?php
/**
 * Wires the Checkpoint_Engine to persisted checkpoint state.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Checkpoints;

use CourseVerify\Repositories\Checkpoint_Repository;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Checkpoint_Manager
 *
 * The only class that combines "what does the schedule say" (Engine) with
 * "what does the database currently say" (Repository) to produce the
 * checkpoint a student must resolve right now, if any. Verification_Service
 * calls this rather than talking to the Engine or Repository directly.
 */
class Checkpoint_Manager {

	/**
	 * @var Checkpoint_Repository
	 */
	private $repository;

	/**
	 * @var Checkpoint_Engine
	 */
	private $engine;

	/**
	 * Constructor.
	 *
	 * @param Checkpoint_Repository $repository Checkpoint repository instance.
	 * @param Checkpoint_Engine     $engine     Checkpoint engine instance.
	 */
	public function __construct( Checkpoint_Repository $repository, Checkpoint_Engine $engine ) {
		$this->repository = $repository;
		$this->engine      = $engine;
	}

	/**
	 * Determine the checkpoint (if any) that currently blocks a student,
	 * creating and advancing checkpoint rows as needed.
	 *
	 * @param int   $user_id             WordPress user ID.
	 * @param int   $course_id           LearnDash course ID.
	 * @param int   $course_time_seconds Tracked course time, in seconds.
	 * @param array $schedule            Output of Checkpoint_Engine::build_schedule_from_hours().
	 * @return array|null Checkpoint row (array) that is currently blocking,
	 *                     or null if the student may proceed.
	 */
	public function get_blocking_checkpoint( $user_id, $course_id, $course_time_seconds, array $schedule ) {

		$existing_rows = $this->repository->get_all_for_user_course( $user_id, $course_id );
		$status_map    = array();

		foreach ( $existing_rows as $row ) {
			$status_map[ $row['checkpoint_key'] ] = $row['status'];
		}

		$evaluation = $this->engine->evaluate( $course_time_seconds, $schedule, $status_map );

		// Ensure every reached checkpoint has a row, and advance any
		// not_reached row whose threshold has now been reached to required.
		foreach ( $schedule as $checkpoint ) {
			$key = $checkpoint['key'];

			if ( ! in_array( $key, $evaluation['reached_keys'], true ) ) {
				continue;
			}

			$id = $this->repository->ensure_exists( $user_id, $course_id, $key, $checkpoint['threshold_seconds'] );

			$current_row    = $this->repository->get( $user_id, $course_id, $key );
			$current_status = $current_row ? $current_row['status'] : 'not_reached';

			if ( Checkpoint_State::can_transition( $current_status, 'required' ) ) {
				$this->repository->update_status_guarded(
					$id,
					array( $current_status ),
					'required',
					array( 'required_at' => current_time( 'mysql' ) )
				);
			}
		}

		if ( null === $evaluation['blocking_checkpoint_key'] ) {
			return null;
		}

		return $this->repository->get( $user_id, $course_id, $evaluation['blocking_checkpoint_key'] );
	}

	/**
	 * Get all checkpoint rows for a student/course, for admin display.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id LearnDash course ID.
	 * @return array[]
	 */
	public function get_all( $user_id, $course_id ) {
		return $this->repository->get_all_for_user_course( $user_id, $course_id );
	}

	/**
	 * Repository accessor, for services that need direct guarded-update
	 * access (Verification_Service).
	 *
	 * @return Checkpoint_Repository
	 */
	public function repository() {
		return $this->repository;
	}
}
