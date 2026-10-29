<?php
/**
 * Frontend template renderer.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Frontend;

use CourseVerify\Course_Settings;
use CourseVerify\Security;
use CourseVerify\Security\Question_Manager;
use CourseVerify\Services\Verification_Service;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Verification_UI
 *
 * Every render_*() method here prepares plain scalar/array data and hands
 * it to a template in public/views/ via output buffering. Templates never
 * query the database themselves and always escape on output.
 */
class Verification_UI {

	/**
	 * @var Verification_Service
	 */
	private $verification;

	/**
	 * Constructor.
	 *
	 * @param Verification_Service $verification Verification service instance.
	 */
	public function __construct( Verification_Service $verification ) {
		$this->verification = $verification;
	}

	/**
	 * Render the initial security-question setup form.
	 *
	 * @param int $course_id LearnDash course ID.
	 * @return string
	 */
	public function render_setup( $course_id ) {
		$question_count = Course_Settings::get_question_count( $course_id );
		$pool           = Course_Settings::get_question_pool();
		$pool_questions = Question_Manager::select_setup_questions_from_pool( $pool, $question_count );
		$nonce          = wp_create_nonce( Security::NONCE_SAVE_SETUP );
		$ajax_url       = admin_url( 'admin-ajax.php' );

		return $this->buffer_template(
			'setup',
			array(
				'course_id'      => (int) $course_id,
				'question_count' => (int) $question_count,
				'pool_questions' => $pool_questions,
				'nonce'          => $nonce,
				'ajax_url'       => $ajax_url,
			)
		);
	}

	/**
	 * Render the verification prompt for a blocking checkpoint, starting
	 * (or resuming) verification for it via the Verification_Service.
	 *
	 * @param array $checkpoint_row Blocking checkpoint row.
	 * @param int   $course_id      LearnDash course ID.
	 * @return string
	 */
	public function render_verification( array $checkpoint_row, $course_id ) {
		$user_id = get_current_user_id();
		$result  = $this->verification->start_verification( $user_id, $course_id, $checkpoint_row );

		if ( is_wp_error( $result ) ) {
			if ( 'courseverify_locked' === $result->get_error_code() ) {
				return $this->render_locked();
			}

			return $this->render_generic_message( $result->get_error_message() );
		}

		return $this->buffer_template(
			'verification',
			array(
				'course_id'      => (int) $course_id,
				'checkpoint_key' => $checkpoint_row['checkpoint_key'],
				'question_id'    => (int) $result['question_id'],
				'question_text'  => $result['question_text'],
			)
		);
	}

	/**
	 * Render the locked-out message.
	 *
	 * @return string
	 */
	public function render_locked() {
		$settings = get_option( 'courseverify_settings', array() );
		$message  = ! empty( $settings['message_locked'] )
			? $settings['message_locked']
			: __( 'Your course access is temporarily locked because identity verification could not be completed. Please contact your school or course administrator.', 'courseverify-student-identity-checkpoints' );

		return $this->buffer_template( 'locked', array( 'message' => $message ) );
	}

	/**
	 * Render a message shown when the course-time provider returned an
	 * error rather than a usable value — never silently allowed through.
	 *
	 * @return string
	 */
	public function render_time_unavailable() {
		return $this->render_generic_message(
			__( 'We were unable to confirm your course progress right now. Please try again shortly or contact your course administrator if this continues.', 'courseverify-student-identity-checkpoints' )
		);
	}

	/**
	 * Render a message shown when the final exam is reached before all
	 * prior checkpoints have passed.
	 *
	 * @return string
	 */
	public function render_final_exam_blocked() {
		return $this->render_generic_message(
			__( 'The final exam is not yet available. Please complete the earlier identity verification checkpoints in this course first.', 'courseverify-student-identity-checkpoints' )
		);
	}

	/**
	 * Render a plain message inside the standard CourseVerify container.
	 *
	 * @param string $message Message text.
	 * @return string
	 */
	private function render_generic_message( $message ) {
		return $this->buffer_template( 'locked', array( 'message' => $message ) );
	}

	/**
	 * Buffer-render a view file with the given variables in scope.
	 *
	 * @param string $view_name View file name, without extension, inside public/views/.
	 * @param array  $vars      Variables to extract into the template's scope.
	 * @return string
	 */
	private function buffer_template( $view_name, array $vars ) {
		$path = COURSEVERIFY_PLUGIN_DIR . 'public/views/' . $view_name . '.php';

		if ( ! file_exists( $path ) ) {
			return '';
		}

		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		ob_start();
		include $path;
		return ob_get_clean();
	}
}
