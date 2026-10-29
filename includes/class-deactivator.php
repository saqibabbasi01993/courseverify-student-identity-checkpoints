<?php
/**
 * Fired during plugin deactivation.
 *
 * @package CourseVerify
 */

namespace CourseVerify;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivator
 *
 * Deactivation must never delete data — it only stops CourseVerify's own
 * runtime scheduling. Tables, options, and settings are left untouched so
 * reactivating the plugin restores exactly where the site left off.
 */
class Deactivator {

	/**
	 * Run all deactivation steps.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// No cron events are scheduled yet as of this phase (log-retention
		// cleanup is added in a later phase); this is a placeholder so that
		// future scheduled hook(s) are unscheduled here, e.g.:
		// wp_clear_scheduled_hook( 'courseverify_log_cleanup' ).
	}
}
