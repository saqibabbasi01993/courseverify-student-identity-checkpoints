<?php
/**
 * Student status lookup and administrator actions.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Services;

use CourseVerify\Course_Settings;
use CourseVerify\Capabilities;
use CourseVerify\Checkpoints\Checkpoint_Manager;
use CourseVerify\Checkpoints\Checkpoint_Engine;
use CourseVerify\Checkpoints\Checkpoint_State;
use CourseVerify\Security\Question_Manager;
use CourseVerify\Contracts\Time_Provider_Interface;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Student_Service
 *
 * Combines the time provider, checkpoint engine/manager, and question
 * manager into the answers the Access_Controller and admin screens need:
 * "is setup needed", "what checkpoint currently blocks this student", "is
 * the final exam gate open", plus the administrator actions (unlock,
 * reset checkpoint, reset questions) — all of which are audited.
 */
class Student_Service {

	/**
	 * @var Checkpoint_Manager
	 */
	private $checkpoints;

	/**
	 * @var Checkpoint_Engine
	 */
	private $engine;

	/**
	 * @var Question_Manager
	 */
	private $questions;

	/**
	 * @var Audit_Service
	 */
	private $audit;

	/**
	 * @var Time_Provider_Interface
	 */
	private $time_provider;

	/**
	 * Constructor.
	 *
	 * @param Checkpoint_Manager      $checkpoints   Checkpoint manager instance.
	 * @param Checkpoint_Engine       $engine        Checkpoint engine instance.
	 * @param Question_Manager        $questions     Question manager instance.
	 * @param Audit_Service           $audit         Audit service instance.
	 * @param Time_Provider_Interface $time_provider Active time provider.
	 */
	public function __construct(
		Checkpoint_Manager $checkpoints,
		Checkpoint_Engine $engine,
		Question_Manager $questions,
		Audit_Service $audit,
		Time_Provider_Interface $time_provider
	) {
		$this->checkpoints   = $checkpoints;
		$this->engine        = $engine;
		$this->questions     = $questions;
		$this->audit         = $audit;
		$this->time_provider = $time_provider;
	}

	/**
	 * Full status lookup for a student in a course. This is the single
	 * entry point the Access_Controller and frontend AJAX handlers call.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id LearnDash course ID.
	 * @return array {
	 *     @type string     $state 'setup_required'|'time_unavailable'|'blocked'|'clear'.
	 *     @type array|null $checkpoint Blocking checkpoint row, when state is 'blocked'.
	 *     @type string     $message Admin/diagnostic message, when state is 'time_unavailable'.
	 * }
	 */
	public function get_status( $user_id, $course_id ) {

		if ( $this->is_bypassed( $user_id ) ) {
			return array( 'state' => 'clear', 'checkpoint' => null, 'message' => '' );
		}

		if ( ! $this->questions->is_setup_complete( $user_id, $course_id ) ) {
			return array( 'state' => 'setup_required', 'checkpoint' => null, 'message' => '' );
		}

		$course_time = $this->time_provider->get_course_time( $user_id, $course_id );

		if ( is_wp_error( $course_time ) ) {
			// Per spec: never silently mark checkpoints as passed on invalid
			// timer data — surface a diagnostic instead of guessing.
			return array(
				'state'      => 'time_unavailable',
				'checkpoint' => null,
				'message'    => $course_time->get_error_message(),
			);
		}

		$hours    = Course_Settings::get_checkpoint_hours( $course_id );
		$schedule = $this->engine->build_schedule_from_hours( $hours, false );

		$blocking = $this->checkpoints->get_blocking_checkpoint( $user_id, $course_id, (int) $course_time, $schedule );

		if ( null === $blocking ) {
			return array( 'state' => 'clear', 'checkpoint' => null, 'message' => '' );
		}

		return array( 'state' => 'blocked', 'checkpoint' => $blocking, 'message' => '' );
	}

