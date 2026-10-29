<?php
/**
 * Per-course CourseVerify settings meta box.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Admin;

use CourseVerify\Course_Settings;
use CourseVerify\Security;
use CourseVerify\Loader;
use CourseVerify\Integrations\LearnDash;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Course_Meta_Box
 */
class Course_Meta_Box {

	const NONCE_ACTION = 'courseverify_save_course_settings';
	const NONCE_NAME   = 'courseverify_course_settings_nonce';
	const META_BOX_ID  = 'courseverify_course_settings';
	const SCREEN_ID    = 'sfwd-courses';

	/**
	 * Register hooks.
	 *
	 * @param Loader $loader Hook loader.
	 * @return void
	 */
	public function register( Loader $loader ) {
		$loader->add_action( 'add_meta_boxes', $this, 'add_meta_box' );
		$loader->add_action( 'save_post_sfwd-courses', $this, 'save', 10, 1 );

		// Keep the box visible by default rather than depending on every
		// admin manually re-checking it in Screen Options. Sites that
		// already had Screen Options preferences saved for the course
		// screen (common once several plugins/theme add their own boxes)
		// otherwise default any newly-added box to hidden until someone
		// happens to notice and re-enable it.
		$loader->add_filter( 'default_hidden_meta_boxes', $this, 'exclude_from_default_hidden', 10, 2 );
		$loader->add_action( 'load-post.php', $this, 'force_visible_for_current_user' );
		$loader->add_action( 'load-post-new.php', $this, 'force_visible_for_current_user' );
	}

	/**
	 * Make sure our box is never in WordPress's default-hidden list for
	 * the course screen (covers users/screens with no saved Screen
	 * Options preferences yet).
	 *
	 * @param string[]   $hidden Default hidden meta box IDs.
	 * @param \WP_Screen $screen Current screen.
	 * @return string[]
	 */
	public function exclude_from_default_hidden( $hidden, $screen ) {
		if ( ! $screen instanceof \WP_Screen || self::SCREEN_ID !== $screen->post_type ) {
			return $hidden;
		}

		return array_values( array_diff( (array) $hidden, array( self::META_BOX_ID ) ) );
	}

	/**
	 * Remove our box from the CURRENT user's already-saved hidden-meta-box
	 * list, if it's there. This runs on every course edit/new-course page
	 * load, so the box self-heals to visible even for admins who had
	 * screen preferences saved before this plugin was installed, without
	 * needing a one-off migration across every user account.
	 *
	 * @return void
	 */
	public function force_visible_for_current_user() {
		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen || self::SCREEN_ID !== $screen->post_type ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		$hidden_meta_key = 'metaboxhidden_' . $screen->id;
		$hidden          = get_user_meta( $user_id, $hidden_meta_key, true );

		if ( ! is_array( $hidden ) || ! in_array( self::META_BOX_ID, $hidden, true ) ) {
			return;
		}

		$hidden = array_values( array_diff( $hidden, array( self::META_BOX_ID ) ) );
		update_user_meta( $user_id, $hidden_meta_key, $hidden );
	}

	/**
	 * Register the meta box on the LearnDash course edit screen.
	 *
	 * @return void
	 */
	public function add_meta_box() {
		if ( ! LearnDash::is_active() || ! Security::current_user_can_manage() ) {
			return;
		}

		add_meta_box(
			self::META_BOX_ID,
			__( 'CourseVerify — Identity Checkpoints', 'courseverify-student-identity-checkpoints' ),
			array( $this, 'render' ),
			self::SCREEN_ID,
			'normal',
			'default'
		);
	}

	/**
	 * Render the meta box.
	 *
	 * @param \WP_Post $post Current course post.
	 * @return void
	 */
	public function render( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$course_id           = $post->ID;
		$enabled             = Course_Settings::is_enabled( $course_id );
		$question_count      = Course_Settings::get_question_count( $course_id );
		$hours               = Course_Settings::get_checkpoint_hours( $course_id );
		$max_attempts        = Course_Settings::get_max_attempts( $course_id );
		$lock_on_failure     = Course_Settings::locks_on_failure( $course_id );
		$final_exam_enabled  = Course_Settings::is_final_exam_enabled( $course_id );
		$final_exam_quiz_id  = Course_Settings::get_final_exam_quiz_id( $course_id );
		$quiz_ids            = LearnDash::get_course_quiz_ids( $course_id );

		include COURSEVERIFY_PLUGIN_DIR . 'admin/views/course-meta-box.php';
	}

	/**
	 * Save the meta box on course save.
	 *
	 * @param int $post_id Course post ID.
	 * @return void
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! Security::current_user_can_manage() ) {
			return;
		}

		$hours_raw = isset( $_POST['courseverify_checkpoint_hours'] ) ? sanitize_text_field( wp_unslash( $_POST['courseverify_checkpoint_hours'] ) ) : '';
		$hours     = array_filter( array_map( 'trim', explode( ',', $hours_raw ) ), 'is_numeric' );

		Course_Settings::save_from_submission(
			$post_id,
			array(
				'enabled'             => ! empty( $_POST['courseverify_enabled'] ),
				'question_count'      => isset( $_POST['courseverify_question_count'] ) ? absint( $_POST['courseverify_question_count'] ) : 3,
				'checkpoint_hours'    => $hours,
				'max_attempts'        => isset( $_POST['courseverify_max_attempts'] ) ? absint( $_POST['courseverify_max_attempts'] ) : 3,
				'lock_on_failure'     => ! empty( $_POST['courseverify_lock_on_failure'] ),
				'final_exam_enabled'  => ! empty( $_POST['courseverify_final_exam_enabled'] ),
				'final_exam_quiz_id'  => isset( $_POST['courseverify_final_exam_quiz_id'] ) ? absint( $_POST['courseverify_final_exam_quiz_id'] ) : 0,
			)
		);
	}
}
