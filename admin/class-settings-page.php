<?php
/**
 * Global settings screen.
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
 * Class Settings_Page
 *
 * Global defaults, registered through the WordPress Settings API rather
 * than a custom settings framework. Per-course overrides live on the
 * course edit screen (Course_Meta_Box), not here.
 */
class Settings_Page {

	const OPTION_GROUP = 'courseverify_settings_group';
	const OPTION_NAME  = 'courseverify_settings';

	/**
	 * Register hooks.
	 *
	 * @param Loader $loader Hook loader.
	 * @return void
	 */
	public function register( Loader $loader ) {
		$loader->add_action( 'admin_init', $this, 'register_settings' );
	}

	/**
	 * Register the setting, section, and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);

		add_settings_section(
			'courseverify_main_section',
			__( 'Default Settings', 'courseverify-student-identity-checkpoints' ),
			'__return_false',
			self::MENU_SLUG()
		);

		$fields = array(
			'default_max_attempts'            => __( 'Default Maximum Attempts', 'courseverify-student-identity-checkpoints' ),
			'default_question_count'          => __( 'Default Number of Security Questions', 'courseverify-student-identity-checkpoints' ),
			'question_pool'                   => __( 'Security Question Pool (one per line)', 'courseverify-student-identity-checkpoints' ),
			'default_checkpoint_schedule_hrs' => __( 'Default Checkpoint Schedule (hours, comma-separated)', 'courseverify-student-identity-checkpoints' ),
			'notify_students_by_email'        => __( 'Notify students by email when their security questions are reset', 'courseverify-student-identity-checkpoints' ),
			'delete_data_on_uninstall'        => __( 'Delete all CourseVerify data when plugin is uninstalled', 'courseverify-student-identity-checkpoints' ),
			'message_verification_success'    => __( 'Verification Success Message', 'courseverify-student-identity-checkpoints' ),
			'message_verification_failure'    => __( 'Verification Failure Message', 'courseverify-student-identity-checkpoints' ),
			'message_locked'                  => __( 'Locked Message', 'courseverify-student-identity-checkpoints' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				$key,
				$label,
				array( $this, 'render_field' ),
				self::MENU_SLUG(),
				'courseverify_main_section',
				array( 'key' => $key )
			);
		}
	}

	/**
	 * The settings page slug (kept in sync with Admin_Controller's menu).
	 *
	 * @return string
	 */
	private static function MENU_SLUG() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return Admin_Controller::MENU_SLUG . '-settings';
	}

	/**
	 * Render a single settings field.
	 *
	 * @param array $args {
	 *     @type string $key Setting key within the courseverify_settings option.
	 * }
	 * @return void
	 */
	public function render_field( array $args ) {
		$key      = $args['key'];
		$settings = get_option( self::OPTION_NAME, array() );
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';

		$name = self::OPTION_NAME . '[' . $key . ']';

		if ( 'delete_data_on_uninstall' === $key ) {
			printf(
				'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
				esc_attr( $name ),
				checked( ! empty( $value ), true, false ),
				esc_html__( 'Off by default — data is preserved unless explicitly opted in.', 'courseverify-student-identity-checkpoints' )
			);
			return;
		}

		if ( 'notify_students_by_email' === $key ) {
			printf(
				'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
				esc_attr( $name ),
				checked( ! empty( $value ), true, false ),
				esc_html__( 'Off by default. When enabled, resetting a student\'s security questions also emails them to let them know — never includes any answer content.', 'courseverify-student-identity-checkpoints' )
			);
			return;
		}

		if ( 'question_pool' === $key ) {
			$display = is_array( $value ) ? implode( "\n", $value ) : '';
			printf(
				'<textarea class="large-text" rows="6" name="%1$s" placeholder="%2$s">%3$s</textarea><p class="description">%4$s</p>',
				esc_attr( $name ),
				esc_attr__( "What was the name of your first school?\nWhat city were you born in?\nWhat was the name of your first pet?", 'courseverify-student-identity-checkpoints' ),
				esc_textarea( $display ),
				esc_html__( 'Students will be asked to answer questions selected from this pool — they never write their own question text.', 'courseverify-student-identity-checkpoints' )
			);
			return;
		}

		if ( 'default_checkpoint_schedule_hrs' === $key ) {
			$display = is_array( $value ) ? implode( ', ', array_map( 'absint', $value ) ) : '';
			printf(
				'<input type="text" class="regular-text" name="%1$s" value="%2$s" placeholder="0, 5, 10, 15, 20, 25" />',
				esc_attr( $name ),
				esc_attr( $display )
			);
			return;
		}

		if ( in_array( $key, array( 'message_verification_success', 'message_verification_failure', 'message_locked' ), true ) ) {
			printf(
				'<textarea class="large-text" rows="2" name="%1$s">%2$s</textarea>',
				esc_attr( $name ),
				esc_textarea( $value )
			);
			return;
		}

		printf(
			'<input type="number" min="1" class="small-text" name="%1$s" value="%2$s" />',
			esc_attr( $name ),
			esc_attr( $value )
		);
	}

	/**
	 * Sanitize the full settings array on save.
	 *
	 * @param array $input Raw submitted settings.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();

		$hours = array();
		if ( ! empty( $input['default_checkpoint_schedule_hrs'] ) ) {
			foreach ( explode( ',', $input['default_checkpoint_schedule_hrs'] ) as $hour ) {
				$hour = trim( $hour );
				if ( is_numeric( $hour ) ) {
					$hours[] = absint( $hour );
				}
			}
		}
		$hours = array_values( array_unique( $hours ) );
		sort( $hours, SORT_NUMERIC );

		$pool = array();
		if ( ! empty( $input['question_pool'] ) ) {
			foreach ( preg_split( '/\r\n|\r|\n/', $input['question_pool'] ) as $line ) {
				$line = sanitize_text_field( trim( $line ) );
				if ( '' !== $line ) {
					$pool[] = $line;
				}
			}
		}

		return array(
			'default_max_attempts'            => max( 1, absint( $input['default_max_attempts'] ?? 3 ) ),
			'default_question_count'          => max( 1, absint( $input['default_question_count'] ?? 3 ) ),
			'question_pool'                    => $pool,
			'default_checkpoint_schedule_hrs' => $hours ? $hours : array( 0, 5, 10, 15, 20, 25 ),
			'default_lock_on_max_failures'    => true,
			'delete_data_on_uninstall'        => ! empty( $input['delete_data_on_uninstall'] ),
			'notify_students_by_email'        => ! empty( $input['notify_students_by_email'] ),
			'message_verification_success'    => sanitize_textarea_field( $input['message_verification_success'] ?? '' ),
			'message_verification_failure'    => sanitize_textarea_field( $input['message_verification_failure'] ?? '' ),
			'message_locked'                  => sanitize_textarea_field( $input['message_locked'] ?? '' ),
		);
	}

	/**
	 * Render the settings screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! Security::current_user_can_manage_settings() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'courseverify-student-identity-checkpoints' ) );
		}

		$page_slug = self::MENU_SLUG();
		include COURSEVERIFY_PLUGIN_DIR . 'admin/views/settings.php';
	}
}
