<?php
/**
 * Verification business logic.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Services;

use CourseVerify\Checkpoints\Checkpoint_Manager;
use CourseVerify\Checkpoints\Checkpoint_State;
use CourseVerify\Security\Question_Manager;
use CourseVerify\Security\Answer_Verifier;
use CourseVerify\Security\Attempt_Manager;
use CourseVerify\Repositories\Question_Repository;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Verification_Service
 *
 * Orchestrates a verification attempt end to end: re-checks current state
 * (idempotency), selects/validates the question, checks the answer,
 * transitions checkpoint status via the guarded repository update, records
 * the attempt outcome, and logs the audit trail. This is the only class
 * that should drive checkpoint state during student-facing verification —
 * UI and AJAX handlers call into this, never the repository directly.
 */
class Verification_Service {

	/**
	 * @var Checkpoint_Manager
	 */
	private $checkpoints;

	/**
	 * @var Question_Manager
	 */
	private $questions;

	/**
	 * @var Answer_Verifier
	 */
	private $verifier;

	/**
	 * @var Attempt_Manager
	 */
	private $attempts;

	/**
	 * @var Audit_Service
	 */
	private $audit;

	/**
	 * @var Question_Repository
	 */
	private $question_repository;

	/**
	 * Constructor.
	 *
	 * @param Checkpoint_Manager   $checkpoints         Checkpoint manager instance.
	 * @param Question_Manager     $questions           Question manager instance.
	 * @param Answer_Verifier      $verifier            Answer verifier instance.
	 * @param Attempt_Manager      $attempts            Attempt manager instance.
	 * @param Audit_Service        $audit               Audit service instance.
	 * @param Question_Repository  $question_repository Question repository instance.
	 */
	public function __construct(
		Checkpoint_Manager $checkpoints,
		Question_Manager $questions,
		Answer_Verifier $verifier,
		Attempt_Manager $attempts,
		Audit_Service $audit,
		Question_Repository $question_repository = null
	) {
		$this->checkpoints = $checkpoints;
		$this->questions   = $questions;
		$this->verifier    = $verifier;
		$this->attempts    = $attempts;
		$this->audit       = $audit;
		$this->question_repository = $question_repository ? $question_repository : new Question_Repository();
	}

	/**
	 * Begin verification for a required checkpoint: transitions
	 * required -> in_progress and returns a question to present. Safe to
	 * call repeatedly (e.g. on page refresh) — if already in_progress it
	 * simply returns the current question again rather than re-selecting.
	 *
	 * @param int   $user_id   WordPress user ID.
	 * @param int   $course_id LearnDash course ID.
	 * @param array $checkpoint_row Current checkpoint row (from Checkpoint_Manager).
	 * @return array|\WP_Error {
	 *     @type int    $question_id   Question row ID (never the answer/hash).
	 *     @type string $question_text Question text to display.
	 * }
	 */
	public function start_verification( $user_id, $course_id, array $checkpoint_row ) {

		if ( 'locked' === $checkpoint_row['status'] ) {
			return new \WP_Error( 'courseverify_locked', __( 'This checkpoint is locked. Please contact your course administrator.', 'courseverify-student-identity-checkpoints' ) );
		}

		if ( 'passed' === $checkpoint_row['status'] ) {
			return new \WP_Error( 'courseverify_already_passed', __( 'This checkpoint has already been verified.', 'courseverify-student-identity-checkpoints' ) );
		}

		if ( Checkpoint_State::can_transition( $checkpoint_row['status'], 'in_progress' ) ) {
			$this->checkpoints->repository()->update_status_guarded(
				$checkpoint_row['id'],
				array( $checkpoint_row['status'] ),
				'in_progress'
			);

			/**
			 * Fires when a checkpoint becomes required and verification begins.
			 *
			 * @param int $user_id   WordPress user ID.
			 * @param int $course_id LearnDash course ID.
			 * @param string $checkpoint_key Checkpoint key.
			 */
			do_action( 'courseverify_checkpoint_required', $user_id, $course_id, $checkpoint_row['checkpoint_key'] );
		}

		$question = $this->questions->select_question_for_checkpoint( $user_id, $course_id, $checkpoint_row['id'] );

		if ( is_wp_error( $question ) ) {
			return $question;
		}

		return array(
			'question_id'   => (int) $question['id'],
			'question_text' => $question['question_text'],
		);
	}

