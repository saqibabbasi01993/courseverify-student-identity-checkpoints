<?php
/**
 * Server-side course-content access enforcement.
 *
 * @package CourseVerify
 */

namespace CourseVerify;

use CourseVerify\Integrations\LearnDash;
use CourseVerify\Services\Student_Service;
use CourseVerify\Frontend\Verification_UI;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Access_Controller
 *
 * Hooks into `the_content` for singular LearnDash course/lesson/topic/quiz
 * pages so the site's own theme chrome (header, footer, navigation) still
 * renders normally — only the protected content area is replaced with the
 * setup/verification/locked UI when required. This avoids assuming a
 * specific LearnDash content-blocking hook exists in every version (see
 * Integrations\LearnDash compatibility note) while still enforcing access
 * entirely server-side: the check re-runs on every request, so direct
 * URLs, refreshes, new tabs, and repeated requests are all re-evaluated.
 *
 * KNOWN LIMITATION (documented per spec, not silently ignored): this gates
 * the rendered page content. It does not yet hard-block LearnDash's AJAX
 * quiz-submission endpoints at the request level for a quiz page a student
 * reached before a checkpoint became due mid-quiz. Fully closing that gap
 * requires verifying which LearnDash AJAX action handles quiz submission
 * in your installed version — flagged here for Phase-9 follow-up rather
 * than guessed at.
 */
class Access_Controller {

	/**
	 * @var Student_Service
	 */
	private $students;

	/**
	 * @var Verification_UI
	 */
	private $ui;

	/**
	 * Constructor.
	 *
	 * @param Student_Service  $students Student service instance.
	 * @param Verification_UI  $ui       Verification UI renderer.
	 */
	public function __construct( Student_Service $students, Verification_UI $ui ) {
		$this->students = $students;
		$this->ui       = $ui;
	}

	/**
	 * Register hooks.
	 *
	 * @param Loader $loader Hook loader.
	 * @return void
	 */
	public function register( Loader $loader ) {
		$loader->add_filter( 'the_content', $this, 'filter_content', 999 );
	}

	/**
	 * Filter LearnDash content, replacing it with setup/verification/locked
	 * UI when CourseVerify determines the student may not proceed.
	 *
	 * @param string $content Original post content.
	 * @return string
	 */
	public function filter_content( $content ) {

		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		if ( ! LearnDash::is_active() ) {
			return $content;
		}

		$post_id = get_the_ID();
		$type    = LearnDash::get_content_type( $post_id );

		if ( '' === $type ) {
			return $content;
		}

		$course_id = LearnDash::get_course_id( $post_id );

		if ( ! $course_id || ! Course_Settings::is_enabled( $course_id ) ) {
			return $content;
		}

		if ( ! is_user_logged_in() ) {
			// Let LearnDash / the theme handle its own login prompting;
			// CourseVerify only gates content for enrolled, logged-in students.
			return $content;
		}

		$user_id = get_current_user_id();

		if ( ! LearnDash::is_user_enrolled( $user_id, $course_id ) ) {
			return $content;
		}

		// The configured final-exam quiz is gated on "all prior checkpoints
		// passed", not on time — checked separately from the time-based flow.
		if ( 'sfwd-quiz' === $type && Course_Settings::is_final_exam_enabled( $course_id )
			&& $post_id === Course_Settings::get_final_exam_quiz_id( $course_id ) ) {

			$final_status = $this->students->get_final_exam_status( $user_id, $course_id );

			if ( ! $final_status['allowed'] ) {
				if ( 'prior_checkpoints_incomplete' === $final_status['reason'] ) {
					return $this->ui->render_final_exam_blocked();
				}

				return $this->ui->render_verification( $final_status['checkpoint'], $course_id );
			}

			return $content;
		}

		$status = $this->students->get_status( $user_id, $course_id );

		switch ( $status['state'] ) {
			case 'setup_required':
				return $this->ui->render_setup( $course_id );

			case 'time_unavailable':
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'CourseVerify: course time unavailable for user ' . $user_id . ', course ' . $course_id . ': ' . $status['message'] );
				}
				return $this->ui->render_time_unavailable();

			case 'blocked':
				if ( 'locked' === $status['checkpoint']['status'] ) {
					return $this->ui->render_locked();
				}
				return $this->ui->render_verification( $status['checkpoint'], $course_id );

			case 'clear':
			default:
				return $content;
		}
	}
}
