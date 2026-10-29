<?php
/**
 * Database schema definition and installer for CourseVerify.
 *
 * @package CourseVerify
 */

namespace CourseVerify;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Database
 *
 * Owns table-creation SQL and schema migrations. Contains no business logic
 * and is only ever called from the Activator (and, in future, from a
 * migration routine triggered on version-mismatch detection).
 */
class Database {

	/**
	 * Option name storing the currently installed schema version.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'courseverify_db_version';

	/**
	 * Create or upgrade all CourseVerify database tables.
	 *
	 * Safe to call on every activation; dbDelta() only applies the diff
	 * between the declared schema and what currently exists.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		foreach ( self::get_schema_sql( $charset_collate ) as $sql ) {
			dbDelta( $sql );
		}

		update_option( self::DB_VERSION_OPTION, COURSEVERIFY_DB_VERSION );
	}

	/**
	 * Build the CREATE TABLE statements for every CourseVerify table.
	 *
	 * Table names are built only from $wpdb->prefix, a trusted value, never
	 * from request input.
	 *
	 * @param string $charset_collate Result of $wpdb->get_charset_collate().
	 * @return string[] Array of CREATE TABLE statements, one per table.
	 */
	private static function get_schema_sql( $charset_collate ) {
		global $wpdb;

		$questions_table   = $wpdb->prefix . 'courseverify_questions';
		$checkpoints_table = $wpdb->prefix . 'courseverify_checkpoints';
		$attempts_table    = $wpdb->prefix . 'courseverify_attempts';
		$audit_table       = $wpdb->prefix . 'courseverify_audit';

		$sql = array();

		// Security questions. Answers are always stored as a wp_hash_password()
		// hash — never plaintext, never a reversible cipher.
		$sql[] = "CREATE TABLE {$questions_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			course_id BIGINT UNSIGNED NOT NULL,
			question_key VARCHAR(64) NOT NULL,
			question_text TEXT NOT NULL,
			answer_hash VARCHAR(255) NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY course_id (course_id),
			KEY user_course (user_id,course_id)
		) {$charset_collate};";

		// Checkpoint state. `status` is validated at the application layer
		// against an explicit allow-list before every write; the column type
		// is intentionally a plain VARCHAR rather than a MySQL ENUM so the
		// allow-list can evolve without a schema migration.
		$sql[] = "CREATE TABLE {$checkpoints_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			course_id BIGINT UNSIGNED NOT NULL,
			checkpoint_key VARCHAR(64) NOT NULL,
			threshold_seconds INT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'not_reached',
			attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			required_at DATETIME NULL DEFAULT NULL,
			verified_at DATETIME NULL DEFAULT NULL,
			locked_at DATETIME NULL DEFAULT NULL,
			unlocked_at DATETIME NULL DEFAULT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY course_id (course_id),
			KEY user_course (user_id,course_id),
			KEY status (status),
			KEY checkpoint_key (checkpoint_key)
		) {$charset_collate};";

		// Verification attempts. Deliberately has no column for the submitted
		// answer — only the outcome is ever recorded.
		$sql[] = "CREATE TABLE {$attempts_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			course_id BIGINT UNSIGNED NOT NULL,
			checkpoint_id BIGINT UNSIGNED NOT NULL,
			result VARCHAR(20) NOT NULL,
			attempted_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY course_id (course_id),
			KEY checkpoint_id (checkpoint_id)
		) {$charset_collate};";

		// Administrative / system audit trail. No answer content, no IP
		// addresses, no more personal data than is needed to explain what
		// happened and who did it.
		$sql[] = "CREATE TABLE {$audit_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			course_id BIGINT UNSIGNED NOT NULL,
			checkpoint_key VARCHAR(64) NULL DEFAULT NULL,
			event_type VARCHAR(64) NOT NULL,
			actor_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			message TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY course_id (course_id),
			KEY event_type (event_type)
		) {$charset_collate};";

		return $sql;
	}

	/**
	 * Allow-list of valid checkpoint statuses.
	 *
	 * Central definition so repositories/services can validate against it
	 * instead of each maintaining their own copy.
	 *
	 * @return string[]
	 */
	public static function get_allowed_checkpoint_statuses() {
		return array(
			'not_reached',
			'required',
			'in_progress',
			'passed',
			'failed',
			'locked',
			'admin_unlocked',
		);
	}

	/**
	 * Allow-list of valid attempt results.
	 *
	 * @return string[]
	 */
	public static function get_allowed_attempt_results() {
		return array(
			'success',
			'failure',
			'locked',
			'admin_unlock',
		);
	}
}