	/**
	 * Whether the final exam is currently accessible for a student:
	 * requires final-exam verification to be enabled for the course, all
	 * time-based checkpoints to already be 'passed', and the dedicated
	 * final_exam checkpoint itself to be 'passed'.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id LearnDash course ID.
	 * @return array {
	 *     @type bool       $allowed Whether the final exam may be accessed.
	 *     @type string     $reason  '' | 'prior_checkpoints_incomplete' | 'verification_required'.
	 *     @type array|null $checkpoint Final-exam checkpoint row, when verification is required.
	 * }
	 */
	public function get_final_exam_status( $user_id, $course_id ) {

		if ( $this->is_bypassed( $user_id ) ) {
			return array( 'allowed' => true, 'reason' => '', 'checkpoint' => null );
		}

		if ( ! Course_Settings::is_final_exam_enabled( $course_id ) ) {
			return array( 'allowed' => true, 'reason' => '', 'checkpoint' => null );
		}

		$hours    = Course_Settings::get_checkpoint_hours( $course_id );
		$schedule = $this->engine->build_schedule_from_hours( $hours, false );
		$all_rows = $this->checkpoints->get_all( $user_id, $course_id );

		$status_map = array();
		foreach ( $all_rows as $row ) {
			$status_map[ $row['checkpoint_key'] ] = $row['status'];
		}

		foreach ( $schedule as $checkpoint ) {
			$status = isset( $status_map[ $checkpoint['key'] ] ) ? $status_map[ $checkpoint['key'] ] : 'not_reached';

			if ( 'passed' !== $status ) {
				return array( 'allowed' => false, 'reason' => 'prior_checkpoints_incomplete', 'checkpoint' => null );
			}
		}

		$repository       = $this->checkpoints->repository();
		$final_id         = $repository->ensure_exists( $user_id, $course_id, 'final_exam', 0 );
		$final_row        = $repository->get( $user_id, $course_id, 'final_exam' );
		$final_status     = $final_row ? $final_row['status'] : 'not_reached';

		if ( 'passed' === $final_status ) {
			return array( 'allowed' => true, 'reason' => '', 'checkpoint' => null );
		}

		if ( Checkpoint_State::can_transition( $final_status, 'required' ) ) {
			$repository->update_status_guarded(
				$final_id,
				array( $final_status ),
				'required',
				array( 'required_at' => current_time( 'mysql' ) )
			);
			$final_row = $repository->get( $user_id, $course_id, 'final_exam' );
		}

		return array( 'allowed' => false, 'reason' => 'verification_required', 'checkpoint' => $final_row );
	}

	/**
	 * Whether checkpoint enforcement should be skipped entirely for this
	 * user — administrators (and anyone else granted CourseVerify's own
	 * management capability) are never required to set up security
	 * questions, never blocked by a time-based checkpoint, and never
	 * gated on the final exam. This is checked once, right at the top of
	 * both status lookups, so nothing downstream (setup, checkpoints,
	 * final exam) ever runs for a bypassed user — not just skipped
	 * cosmetically in the UI.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool
	 */
	private function is_bypassed( $user_id ) {
		$is_admin = user_can( $user_id, 'manage_options' ) || user_can( $user_id, Capabilities::MANAGE );

		/**
		 * Filters whether CourseVerify checkpoint enforcement is bypassed
		 * for a given user. Defaults to true for Administrators (and
		 * anyone granted the manage_courseverify capability) — false for
		 * everyone else.
		 *
		 * @param bool $is_admin Whether the user is treated as bypassed by default.
		 * @param int  $user_id  WordPress user ID.
		 */
		return (bool) apply_filters( 'courseverify_bypass_checkpoints', $is_admin, $user_id );
	}

	/**
	 * Fetch a single checkpoint row directly (used by the AJAX submit
	 * handler to re-check fresh state immediately before verifying).
	 *
	 * @param int    $user_id        WordPress user ID.
	 * @param int    $course_id      LearnDash course ID.
	 * @param string $checkpoint_key Checkpoint key.
	 * @return array|null
	 */
	public function get_checkpoint_row( $user_id, $course_id, $checkpoint_key ) {
		return $this->checkpoints->repository()->get( $user_id, $course_id, $checkpoint_key );
	}

	/**
	 * Complete initial identity setup for a student/course.
	 *
	 * @param int   $user_id   WordPress user ID.
	 * @param int   $course_id LearnDash course ID.
	 * @param array $pairs     Array of ['question_text' => string, 'answer' => string].
	 * @return true|\WP_Error
	 */
	public function complete_setup( $user_id, $course_id, array $pairs ) {
		$required = Course_Settings::get_question_count( $course_id );
		$result   = $this->questions->save_setup( $user_id, $course_id, $pairs, $required );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->audit->log( 'security_setup_completed', $user_id, $course_id );

		return true;
	}

	/**
	 * Administrator: unlock a locked checkpoint. Resets attempts and
	 * returns the checkpoint to 'required' so the student can try again.
	 *
	 * @param int    $user_id        Student user ID.
	 * @param int    $course_id      Course ID.
	 * @param string $checkpoint_key Checkpoint key.
	 * @param int    $actor_user_id  Administrator's user ID.
	 * @param string $reason         Optional admin-supplied reason.
	 * @return true|\WP_Error
	 */
	public function admin_unlock( $user_id, $course_id, $checkpoint_key, $actor_user_id, $reason = '' ) {
		$repository = $this->checkpoints->repository();
		$row        = $repository->get( $user_id, $course_id, $checkpoint_key );

		if ( ! $row || 'locked' !== $row['status'] ) {
			return new \WP_Error( 'courseverify_not_locked', __( 'This checkpoint is not currently locked.', 'courseverify-student-identity-checkpoints' ) );
		}

		$repository->update_status_guarded( $row['id'], array( 'locked' ), 'admin_unlocked', array( 'unlocked_at' => current_time( 'mysql' ) ) );
		$repository->update_status_guarded( $row['id'], array( 'admin_unlocked' ), 'required', array( 'attempts' => 0, 'required_at' => current_time( 'mysql' ) ) );
		$this->questions->clear_current_question( $row['id'] );

		$this->audit->log( 'student_unlocked', $user_id, $course_id, $checkpoint_key, $reason, $actor_user_id );

		do_action( 'courseverify_student_unlocked', $user_id, $course_id, $checkpoint_key, $actor_user_id );

		return true;
	}

