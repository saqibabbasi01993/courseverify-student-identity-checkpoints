<?php
/**
 * Uncanny Toolkit Pro (Simple Course Timer) time-provider adapter.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Integrations\Uncanny;

use CourseVerify\Contracts\Time_Provider_Interface;
use CourseVerify\Integrations\Time_Parser;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Uncanny_Timer
 *
 * COMPATIBILITY NOTE (read before relying on this in production):
 * ------------------------------------------------------------------
 * I do not have access to Uncanny Toolkit Pro's source in this environment,
 * so I could not verify whether the [uo_time] shortcode is backed by a
 * documented, directly callable function (e.g. something exposed as a
 * public static method or a documented filter/action that returns tracked
 * seconds for an arbitrary user/course pair). Per the build spec, I have
 * NOT invented such a function.
 *
 * This adapter therefore uses the only mechanism guaranteed to be public:
 * rendering the [uo_time] shortcode itself via do_shortcode() and parsing
 * its HH:MM:SS output with Time_Parser. This has a real limitation you
 * should verify against your actual installed version before relying on
 * it:
 *
 *   - The shortcode renders time for the CURRENTLY LOGGED-IN user in the
 *     CURRENT post/course context. It is not documented to accept a
 *     $user_id/$course_id pair. This adapter can therefore only reliably
 *     answer get_course_time() for the current front-end visitor viewing
 *     that course's content — which matches CourseVerify's primary use
 *     case (checking the checkpoint for the student who is currently
 *     browsing the course).
 *   - For admin-initiated checks on an arbitrary student (e.g. the admin
 *     Students screen), this adapter cannot currently produce a value and
 *     returns a WP_Error rather than guessing. If Uncanny Toolkit Pro
 *     exposes a documented per-user data source (custom table, user meta,
 *     or a public API method) that you can confirm from their
 *     documentation or support, tell me and I will swap this adapter to
 *     read that directly instead of shortcode output — the rest of the
 *     plugin (Checkpoint Engine, Services) does not need to change, since
 *     it only ever talks to Time_Provider_Interface.
 *
 * This class only reads data. It never writes to any Uncanny-owned table
 * or option.
 */
class Uncanny_Timer implements Time_Provider_Interface {

	/**
	 * {@inheritDoc}
	 */
	public function is_available() {
		return shortcode_exists( 'uo_time' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id LearnDash course ID.
	 * @return int|\WP_Error
	 */
	public function get_course_time( $user_id, $course_id ) {

		if ( ! $this->is_available() ) {
			return new \WP_Error(
				'courseverify_uncanny_unavailable',
				__( 'The Uncanny Toolkit Pro course timer is not available.', 'courseverify-student-identity-checkpoints' )
			);
		}

		$current_user_id = get_current_user_id();

		if ( (int) $user_id !== (int) $current_user_id ) {
			return new \WP_Error(
				'courseverify_uncanny_unsupported_lookup',
				__( 'This time provider can currently only read the course time of the active, logged-in student. Looking up another student\'s time requires a verified Uncanny Toolkit Pro data source.', 'courseverify-student-identity-checkpoints' )
			);
		}

		/**
		 * Filters whether the course-context override should be applied at
		 * all. By default, when we're rendering as part of a normal
		 * front-end page load (the common case — the_content filter on the
		 * lesson/topic/quiz/course the student is actually viewing),
		 * [uo_time] is rendered using WHATEVER PAGE CONTEXT IS ALREADY
		 * ACTIVE, since that's the page Uncanny is actually tracking time
		 * against. Overriding global $post to the course post itself (an
		 * earlier version of this adapter did this unconditionally) could
		 * point the shortcode at a context Uncanny isn't tracking, and
		 * return empty/zero data even for a student actively progressing
		 * through the course. The override below is only used as a
		 * best-effort fallback for contexts with no real page loaded at all
		 * (e.g. an AJAX request with no global $post).
		 *
		 * @param int $course_id LearnDash course ID.
		 * @param int $user_id   WordPress user ID.
		 */
		$use_current_context = ! empty( $GLOBALS['post'] ) && ! wp_doing_ajax();

		if ( $use_current_context ) {
			$raw_output = $this->render_uo_time_shortcode( 0 );
		} else {
			$context_post_id = apply_filters( 'courseverify_uncanny_context_post_id', $course_id, $user_id );
			$raw_output       = $this->render_uo_time_shortcode( $context_post_id );
		}

		if ( is_wp_error( $raw_output ) ) {
			return $raw_output;
		}

		$seconds = Time_Parser::parse_time_to_seconds( $raw_output );

		if ( is_wp_error( $seconds ) ) {
			return new \WP_Error(
				'courseverify_uncanny_invalid_output',
				__( 'The course timer returned an unrecognized value.', 'courseverify-student-identity-checkpoints' )
			);
		}

		return $seconds;
	}

	/**
	 * Render [uo_time] and extract the raw HH:MM:SS text from the output.
	 *
	 * @param int $post_id Post ID to switch context to before rendering, or
	 *                      0 to render using whatever global $post context
	 *                      is already active (the normal front-end case).
	 * @return string|\WP_Error Raw HH:MM:SS text, or WP_Error.
	 */
	private function render_uo_time_shortcode( $post_id ) {
		global $post;

		$original_post = $post;
		$restore_post  = false;

		if ( $post_id ) {
			$maybe_post = get_post( $post_id );

			if ( $maybe_post ) {
				$post         = $maybe_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				$restore_post = true;
				setup_postdata( $post );
			}
		}

		$output = do_shortcode( '[uo_time]' );

		if ( $restore_post ) {
			$post = $original_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			if ( $original_post ) {
				setup_postdata( $original_post );
			} else {
				wp_reset_postdata();
			}
		}

		$output = trim( wp_strip_all_tags( (string) $output ) );

		if ( '' === $output ) {
			// An empty shortcode result most commonly means Uncanny simply
			// hasn't recorded any time for this student/course yet (e.g.
			// their very first page view) rather than a real error —
			// treat it as 0 seconds rather than blocking access with a
			// diagnostic message on a brand-new enrollment. This does not
			// weaken the "never silently pass a checkpoint" rule: 0
			// seconds still correctly triggers the course-start checkpoint
			// rather than skipping it.
			return '00:00:00';
		}

		// The shortcode may wrap the time in surrounding label text; extract
		// the first HH:MM:SS-shaped token rather than assuming the whole
		// output is exactly the time string.
		if ( preg_match( '/\d+:[0-5]\d:[0-5]\d/', $output, $matches ) ) {
			return $matches[0];
		}

		return new \WP_Error(
			'courseverify_uncanny_unparsable_output',
			__( 'The course timer output could not be parsed.', 'courseverify-student-identity-checkpoints' )
		);
	}
}
