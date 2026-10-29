<?php
/**
 * Core plugin orchestrator.
 *
 * @package CourseVerify
 */

namespace CourseVerify;

use CourseVerify\Repositories\Question_Repository;
use CourseVerify\Repositories\Checkpoint_Repository;
use CourseVerify\Repositories\Attempt_Repository;
use CourseVerify\Repositories\Audit_Repository;
use CourseVerify\Security\Question_Manager;
use CourseVerify\Security\Answer_Verifier;
use CourseVerify\Security\Attempt_Manager;
use CourseVerify\Checkpoints\Checkpoint_Engine;
use CourseVerify\Checkpoints\Checkpoint_Manager;
use CourseVerify\Services\Audit_Service;
use CourseVerify\Services\Verification_Service;
use CourseVerify\Services\Student_Service;
use CourseVerify\Integrations\Uncanny\Uncanny_Timer;
use CourseVerify\Frontend\Verification_UI;
use CourseVerify\Frontend\Public_Controller;
use CourseVerify\Admin\Dashboard_Page;
use CourseVerify\Admin\Students_Page;
use CourseVerify\Admin\Logs_Page;
use CourseVerify\Admin\Settings_Page;
use CourseVerify\Admin\Course_Meta_Box;
use CourseVerify\Admin\Admin_Controller;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 *
 * Bootstraps the loader, builds the plugin's object graph (repositories ->
 * integrations -> services -> checkpoint engine -> access controller ->
 * frontend/admin UI), and registers dependency-notice hooks. Deliberately
 * a plain factory rather than a DI container/service-locator framework —
 * the object graph is small and static per request, so a container would
 * be unnecessary complexity (per the spec's no-overengineering guidance).
 */
class Plugin {

	/**
	 * The hook loader.
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->loader = new Loader();
		$this->define_dependency_notice_hooks();
		$this->wire_application();
	}

	/**
	 * Register the admin-notice check for missing/inactive LearnDash and
	 * Uncanny Toolkit Pro. Runs on every admin page load but the check
	 * itself is a cheap function_exists()/class_exists() lookup, and the
	 * notice only renders to users who can act on it.
	 *
	 * @return void
	 */
	private function define_dependency_notice_hooks() {
		$this->loader->add_action( 'admin_notices', $this, 'render_dependency_notices' );
	}

	/**
	 * Build the full object graph and register each component's hooks on
	 * the loader.
	 *
	 * @return void
	 */
	private function wire_application() {

		// Repositories.
		$question_repository   = new Question_Repository();
		$checkpoint_repository = new Checkpoint_Repository();
		$attempt_repository    = new Attempt_Repository();
		$audit_repository      = new Audit_Repository();

		// Security.
		$question_manager = new Question_Manager( $question_repository );
		$answer_verifier  = new Answer_Verifier();
		$attempt_manager  = new Attempt_Manager( $attempt_repository );

		// Checkpoints.
		$checkpoint_engine  = new Checkpoint_Engine();
		$checkpoint_manager = new Checkpoint_Manager( $checkpoint_repository, $checkpoint_engine );

		// Services.
		$audit_service       = new Audit_Service( $audit_repository );
		$verification_service = new Verification_Service( $checkpoint_manager, $question_manager, $answer_verifier, $attempt_manager, $audit_service );

		/**
		 * Filters the active course-time provider. Defaults to the Uncanny
		 * Toolkit Pro adapter; a site can supply an alternative provider
		 * (implementing Contracts\Time_Provider_Interface) here.
		 *
		 * @param Contracts\Time_Provider_Interface $time_provider Default time provider.
		 */
		$time_provider = apply_filters( 'courseverify_time_provider', new Uncanny_Timer() );

		$student_service = new Student_Service( $checkpoint_manager, $checkpoint_engine, $question_manager, $audit_service, $time_provider );

		// Frontend.
		$verification_ui   = new Verification_UI( $verification_service );
		$access_controller = new Access_Controller( $student_service, $verification_ui );
		$public_controller  = new Public_Controller( $student_service, $verification_service );

		$access_controller->register( $this->loader );
		$public_controller->register( $this->loader );

		// Admin.
		$dashboard_page  = new Dashboard_Page();
		$students_page   = new Students_Page( $checkpoint_repository, $student_service );
		$logs_page       = new Logs_Page( $audit_service );
		$settings_page   = new Settings_Page();
		$course_meta_box = new Course_Meta_Box();

		$admin_controller = new Admin_Controller( $dashboard_page, $students_page, $logs_page, $settings_page, $course_meta_box );
		$admin_controller->register( $this->loader );

		// Privacy tools.
		$privacy = new Privacy();
		$privacy->register( $this->loader );
	}

	/**
	 * Render admin notices for missing dependencies.
	 *
	 * Only shown to users who could actually install/activate plugins, and
	 * never fatals the site regardless of what is or isn't active.
	 *
	 * @return void
	 */
	public function render_dependency_notices() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( ! self::is_learndash_active() ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__( 'CourseVerify requires LearnDash for course checkpoint functionality.', 'courseverify-student-identity-checkpoints' )
			);
		}

		if ( ! self::is_uncanny_timer_active() ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__( 'CourseVerify requires a supported course-time provider. Uncanny Toolkit Pro\'s Simple Course Timer is currently the supported provider.', 'courseverify-student-identity-checkpoints' )
			);
		}
	}

	/**
	 * Whether LearnDash appears to be active.
	 *
	 * @return bool
	 */
	public static function is_learndash_active() {
		return defined( 'LEARNDASH_VERSION' ) || class_exists( 'SFWD_LMS' );
	}

	/**
	 * Whether Uncanny Toolkit Pro's course timer module appears to be
	 * active. This check will be refined in Phase 6 once the actual
	 * data source behind [uo_time] has been confirmed against the
	 * installed version.
	 *
	 * @return bool
	 */
	public static function is_uncanny_timer_active() {
		return shortcode_exists( 'uo_time' );
	}

	/**
	 * Run the plugin — registers all hooks collected on the loader.
	 *
	 * @return void
	 */
	public function run() {
		$this->loader->run();
	}
}