	/**
	 * Process a submitted answer for a checkpoint currently in progress.
	 *
	 * @param int    $user_id          WordPress user ID.
	 * @param int    $course_id        LearnDash course ID.
	 * @param array  $checkpoint_row   Checkpoint row, re-fetched fresh by the caller immediately before this call.
	 * @param int    $question_id      The question ID the student was shown.
	 * @param string $submitted_answer Raw submitted answer.
	 * @param int    $max_attempts     Configured maximum attempts for this course.
	 * @return array|\WP_Error {
	 *     @type string $result 'passed' or 'failed_retry' or 'locked'.
	 *     @type string $message User-facing message.
	 * }
	 */
	public function submit_answer( $user_id, $course_id, array $checkpoint_row, $question_id, $submitted_answer, $max_attempts ) {

		// Idempotency / anti-bypass: re-validate current state rather than
		// trusting whatever the caller/frontend believed it to be.
		if ( 'in_progress' !== $checkpoint_row['status'] ) {
			if ( 'passed' === $checkpoint_row['status'] ) {
				return array(
					'result'  => 'passed',
					'message' => __( 'Identity already verified.', 'courseverify-student-identity-checkpoints' ),
				);
			}

			return new \WP_Error( 'courseverify_invalid_state', __( 'This checkpoint is not currently awaiting verification.', 'courseverify-student-identity-checkpoints' ) );
		}

		// The submitted question_id must match the specific question the
		// server actually assigned to this in-progress attempt — not
		// merely any question that happens to belong to the student. This
		// is what keeps "which question is being asked" a server
		// decision: a student can't answer whichever of their own
		// questions they remember best instead of the one shown.
		$expected_question_id = $this->questions->get_current_question_id( $checkpoint_row['id'] );

		if ( ! $expected_question_id || $expected_question_id !== (int) $question_id ) {
			return new \WP_Error( 'courseverify_invalid_question', __( 'That question is not valid for this checkpoint.', 'courseverify-student-identity-checkpoints' ) );
		}

		$questions_for_student = $this->question_repository->get_for_user_course( $user_id, $course_id );

		$matched_question = null;

		foreach ( $questions_for_student as $question ) {
			if ( (int) $question['id'] === (int) $question_id ) {
				$matched_question = $question;
				break;
			}
		}

		if ( ! $matched_question ) {
			return new \WP_Error( 'courseverify_invalid_question', __( 'That question is not valid for this checkpoint.', 'courseverify-student-identity-checkpoints' ) );
		}

		/**
		 * Fires immediately before an answer is checked.
		 *
		 * @param int $user_id   WordPress user ID.
		 * @param int $course_id LearnDash course ID.
		 */
		do_action( 'courseverify_before_verification', $user_id, $course_id, $checkpoint_row['checkpoint_key'] );

		$is_correct = $this->verifier->verify( $submitted_answer, $matched_question['answer_hash'] );

		/**
		 * Fires immediately after an answer is checked.
		 *
		 * @param int  $user_id    WordPress user ID.
		 * @param int  $course_id  LearnDash course ID.
		 * @param bool $is_correct Whether the answer was correct.
		 */
		do_action( 'courseverify_after_verification', $user_id, $course_id, $checkpoint_row['checkpoint_key'], $is_correct );

		if ( $is_correct ) {
			$transitioned = $this->checkpoints->repository()->update_status_guarded(
				$checkpoint_row['id'],
				array( 'in_progress' ),
				'passed',
				array( 'verified_at' => current_time( 'mysql' ) )
			);

			if ( ! $transitioned ) {
				// Someone else (another tab) already resolved this checkpoint.
				return array(
					'result'  => 'passed',
					'message' => __( 'Identity already verified.', 'courseverify-student-identity-checkpoints' ),
				);
			}

			$this->questions->clear_current_question( $checkpoint_row['id'] );
			$this->attempts->record( $user_id, $course_id, $checkpoint_row['id'], 'success' );
			$this->audit->log( 'verification_success', $user_id, $course_id, $checkpoint_row['checkpoint_key'] );
			$this->audit->log( 'checkpoint_passed', $user_id, $course_id, $checkpoint_row['checkpoint_key'] );

			do_action( 'courseverify_checkpoint_passed', $user_id, $course_id, $checkpoint_row['checkpoint_key'] );

			return array(
				'result'  => 'passed',
				'message' => $this->get_configured_message( 'message_verification_success', __( 'Identity verified. You may continue your course.', 'courseverify-student-identity-checkpoints' ) ),
			);
		}

		// Incorrect answer.
		$this->checkpoints->repository()->increment_attempts( $checkpoint_row['id'] );
		$this->attempts->record( $user_id, $course_id, $checkpoint_row['id'], 'failure' );
		$this->audit->log( 'verification_failure', $user_id, $course_id, $checkpoint_row['checkpoint_key'] );

		do_action( 'courseverify_verification_failed', $user_id, $course_id, $checkpoint_row['checkpoint_key'] );
		do_action( 'courseverify_checkpoint_failed', $user_id, $course_id, $checkpoint_row['checkpoint_key'] );

		$attempts_made      = (int) $checkpoint_row['attempts'] + 1;
		$attempts_remaining = max( 0, absint( $max_attempts ) - $attempts_made );
		$lock_on_failure    = \CourseVerify\Course_Settings::locks_on_failure( $course_id );
		$reached_max        = $this->attempts->has_reached_max( $attempts_made, $max_attempts );

		if ( $lock_on_failure && $reached_max ) {
			$this->checkpoints->repository()->update_status_guarded(
				$checkpoint_row['id'],
				array( 'in_progress' ),
				'locked',
				array( 'locked_at' => current_time( 'mysql' ) )
			);

			$this->questions->clear_current_question( $checkpoint_row['id'] );
			$this->attempts->record( $user_id, $course_id, $checkpoint_row['id'], 'locked' );
			$this->audit->log( 'student_locked', $user_id, $course_id, $checkpoint_row['checkpoint_key'] );

			do_action( 'courseverify_student_locked', $user_id, $course_id, $checkpoint_row['checkpoint_key'] );

			return array(
				'result'             => 'locked',
				'message'            => $this->get_configured_message( 'message_locked', __( 'Your course access is temporarily locked because identity verification could not be completed. Please contact your school or course administrator.', 'courseverify-student-identity-checkpoints' ) ),
				'attempts_remaining' => 0,
			);
		}

		$base_message = $this->get_configured_message( 'message_verification_failure', __( 'That answer was not correct. Please try again.', 'courseverify-student-identity-checkpoints' ) );

		// Course has "Lock on Failure" turned off: never threaten a
		// lockout that isn't actually going to happen, on ANY attempt —
		// not just the one that would otherwise have reached the limit.
		// Once the configured attempt count is hit, quietly reset the
		// counter so the student can keep retrying indefinitely.
		if ( ! $lock_on_failure ) {
			if ( $reached_max ) {
				$this->checkpoints->repository()->update_status_guarded(
					$checkpoint_row['id'],
					array( 'in_progress' ),
					'in_progress',
					array( 'attempts' => 0 )
				);
			}

			return array(
				'result'             => 'failed_retry',
				'message'            => $base_message,
				'attempts_remaining' => $reached_max ? absint( $max_attempts ) : $attempts_remaining,
				'is_final_attempt'   => false,
			);
		}

		$message = 1 === $attempts_remaining
			? sprintf(
				/* translators: %s: the configured verification-failure message. */
				__( '%s You have 1 attempt remaining before your course access is locked.', 'courseverify-student-identity-checkpoints' ),
				$base_message
			)
			: sprintf(
				/* translators: 1: the configured verification-failure message, 2: number of attempts remaining. */
				__( '%1$s You have %2$d attempts remaining before your course access is locked.', 'courseverify-student-identity-checkpoints' ),
				$base_message,
				$attempts_remaining
			);

		return array(
			'result'             => 'failed_retry',
			'message'            => $message,
			'attempts_remaining' => $attempts_remaining,
			'is_final_attempt'   => 1 === $attempts_remaining,
		);
	}

	/**
	 * Get an admin-configured message from Settings, falling back to the
	 * built-in default when the admin hasn't set one.
	 *
	 * @param string $key     Settings key (message_verification_success,
	 *                        message_verification_failure, or message_locked).
	 * @param string $default Fallback text.
	 * @return string
	 */
	private function get_configured_message( $key, $default ) {
		$settings = get_option( 'courseverify_settings', array() );

		return ! empty( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}
}
