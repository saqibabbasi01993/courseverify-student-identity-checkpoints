<?php
/**
 * Capability definitions for CourseVerify.
 *
 * @package CourseVerify
 */

namespace CourseVerify;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Capabilities
 *
 * Centralizes CourseVerify's custom capabilities so admin/AJAX/REST code
 * never hard-codes a capability string directly.
 */
class Capabilities {

	const MANAGE         = 'manage_courseverify';
	const VIEW_LOGS      = 'view_courseverify_logs';
	const MANAGE_SETTINGS = 'manage_courseverify_settings';
	const UNLOCK_STUDENT  = 'unlock_courseverify_student';

	/**
	 * All CourseVerify capabilities.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array(
			self::MANAGE,
			self::VIEW_LOGS,
			self::MANAGE_SETTINGS,
			self::UNLOCK_STUDENT,
		);
	}

	/**
	 * Grant all CourseVerify capabilities to the administrator role.
	 *
	 * Called on activation only. Does not touch any other role — sites that
	 * want to grant these to additional roles (e.g. a custom "school admin"
	 * role) can do so themselves, or via the
	 * `courseverify_default_capability_roles` filter in a future phase.
	 *
	 * @return void
	 */
	public static function add_capabilities() {
		$role = get_role( 'administrator' );

		if ( ! $role ) {
			return;
		}

		foreach ( self::all() as $capability ) {
			$role->add_cap( $capability );
		}
	}

	/**
	 * Remove all CourseVerify capabilities from the administrator role.
	 *
	 * Intentionally NOT called on deactivation — capabilities are left in
	 * place so a temporary deactivation doesn't quietly strip admin access.
	 * This is only invoked from uninstall.php, and only when the site owner
	 * has opted in to full data removal.
	 *
	 * @return void
	 */
	public static function remove_capabilities() {
		$role = get_role( 'administrator' );

		if ( ! $role ) {
			return;
		}

		foreach ( self::all() as $capability ) {
			$role->remove_cap( $capability );
		}
	}
}
