<?php
/**
 * Fired during plugin activation.
 *
 * @package CourseVerify
 */

namespace CourseVerify;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Activator
 *
 * Everything that needs to happen exactly once when the plugin is switched
 * on: create/upgrade tables, register capabilities, and seed default
 * settings if none exist yet. Intentionally does not perform any expensive
 * or repeated work — this only ever runs on activation.
 */
class Activator {

	/**
	 * Global settings option name.
	 *
	 * @var string
	 */
	const SETTINGS_OPTION = 'courseverify_settings';

	/**
	 * Run all activation steps.
	 *
	 * @return void
	 */
	public static function activate() {
		Database::install();
		Capabilities::add_capabilities();
		self::seed_default_settings();
	}

	/**
	 * Seed default global settings, without overwriting any existing
	 * configuration (e.g. on reactivation after a temporary deactivation).
	 *
	 * @return void
	 */
	private static function seed_default_settings() {
		if ( false !== get_option( self::SETTINGS_OPTION, false ) ) {
			return;
		}

		$defaults = array(
			'default_max_attempts'            => 3,
			'default_question_count'          => 3,
			'question_pool'                    => array(
				__( 'What was the name of your first school?', 'courseverify-student-identity-checkpoints' ),
				__( 'What city were you born in?', 'courseverify-student-identity-checkpoints' ),
				__( 'What was the name of your first pet?', 'courseverify-student-identity-checkpoints' ),
			),
			// Hours from course start; converted to seconds by the Checkpoint
			// Engine. This is only the plugin's example default — every course
			// can override its own schedule.
			'default_checkpoint_schedule_hrs' => array( 0, 5, 10, 15, 20, 25 ),
			'default_lock_on_max_failures'    => true,
			'delete_data_on_uninstall'        => false,
			'notify_students_by_email'        => false,
			'message_verification_success'    => __( 'Identity verified. You may continue your course.', 'courseverify-student-identity-checkpoints' ),
			'message_verification_failure'    => __( 'That answer was not correct. Please try again.', 'courseverify-student-identity-checkpoints' ),
			'message_locked'                  => __( 'Your course access is temporarily locked because identity verification could not be completed. Please contact your school or course administrator.', 'courseverify-student-identity-checkpoints' ),
		);

		add_option( self::SETTINGS_OPTION, $defaults );
	}
}
