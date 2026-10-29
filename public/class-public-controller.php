<?php
/**
 * Frontend controller: conditional asset loading and AJAX endpoints.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Frontend;

use CourseVerify\Course_Settings;
use CourseVerify\Security;
use CourseVerify\Loader;
use CourseVerify\Integrations\LearnDash;
use CourseVerify\Services\Student_Service;
use CourseVerify\Services\Verification_Service;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Public_Controller
 *
 * Every AJAX handler here follows the same shape: verify the
 * action-specific nonce, require a logged-in user, derive the student
 * strictly from get_current_user_id() (never from request input), verify
 * course enrollment, sanitize input, delegate to a service, and return
 * wp_send_json_success()/wp_send_json_error(). No handler ever decides
 * pass/fail itself — that stays in Verification_Service.
 */
class Public_Controller {

	/**
	 * @var Student_Service
	 */
	private $students;

	/**
	 * @var Verification_Service
	 */
	private $verification;

	/**
	 * Constructor.
	 *
	 * @param Student_Service       $students     Student service instance.
	 * @param Verification_Service  $verification Verification service instance.
	 */
	public function __construct( Student_Service $students, Verification_Service $verification ) {
		$this->students     = $students;
		$this->verification = $verification;
	}

	/**
	 * Register hooks.
	 *
	 * @param Loader $loader Hook loader.
	 * @return void
	 */
	public function register( Loader $loader ) {
		$loader->add_action( 'wp_enqueue_scripts', $this, 'maybe_enqueue_assets' );

		$loader->add_action( 'wp_ajax_courseverify_save_setup', $this, 'ajax_save_setup' );
		$loader->add_action( 'wp_ajax_courseverify_get_checkpoint', $this, 'ajax_get_checkpoint' );
		$loader->add_action( 'wp_ajax_courseverify_submit_verification', $this, 'ajax_submit_verification' );
		$loader->add_action( 'wp_ajax_courseverify_check_status', $this, 'ajax_check_status' );
	}