	/**
	 * Administrator: reset a checkpoint back to 'required' regardless of
	 * its current status, resetting the attempt counter. Historical audit
	 * and attempt records are never deleted.
	 *
	 * @param int    $user_id        Student user ID.
	 * @param int    $course_id      Course ID.
	 * @param string $checkpoint_key Checkpoint key.
	 * @param int    $actor_user_id  Administrator's user ID.
	 * @param string $reason         Optional admin-supplied reason.
	 * @return true|\WP_Error
	 */
	public function admin_reset_checkpoint( $user_id, $course_id, $checkpoint_key, $actor_user_id, $reason = '' ) {
		$repository = $this->checkpoints->repository();
		$row        = $repository->get( $user_id, $course_id, $checkpoint_key );

		if ( ! $row ) {
			return new \WP_Error( 'courseverify_not_found', __( 'Checkpoint not found.', 'courseverify-student-identity-checkpoints' ) );
		}

		$repository->update_status_guarded(
			$row['id'],
			array( $row['status'] ),
			'required',
			array( 'attempts' => 0, 'required_at' => current_time( 'mysql' ) )
		);
		$this->questions->clear_current_question( $row['id'] );

		$this->audit->log( 'admin_checkpoint_reset', $user_id, $course_id, $checkpoint_key, $reason, $actor_user_id );

		return true;
	}

	/**
	 * Administrator: reset a student's configured security questions for a
	 * course, asking them to set new answers next time they reach a
	 * checkpoint. Existing checkpoint/verification history is preserved,
	 * but if the student was currently LOCKED, that lock is cleared too —
	 * otherwise resetting their questions alone would leave them stuck
	 * behind a lock that nothing else would clear. Optionally emails the
	 * student to let them know (off by default; enable under Settings).
	 *
	 * @param int    $user_id       Student user ID.
	 * @param int    $course_id     Course ID.
	 * @param int    $actor_user_id Administrator's user ID.
	 * @param string $reason        Optional admin-supplied reason.
	 * @return void
	 */
	public function admin_reset_questions( $user_id, $course_id, $actor_user_id, $reason = '' ) {
		$this->questions->reset_for_user_course( $user_id, $course_id );
		$this->audit->log( 'security_questions_reset', $user_id, $course_id, '', $reason, $actor_user_id );

		// Clear any lock so the student isn't left stuck after setting new
		// answers — a locked checkpoint otherwise stays locked until a
		// separate unlock action, which defeats the point of asking them
		// to reconfigure.
		$repository = $this->checkpoints->repository();

		foreach ( $repository->get_all_for_user_course( $user_id, $course_id ) as $row ) {
			if ( 'locked' !== $row['status'] ) {
				continue;
			}

			$repository->update_status_guarded( $row['id'], array( 'locked' ), 'admin_unlocked', array( 'unlocked_at' => current_time( 'mysql' ) ) );
			$repository->update_status_guarded( $row['id'], array( 'admin_unlocked' ), 'required', array( 'attempts' => 0, 'required_at' => current_time( 'mysql' ) ) );
			$this->questions->clear_current_question( $row['id'] );

			$this->audit->log( 'student_unlocked', $user_id, $course_id, $row['checkpoint_key'], __( 'Automatically unlocked as part of a security-questions reset.', 'courseverify-student-identity-checkpoints' ), $actor_user_id );
		}

		$this->maybe_notify_student_of_question_reset( $user_id, $course_id );
	}

	/**
	 * Send an optional notification email to the student, only if the
	 * "Notify students by email" setting is enabled. Never includes any
	 * answer content — just tells them to set new answers next visit.
	 *
	 * @param int $user_id   Student user ID.
	 * @param int $course_id Course ID.
	 * @return void
	 */
	private function maybe_notify_student_of_question_reset( $user_id, $course_id ) {
		$settings = get_option( 'courseverify_settings', array() );

		if ( empty( $settings['notify_students_by_email'] ) ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user || ! is_email( $user->user_email ) ) {
			return;
		}

		$course_title = get_the_title( $course_id );

		$subject = sprintf(
			/* translators: %s: course title. */
			__( 'Action needed: set new security answers for %s', 'courseverify-student-identity-checkpoints' ),
			$course_title
		);

		$message = sprintf(
			/* translators: 1: student display name, 2: course title. */
			__( "Hi %1\$s,\n\nYour security questions for \"%2\$s\" have been reset by your course administrator. The next time you open the course, you'll be asked to set new security answers before continuing.\n\nIf you weren't expecting this, please contact your course administrator.", 'courseverify-student-identity-checkpoints' ),
			$user->display_name,
			$course_title
		);

		/**
		 * Filters whether the question-reset notification email is actually
		 * sent, for sites that want to suppress or customize this further.
		 *
		 * @param bool   $send      Whether to send. Default true (setting already gates this).
		 * @param int    $user_id   Student user ID.
		 * @param int    $course_id Course ID.
		 */
		if ( ! apply_filters( 'courseverify_send_question_reset_email', true, $user_id, $course_id ) ) {
			return;
		}

		wp_mail( $user->user_email, $subject, $message );
	}
}
