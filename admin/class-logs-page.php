<?php
/**
 * Logs admin screen.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Admin;

use CourseVerify\Security;
use CourseVerify\Services\Audit_Service;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Logs_Page
 */
class Logs_Page {

	/**
	 * @var Audit_Service
	 */
	private $audit;

	/**
	 * Constructor.
	 *
	 * @param Audit_Service $audit Audit service instance.
	 */
	public function __construct( Audit_Service $audit ) {
		$this->audit = $audit;
	}

	/**
	 * Render the Logs screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! Security::current_user_can_view_logs() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'courseverify-student-identity-checkpoints' ) );
		}

		$page      = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$user_id   = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$rows = $this->audit->get_filtered(
			array(
				'course_id' => $course_id,
				'user_id'   => $user_id,
				'page'      => $page,
				'per_page'  => 30,
			)
		);

		include COURSEVERIFY_PLUGIN_DIR . 'admin/views/logs.php';
	}
}
