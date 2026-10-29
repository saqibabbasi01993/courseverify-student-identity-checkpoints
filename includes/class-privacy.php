<?php
/**
 * WordPress privacy tools integration.
 *
 * @package CourseVerify
 */

namespace CourseVerify;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Privacy
 *
 * Registers CourseVerify with WordPress's personal-data exporter/eraser
 * tools. Never exports or erases the hashed answer itself — only the
 * question text, verification status, timestamps, and attempt outcomes,
 * which is the same "no answer content" boundary the rest of the plugin
 * maintains.
 */
class Privacy {

	/**
	 * Register hooks.
	 *
	 * @param Loader $loader Hook loader.
	 * @return void
	 */
	public function register( Loader $loader ) {
		$loader->add_filter( 'wp_privacy_personal_data_exporters', $this, 'register_exporter' );
		$loader->add_filter( 'wp_privacy_personal_data_erasers', $this, 'register_eraser' );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array $exporters Existing exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['courseverify'] = array(
			'exporter_friendly_name' => __( 'CourseVerify', 'courseverify-student-identity-checkpoints' ),
			'callback'                => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array $erasers Existing erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['courseverify'] = array(
			'eraser_friendly_name' => __( 'CourseVerify', 'courseverify-student-identity-checkpoints' ),
			'callback'              => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Export a user's CourseVerify data: configured question text (never
	 * the answer hash), checkpoint statuses, and attempt timestamps/results.
	 *
	 * @param string $email_address User's email address.
	 * @param int    $page          Page number (unused — data volume per user is small).
	 * @return array
	 */
	public function export( $email_address, $page = 1 ) {
		global $wpdb;

		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}

		$export_items = array();

		$questions = $wpdb->get_results( $wpdb->prepare( "SELECT course_id, question_text, created_at FROM {$wpdb->prefix}courseverify_questions WHERE user_id = %d", $user->ID ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( (array) $questions as $row ) {
			$export_items[] = array(
				'group_id'    => 'courseverify_questions',
				'group_label' => __( 'CourseVerify Security Questions', 'courseverify-student-identity-checkpoints' ),
				'item_id'      => 'courseverify-question-' . $row['course_id'] . '-' . $row['created_at'],
				'data'         => array(
					array( 'name' => __( 'Course ID', 'courseverify-student-identity-checkpoints' ), 'value' => $row['course_id'] ),
					array( 'name' => __( 'Question', 'courseverify-student-identity-checkpoints' ), 'value' => $row['question_text'] ),
					array( 'name' => __( 'Configured On', 'courseverify-student-identity-checkpoints' ), 'value' => $row['created_at'] ),
				),
			);
		}

		$checkpoints = $wpdb->get_results( $wpdb->prepare( "SELECT course_id, checkpoint_key, status, attempts, updated_at FROM {$wpdb->prefix}courseverify_checkpoints WHERE user_id = %d", $user->ID ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( (array) $checkpoints as $row ) {
			$export_items[] = array(
				'group_id'    => 'courseverify_checkpoints',
				'group_label' => __( 'CourseVerify Checkpoint Status', 'courseverify-student-identity-checkpoints' ),
				'item_id'      => 'courseverify-checkpoint-' . $row['course_id'] . '-' . $row['checkpoint_key'],
				'data'         => array(
					array( 'name' => __( 'Course ID', 'courseverify-student-identity-checkpoints' ), 'value' => $row['course_id'] ),
					array( 'name' => __( 'Checkpoint', 'courseverify-student-identity-checkpoints' ), 'value' => $row['checkpoint_key'] ),
					array( 'name' => __( 'Status', 'courseverify-student-identity-checkpoints' ), 'value' => $row['status'] ),
					array( 'name' => __( 'Attempts', 'courseverify-student-identity-checkpoints' ), 'value' => $row['attempts'] ),
					array( 'name' => __( 'Last Updated', 'courseverify-student-identity-checkpoints' ), 'value' => $row['updated_at'] ),
				),
			);
		}

		return array( 'data' => $export_items, 'done' => true );
	}

	/**
	 * Erase a user's CourseVerify data on request. Removes security
	 * questions and checkpoint/attempt records. Audit-log rows referencing
	 * the user are retained by default (administrative/legal record), per
	 * the spec's guidance to consider audit/legal requirements before
	 * automatically deleting historical records — sites that want audit
	 * rows erased too can hook `courseverify_privacy_erase_audit` and
	 * return true.
	 *
	 * @param string $email_address User's email address.
	 * @param int    $page          Page number.
	 * @return array
	 */
	public function erase( $email_address, $page = 1 ) {
		global $wpdb;

		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}

		$removed = 0;

		$removed += (int) $wpdb->delete( $wpdb->prefix . 'courseverify_questions', array( 'user_id' => $user->ID ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$removed += (int) $wpdb->delete( $wpdb->prefix . 'courseverify_checkpoints', array( 'user_id' => $user->ID ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$removed += (int) $wpdb->delete( $wpdb->prefix . 'courseverify_attempts', array( 'user_id' => $user->ID ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		/**
		 * Filters whether CourseVerify audit-log rows for this user should
		 * also be erased. Defaults to false — audit history is treated as
		 * an administrative/legal record by default.
		 *
		 * @param bool   $erase_audit Whether to erase audit rows. Default false.
		 * @param string $email_address The email address being erased.
		 */
		if ( apply_filters( 'courseverify_privacy_erase_audit', false, $email_address ) ) {
			$wpdb->delete( $wpdb->prefix . 'courseverify_audit', array( 'user_id' => $user->ID ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'        => array(),
			'done'            => true,
		);
	}
}
