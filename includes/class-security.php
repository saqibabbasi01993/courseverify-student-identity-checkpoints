<?php
/**
 * Shared security helpers.
 *
 * @package CourseVerify
 */

namespace CourseVerify;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Security
 *
 * Centralizes the plugin's action-specific nonce names so they're defined
 * once. Nonces here are always paired with a capability and/or ownership
 * check by the caller — this class never treats a valid nonce as
 * sufficient authorization on its own.
 */
class Security {

	const NONCE_VERIFY_ANSWER   = 'courseverify_verify_answer';
	const NONCE_SAVE_SETUP      = 'courseverify_save_setup';
	const NONCE_SAVE_SETTINGS   = 'courseverify_save_settings';
	const NONCE_UNLOCK_STUDENT  = 'courseverify_unlock_student';
	const NONCE_RESET_CHECKPOINT = 'courseverify_reset_checkpoint';
	const NONCE_RESET_QUESTIONS  = 'courseverify_reset_questions';
	const NONCE_CHECK_STATUS     = 'courseverify_check_status';

	/**
	 * Verify a nonce sent via AJAX for a specific action. Does not perform
	 * a capability or ownership check — callers must do that separately.
	 *
	 * @param string $action     One of the NONCE_* constants.
	 * @param string $query_arg  $_REQUEST key the nonce was sent under. Default 'nonce'.
	 * @return bool
	 */
	public static function verify_ajax_nonce( $action, $query_arg = 'nonce' ) {
		return (bool) check_ajax_referer( $action, $query_arg, false );
	}

	/**
	 * Whether the current user may manage CourseVerify generally.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage() {
		return current_user_can( Capabilities::MANAGE );
	}

	/**
	 * Whether the current user may unlock students.
	 *
	 * @return bool
	 */
	public static function current_user_can_unlock() {
		return current_user_can( Capabilities::UNLOCK_STUDENT ) || current_user_can( Capabilities::MANAGE );
	}

	/**
	 * Whether the current user may view logs.
	 *
	 * @return bool
	 */
	public static function current_user_can_view_logs() {
		return current_user_can( Capabilities::VIEW_LOGS ) || current_user_can( Capabilities::MANAGE );
	}

	/**
	 * Whether the current user may manage settings.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage_settings() {
		return current_user_can( Capabilities::MANAGE_SETTINGS ) || current_user_can( Capabilities::MANAGE );
	}
}
