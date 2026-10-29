<?php
/**
 * Uninstall handler for CourseVerify.
 *
 * Deliberately conservative: CourseVerify data is only removed if the site
 * owner explicitly opted in via the "Delete all CourseVerify data when
 * plugin is uninstalled" setting. The default is to preserve data, since
 * uninstall can happen accidentally and verification/audit history may be
 * needed for institutional record-keeping.
 *
 * Everything runs inside a single prefixed function rather than at the
 * file's top level, so none of its working variables are treated as
 * plugin-defined globals.
 *
 * @package CourseVerify
 */

// If uninstall is not called from WordPress, bail.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Run the CourseVerify uninstall routine.
 *
 * @return void
 */
function courseverify_run_uninstall() {
	$courseverify_settings = get_option( 'courseverify_settings', array() );

	$courseverify_delete_data = ! empty( $courseverify_settings['delete_data_on_uninstall'] );

	if ( ! $courseverify_delete_data ) {
		return;
	}

	global $wpdb;

	// Table names come only from $wpdb->prefix — a trusted, plugin-controlled
	// value — never from request input or option content. Table names can't
	// be parameterized with $wpdb->prepare(), which is why this is
	// deliberately suppressed rather than "fixed" with a placeholder that
	// doesn't apply here.
	$courseverify_tables = array(
		$wpdb->prefix . 'courseverify_questions',
		$wpdb->prefix . 'courseverify_checkpoints',
		$wpdb->prefix . 'courseverify_attempts',
		$wpdb->prefix . 'courseverify_audit',
	);

	foreach ( $courseverify_tables as $courseverify_table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$courseverify_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	}

	delete_option( 'courseverify_settings' );
	delete_option( 'courseverify_db_version' );

	// Remove custom capabilities from the administrator role. Only done
	// here, not on plain deactivation, so a temporary deactivation never
	// strips admin access.
	if ( function_exists( 'get_role' ) ) {
		$courseverify_role = get_role( 'administrator' );

		if ( $courseverify_role ) {
			$courseverify_caps = array(
				'manage_courseverify',
				'view_courseverify_logs',
				'manage_courseverify_settings',
				'unlock_courseverify_student',
			);

			foreach ( $courseverify_caps as $courseverify_cap ) {
				$courseverify_role->remove_cap( $courseverify_cap );
			}
		}
	}
}

courseverify_run_uninstall();