	/**
	 * Enqueue frontend assets only on singular, CourseVerify-protected
	 * LearnDash content pages.
	 *
	 * @return void
	 */
	public function maybe_enqueue_assets() {
		if ( ! is_singular() || ! LearnDash::is_active() ) {
			return;
		}

		$post_id = get_queried_object_id();

		if ( '' === LearnDash::get_content_type( $post_id ) ) {
			return;
		}

		$course_id = LearnDash::get_course_id( $post_id );

		if ( ! $course_id || ! Course_Settings::is_enabled( $course_id ) ) {
			return;
		}

		$user_id  = get_current_user_id();
		$is_admin = user_can( $user_id, 'manage_options' ) || user_can( $user_id, \CourseVerify\Capabilities::MANAGE );

		if ( apply_filters( 'courseverify_bypass_checkpoints', $is_admin, $user_id ) ) {
			return;
		}

		wp_enqueue_style(
			'courseverify-frontend',
			COURSEVERIFY_PLUGIN_URL . 'public/css/frontend.css',
			array(),
			COURSEVERIFY_VERSION
		);

		wp_enqueue_script(
			'courseverify-frontend',
			COURSEVERIFY_PLUGIN_URL . 'public/js/frontend.js',
			array(),
			COURSEVERIFY_VERSION,
			true
		);

		wp_localize_script(
			'courseverify-frontend',
			'CourseVerifyFrontend',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonceSaveSetup'   => wp_create_nonce( Security::NONCE_SAVE_SETUP ),
				'nonceVerify'      => wp_create_nonce( Security::NONCE_VERIFY_ANSWER ),
				'nonceCheckStatus' => wp_create_nonce( Security::NONCE_CHECK_STATUS ),
			)
		);
	}

	/**
	 * AJAX: save the student's initial security-question setup.
	 *
	 * @return void
	 */
	public function ajax_save_setup() {
		if ( ! Security::verify_ajax_nonce( Security::NONCE_SAVE_SETUP ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'courseverify-student-identity-checkpoints' ) ), 403 );
		}

		$user_id   = get_current_user_id();
		$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $course_id || ! LearnDash::is_user_enrolled( $user_id, $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not enrolled in this course.', 'courseverify-student-identity-checkpoints' ) ), 403 );
		}

		$raw_questions = isset( $_POST['questions'] ) && is_array( $_POST['questions'] ) ? wp_unslash( $_POST['questions'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$pairs = array();
		foreach ( $raw_questions as $entry ) {
			$pairs[] = array(
				'question_text' => isset( $entry['question_text'] ) ? sanitize_text_field( $entry['question_text'] ) : '',
				'answer'        => isset( $entry['answer'] ) ? sanitize_text_field( $entry['answer'] ) : '',
			);
		}

		$result = $this->students->complete_setup( $user_id, $course_id, $pairs );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Setup complete.', 'courseverify-student-identity-checkpoints' ) ) );
	}

	/**
	 * AJAX: get the current checkpoint status (and question, if a
	 * checkpoint is currently in progress) for the student.
	 *
	 * @return void
	 */
	public function ajax_get_checkpoint() {
		if ( ! Security::verify_ajax_nonce( Security::NONCE_CHECK_STATUS ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'courseverify-student-identity-checkpoints' ) ), 403 );
		}

		$user_id   = get_current_user_id();
		$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $course_id || ! LearnDash::is_user_enrolled( $user_id, $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not enrolled in this course.', 'courseverify-student-identity-checkpoints' ) ), 403 );
		}

		$status = $this->students->get_status( $user_id, $course_id );

		if ( 'blocked' !== $status['state'] || 'locked' === $status['checkpoint']['status'] ) {
			wp_send_json_success( array( 'state' => $status['state'] ) );
		}

		$verification = $this->verification->start_verification( $user_id, $course_id, $status['checkpoint'] );

		if ( is_wp_error( $verification ) ) {
			wp_send_json_success( array( 'state' => 'locked' ) );
		}

		wp_send_json_success(
			array(
				'state'          => 'blocked',
				'checkpoint_key' => $status['checkpoint']['checkpoint_key'],
				'question_id'    => $verification['question_id'],
				'question_text'  => $verification['question_text'],
			)
		);
	}

	/**
	 * AJAX: submit an answer for the current checkpoint.
	 *
	 * @return void
	 */
	public function ajax_submit_verification() {
		if ( ! Security::verify_ajax_nonce( Security::NONCE_VERIFY_ANSWER ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'courseverify-student-identity-checkpoints' ) ), 403 );
		}

		$user_id        = get_current_user_id();
		$course_id      = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$checkpoint_key = isset( $_POST['checkpoint_key'] ) ? sanitize_key( wp_unslash( $_POST['checkpoint_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$question_id    = isset( $_POST['question_id'] ) ? absint( $_POST['question_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$answer         = isset( $_POST['answer'] ) ? sanitize_text_field( wp_unslash( $_POST['answer'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $course_id || ! $checkpoint_key || ! LearnDash::is_user_enrolled( $user_id, $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not enrolled in this course.', 'courseverify-student-identity-checkpoints' ) ), 403 );
		}

		// Re-fetch fresh state immediately before verifying — never trust
		// whatever the client believed the state to be.
		$checkpoint_row = $this->students->get_checkpoint_row( $user_id, $course_id, $checkpoint_key );

		if ( ! $checkpoint_row ) {
			wp_send_json_error( array( 'message' => __( 'Checkpoint not found.', 'courseverify-student-identity-checkpoints' ) ), 404 );
		}

		$max_attempts = Course_Settings::get_max_attempts( $course_id );

		$result = $this->verification->submit_answer( $user_id, $course_id, $checkpoint_row, $question_id, $answer, $max_attempts );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: lightweight status poll (UX only — the server remains
	 * authoritative on every real content request regardless of this).
	 *
	 * @return void
	 */
	public function ajax_check_status() {
		$this->ajax_get_checkpoint();
	}
}
