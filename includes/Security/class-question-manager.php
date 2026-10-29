<?php
/**
 * Student security-question setup and selection.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Security;

use CourseVerify\Repositories\Question_Repository;
use CourseVerify\Course_Settings;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Question_Manager
 *
 * Handles initial security-question setup (validation, normalization,
 * hashing, saving) and random question selection at checkpoint time. Never
 * exposes stored answers back out, to anyone, including administrators.
 */
class Question_Manager {

	/**
	 * @var Question_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param Question_Repository $repository Question repository instance.
	 */
	public function __construct( Question_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Whether a student has completed identity setup for a course.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id LearnDash course ID.
	 * @return bool
	 */
	public function is_setup_complete( $user_id, $course_id ) {
		return $this->repository->has_configured_questions( $user_id, $course_id );
	}

	/**
	 * Validate and save a batch of student-submitted questions/answers.
	 *
	 * @param int   $user_id   WordPress user ID.
	 * @param int   $course_id LearnDash course ID.
	 * @param array $pairs     Array of ['question_text' => string, 'answer' => string].
	 * @param int   $required_count Minimum number of questions required.
	 * @return true|\WP_Error
	 */
	public function save_setup( $user_id, $course_id, array $pairs, $required_count ) {

		if ( $this->repository->has_configured_questions( $user_id, $course_id ) ) {
			return new \WP_Error(
				'courseverify_already_configured',
				__( 'Security questions have already been configured for this course.', 'courseverify-student-identity-checkpoints' )
			);
		}

		$clean_pairs = array();

		foreach ( $pairs as $pair ) {
			// $pairs arrives already unslashed and sanitized by the caller
			// (the AJAX handler's raw $_POST access point) — re-applying
			// wp_unslash() here would risk stripping a legitimate backslash
			// character out of an answer a second time, so this only
			// re-sanitizes (idempotent) rather than unslashing again.
			$question_text = isset( $pair['question_text'] ) ? sanitize_text_field( $pair['question_text'] ) : '';
			$answer        = isset( $pair['answer'] ) ? (string) $pair['answer'] : '';

			if ( '' === $question_text || '' === trim( $answer ) ) {
				return new \WP_Error(
					'courseverify_incomplete_question',
					__( 'Every security question must have both a question and an answer.', 'courseverify-student-identity-checkpoints' )
				);
			}

			$clean_pairs[] = array(
				'question_text' => $question_text,
				'answer'        => $answer,
			);
		}

		// If an admin question pool is configured, the question text
		// submitted for each answer is presented to the student read-only
		// (a hidden field carries it back for the AJAX request), but a
		// hidden field is still just client-side markup — nothing stops a
		// browser dev-tools edit from swapping it for arbitrary text
		// before submit. Re-validate every submitted question_text against
		// the actual configured pool here, server-side, so a student can
		// only ever end up with a question that's genuinely on the
		// admin-approved list, never one they invented themselves.
		$pool = Course_Settings::get_question_pool();

		if ( ! empty( $pool ) ) {
			$normalized_pool = array_map( 'trim', $pool );

			foreach ( $clean_pairs as $pair ) {
				if ( ! in_array( $pair['question_text'], $normalized_pool, true ) ) {
					return new \WP_Error(
						'courseverify_invalid_question',
						__( 'Security questions must be selected from the provided list and cannot be changed.', 'courseverify-student-identity-checkpoints' )
					);
				}
			}

			$submitted_texts = wp_list_pluck( $clean_pairs, 'question_text' );

			if ( count( array_unique( $submitted_texts ) ) !== count( $submitted_texts ) ) {
				return new \WP_Error(
					'courseverify_duplicate_question',
					__( 'Please answer each security question only once — the same question cannot be used twice.', 'courseverify-student-identity-checkpoints' )
				);
			}
		}

		if ( count( $clean_pairs ) < absint( $required_count ) ) {
			return new \WP_Error(
				'courseverify_not_enough_questions',
				sprintf(
					/* translators: %d: number of required security questions. */
					__( 'Please configure at least %d security questions.', 'courseverify-student-identity-checkpoints' ),
					absint( $required_count )
				)
			);
		}

		foreach ( $clean_pairs as $index => $pair ) {
			$normalized_answer = self::normalize_answer( $pair['answer'] );
			$answer_hash        = wp_hash_password( $normalized_answer );
			$question_key       = 'q' . ( $index + 1 );

			$this->repository->insert( $user_id, $course_id, $question_key, $pair['question_text'], $answer_hash );
		}

		return true;
	}

	/**
	 * Select the question to present for a checkpoint that is currently
	 * in progress.
	 *
	 * The server is the sole authority on which question is "the" question
	 * for a given in-progress attempt: once one has been assigned, it is
	 * cached (keyed by checkpoint row ID) and reused on every subsequent
	 * call — a page reload or a background status poll must keep showing
	 * the same question rather than silently re-rolling a new one out from
	 * under the student, and Verification_Service checks a submitted
	 * answer's question_id against this same cached value so a student
	 * can't answer a different one of their own questions than the one
	 * actually displayed. A fresh question is only chosen once the cache
	 * is empty — i.e. the first time a checkpoint becomes in-progress, or
	 * after clear_current_question() has been called on a resolved/reset
	 * checkpoint — and avoids immediately repeating whatever was cached
	 * previously where more than one question is configured.
	 *
	 * @param int $user_id       WordPress user ID.
	 * @param int $course_id     LearnDash course ID.
	 * @param int $checkpoint_id Checkpoint row ID (used only to key the
	 *                           per-attempt cache, never persisted as part
	 *                           of the question record itself).
	 * @return array|\WP_Error Question row (id, question_key, question_text,
	 *                          answer_hash), or WP_Error if none configured.
	 */
	public function select_question_for_checkpoint( $user_id, $course_id, $checkpoint_id ) {
		$questions = $this->repository->get_for_user_course( $user_id, $course_id );

		if ( empty( $questions ) ) {
			return new \WP_Error(
				'courseverify_no_questions',
				__( 'No security questions are configured for this student.', 'courseverify-student-identity-checkpoints' )
			);
		}

		$current_id = $this->get_current_question_id( $checkpoint_id );

		if ( $current_id ) {
			foreach ( $questions as $question ) {
				if ( (int) $question['id'] === $current_id ) {
					return $question;
				}
			}
			// The cached ID no longer matches any of the student's current
			// questions (e.g. they were reset) — fall through and assign a
			// new one below.
		}

		if ( 1 === count( $questions ) ) {
			$selected = $questions[0];
		} else {
			$candidates = array_filter(
				$questions,
				function ( $question ) use ( $current_id ) {
					return (int) $question['id'] !== $current_id;
				}
			);

			$candidates = array_values( $candidates ? $candidates : $questions );
			$selected   = $candidates[ array_rand( $candidates ) ];
		}

		set_transient( $this->current_question_transient_key( $checkpoint_id ), $selected['id'], DAY_IN_SECONDS );

		return $selected;
	}

	/**
	 * Get the question ID currently assigned to an in-progress checkpoint
	 * attempt, if any.
	 *
	 * @param int $checkpoint_id Checkpoint row ID.
	 * @return int 0 if none assigned.
	 */
	public function get_current_question_id( $checkpoint_id ) {
		return (int) get_transient( $this->current_question_transient_key( $checkpoint_id ) );
	}

	/**
	 * Clear the assigned-question cache for a checkpoint. Call this
	 * whenever a checkpoint leaves the in-progress state (passed, locked,
	 * or admin-reset/unlocked) so the next time it becomes in-progress a
	 * fresh question is assigned rather than reusing a stale one.
	 *
	 * @param int $checkpoint_id Checkpoint row ID.
	 * @return void
	 */
	public function clear_current_question( $checkpoint_id ) {
		delete_transient( $this->current_question_transient_key( $checkpoint_id ) );
	}

	/**
	 * Build the transient key used to cache the assigned question for a
	 * checkpoint attempt.
	 *
	 * @param int $checkpoint_id Checkpoint row ID.
	 * @return string
	 */
	private function current_question_transient_key( $checkpoint_id ) {
		return 'courseverify_current_q_' . absint( $checkpoint_id );
	}

	/**
	 * Reset (delete) a student's configured questions for a course. The
	 * student will be required to configure new ones. Does not touch
	 * checkpoint or audit history.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id LearnDash course ID.
	 * @return void
	 */
	public function reset_for_user_course( $user_id, $course_id ) {
		$this->repository->delete_for_user_course( $user_id, $course_id );
	}

	/**
	 * Randomly select N questions from an admin-configured pool for a
	 * student to answer during setup. If the pool has fewer entries than
	 * requested, every pool question is used.
	 *
	 * @param string[] $pool  Admin-configured question pool.
	 * @param int      $count Number of questions to select.
	 * @return string[] Selected question texts.
	 */
	public static function select_setup_questions_from_pool( array $pool, $count ) {
		$pool = array_values( array_filter( array_map( 'trim', $pool ) ) );

		if ( empty( $pool ) ) {
			return array();
		}

		$count = min( absint( $count ), count( $pool ) );

		if ( $count >= count( $pool ) ) {
			return $pool;
		}

		$keys = (array) array_rand( $pool, $count );
		sort( $keys );

		$selected = array();
		foreach ( $keys as $key ) {
			$selected[] = $pool[ $key ];
		}

		return $selected;
	}

	/**
	 * Normalize a submitted answer consistently for both setup and
	 * verification: case-insensitive, whitespace-trimmed, internal
	 * whitespace collapsed.
	 *
	 * @param string $answer Raw answer text.
	 * @return string Normalized answer.
	 */
	public static function normalize_answer( $answer ) {
		$answer = strtolower( trim( (string) $answer ) );
		$answer = preg_replace( '/\s+/', ' ', $answer );

		return $answer;
	}
}
