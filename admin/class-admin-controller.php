<?php
/**
 * Admin menu and asset orchestration.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Admin;

use CourseVerify\Security;
use CourseVerify\Loader;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Controller
 *
 * Registers one top-level menu with submenus (Dashboard, Students, Logs,
 * Settings) and only enqueues CourseVerify's admin assets on its own
 * screens. Delegates rendering to the dedicated *_Page classes — this
 * class is wiring only.
 */
class Admin_Controller {

	const MENU_SLUG = 'courseverify';

	/**
	 * @var Dashboard_Page
	 */
	private $dashboard_page;

	/**
	 * @var Students_Page
	 */
	private $students_page;

	/**
	 * @var Logs_Page
	 */
	private $logs_page;

	/**
	 * @var Settings_Page
	 */
	private $settings_page;

	/**
	 * @var Course_Meta_Box
	 */
	private $course_meta_box;

	/**
	 * Constructor.
	 *
	 * @param Dashboard_Page  $dashboard_page  Dashboard page renderer.
	 * @param Students_Page   $students_page   Students page renderer.
	 * @param Logs_Page       $logs_page       Logs page renderer.
	 * @param Settings_Page   $settings_page   Settings page renderer.
	 * @param Course_Meta_Box $course_meta_box Course settings meta box.
	 */
	public function __construct(
		Dashboard_Page $dashboard_page,
		Students_Page $students_page,
		Logs_Page $logs_page,
		Settings_Page $settings_page,
		Course_Meta_Box $course_meta_box
	) {
		$this->dashboard_page  = $dashboard_page;
		$this->students_page   = $students_page;
		$this->logs_page       = $logs_page;
		$this->settings_page   = $settings_page;
		$this->course_meta_box = $course_meta_box;
	}

	/**
	 * Register hooks.
	 *
	 * @param Loader $loader Hook loader.
	 * @return void
	 */
	public function register( Loader $loader ) {
		$loader->add_action( 'admin_menu', $this, 'register_menu' );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'maybe_enqueue_assets' );

		$this->settings_page->register( $loader );
		$this->students_page->register( $loader );
		$this->course_meta_box->register( $loader );
	}

	/**
	 * Register the admin menu and submenus.
	 *
	 * @return void
	 */
	public function register_menu() {
		if ( ! Security::current_user_can_manage() ) {
			return;
		}

		add_menu_page(
			__( 'CourseVerify', 'courseverify-student-identity-checkpoints' ),
			__( 'CourseVerify', 'courseverify-student-identity-checkpoints' ),
			'manage_courseverify',
			self::MENU_SLUG,
			array( $this->dashboard_page, 'render' ),
			'dashicons-shield',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'courseverify-student-identity-checkpoints' ),
			__( 'Dashboard', 'courseverify-student-identity-checkpoints' ),
			'manage_courseverify',
			self::MENU_SLUG,
			array( $this->dashboard_page, 'render' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Students', 'courseverify-student-identity-checkpoints' ),
			__( 'Students', 'courseverify-student-identity-checkpoints' ),
			'manage_courseverify',
			self::MENU_SLUG . '-students',
			array( $this->students_page, 'render' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Logs', 'courseverify-student-identity-checkpoints' ),
			__( 'Logs', 'courseverify-student-identity-checkpoints' ),
			'view_courseverify_logs',
			self::MENU_SLUG . '-logs',
			array( $this->logs_page, 'render' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'courseverify-student-identity-checkpoints' ),
			__( 'Settings', 'courseverify-student-identity-checkpoints' ),
			'manage_courseverify_settings',
			self::MENU_SLUG . '-settings',
			array( $this->settings_page, 'render' )
		);
	}

	/**
	 * Enqueue admin assets only on CourseVerify's own screens.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function maybe_enqueue_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, self::MENU_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'courseverify-admin',
			COURSEVERIFY_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			COURSEVERIFY_VERSION
		);

		wp_enqueue_script(
			'courseverify-admin',
			COURSEVERIFY_PLUGIN_URL . 'admin/js/admin.js',
			array(),
			COURSEVERIFY_VERSION,
			true
		);
	}
}
