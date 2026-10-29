<?php
/**
 * Verification-attempt bookkeeping.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Security;

use CourseVerify\Repositories\Attempt_Repository;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Attempt_Manager
 *
 * Records attempt outcomes (never the submitted answer) and answers
 * "has this checkpoint hit its max attempts" against the configured limit.
 * Does not decide checkpoint status itself — Verification_Service does
 * that, using this class's answers as input.
 */
class Attempt_Manager {

	/**
	 * @var Attempt_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param Attempt_Repository $repository Attempt repository instance.
	 */
	public function __construct( Attempt_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Record an attempt outcome.
	 *
	 * @param int    $user_id       WordPress user ID.
	 * @param int    $course_id     LearnDash course ID.
	 * @param int    $checkpoint_id Checkpoint row ID.
	 * @param string $result        One of: success, failure, locked, admin_unlock.
	 * @return void
	 */
	public function record( $user_id, $course_id, $checkpoint_id, $result ) {
		$allowed = array( 'success', 'failure', 'locked', 'admin_unlock' );

		if ( ! in_array( $result, $allowed, true ) ) {
			return;
		}

		$this->repository->record( $user_id, $course_id, $checkpoint_id, $result );
	}

	/**
	 * Whether the given attempt count has reached or exceeded the maximum
	 * allowed attempts.
	 *
	 * @param int $current_attempts Attempts already made (before this one).
	 * @param int $max_attempts     Configured maximum. Default 3.
	 * @return bool
	 */
	public function has_reached_max( $current_attempts, $max_attempts = 3 ) {
		return absint( $current_attempts ) >= absint( $max_attempts );
	}

	/**
	 * Recent attempt history for a checkpoint (admin view only — results
	 * and timestamps, never answer content).
	 *
	 * @param int $checkpoint_id Checkpoint row ID.
	 * @return array[]
	 */
	public function get_history( $checkpoint_id ) {
		return $this->repository->get_for_checkpoint( $checkpoint_id );
	}
}
