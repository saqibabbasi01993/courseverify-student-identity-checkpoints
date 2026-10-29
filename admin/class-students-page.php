<?php
/**
 * Students admin screen.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Admin;

use CourseVerify\Security;
use CourseVerify\Loader;
use CourseVerify\Repositories\Checkpoint_Repository;
use CourseVerify\Services\Student_Service;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Students_Page
 *
 * Lists checkpoint rows across students/courses with filtering and
 * pagination, and handles the admin actions (unlock, reset checkpoint,
 * reset questions) via admin-post.php — the standard WordPress pattern for
 * a state-changing admin action, each protected by its own nonce plus a
 * capability check (nonce alone is never treated as authorization).
 */
class Students_Page {

	/**
	 * @var Checkpoint_Repository
	 */
	private $checkpoint_repository;

	/**
	 * @var Student_Service
	 */
	private $students;

	/**
	 * Constructor.
	 *
	 * @param Checkpoint_Repository $checkpoint_repository Checkpoint repository instance.
	 * @param Student_Service       $students              Student service instance.
	 */
	public function __construct( Checkpoint_Repository $checkpoint_repository, Student_Service $students ) {
		$this->checkpoint_repository = $checkpoint_repository;
		$this->students               = $students;
	}

	/**
	 * Register hooks.
	 *
	 * @param Loader $loader Hook loader.
	 * @return void
	 */
	public function register( Loader $loader ) {
		$loader->add_action( 'admin_post_courseverify_unlock_student', $this, 'handle_unlock' );
		$loader->add_action( 'admin_post_courseverify_reset_checkpoint', $this, 'handle_reset_checkpoint' );
		$loader->add_action( 'admin_post_courseverify_reset_questions', $this, 'handle_reset_questions' );
	}

	/**
	 * Render the Students screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! Security::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'courseverify-student-identity-checkpoints' ) );
		}

		$page      = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status    = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$rows = $this->checkpoint_repository->get_filtered(
			array(
				'status'    => $status,
				'course_id' => $course_id,
				'page'      => $page,
				'per_page'  => 20,
			)
		);

		$statuses = array( 'not_reached', 'required', 'in_progress', 'passed', 'failed', 'locked', 'admin_unlocked' );

		include COURSEVERIFY_PLUGIN_DIR . 'admin/views/students.php';
	}

	/**
	 * Handle the "unlock student" admin action.
	 *
	 * @return void
	 */
	public function handle_unlock() {
		$this->handle_action( 'courseverify_unlock_student', Security::NONCE_UNLOCK_STUDENT, function ( $user_id, $course_id, $checkpoint_key, $reason ) {
			if ( ! Security::current_user_can_unlock() ) {
				wp_die( esc_html__( 'You do not have permission to perform this action.', 'courseverify-student-identity-checkpoints' ) );
			}
			$this->students->admin_unlock( $user_id, $course_id, $checkpoint_key, get_current_user_id(), $reason );
		} );
	}

	/**
	 * Handle the "reset checkpoint" admin action.
	 *
	 * @return void
	 */
	public function handle_reset_checkpoint() {
		$this->handle_action( 'courseverify_reset_checkpoint', Security::NONCE_RESET_CHECKPOINT, function ( $user_id, $course_id, $checkpoint_key, $reason ) {
			if ( ! Security::current_user_can_manage() ) {
				wp_die( esc_html__( 'You do not have permission to perform this action.', 'courseverify-student-identity-checkpoints' ) );
			}
			$this->students->admin_reset_checkpoint( $user_id, $course_id, $checkpoint_key, get_current_user_id(), $reason );
		} );
	}

	/**
	 * Handle the "reset security questions" admin action.
	 *
	 * @return void
	 */
	public function handle_reset_questions() {
		$this->handle_action( 'courseverify_reset_questions', Security::NONCE_RESET_QUESTIONS, function ( $user_id, $course_id, $checkpoint_key, $reason ) {
			if ( ! Security::current_user_can_manage() ) {
				wp_die( esc_html__( 'You do not have permission to perform this action.', 'courseverify-student-identity-checkpoints' ) );
			}
			$this->students->admin_reset_questions( $user_id, $course_id, get_current_user_id(), $reason );
		} );
	}

	/**
	 * Shared admin-post handler shape: verify nonce, extract/sanitize
	 * fields, run the callback, then redirect back to the Students screen.
	 *
	 * @param string   $action_name Form action name (also the query var for the nonce).
	 * @param string   $nonce_action Nonce action constant.
	 * @param callable $callback     Receives ($user_id, $course_id, $checkpoint_key, $reason).
	 * @return void
	 */
	private function handle_action( $action_name, $nonce_action, callable $callback ) {
		check_admin_referer( $nonce_action );

		$user_id        = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$course_id      = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		$checkpoint_key = isset( $_POST['checkpoint_key'] ) ? sanitize_key( wp_unslash( $_POST['checkpoint_key'] ) ) : '';
		$reason         = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';

		if ( $user_id && $course_id ) {
			$callback( $user_id, $course_id, $checkpoint_key, $reason );
		}

		wp_safe_redirect( add_query_arg( 'courseverify_notice', 'done', wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=' . Admin_Controller::MENU_SLUG . '-students' ) ) );
		exit;
	}
}
