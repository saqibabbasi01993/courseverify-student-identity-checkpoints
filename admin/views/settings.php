<?php
/**
 * Settings admin view.
 *
 * @var string $page_slug Settings page slug used for settings_fields()/do_settings_sections().
 *
 * @package CourseVerify
 */

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap courseverify-admin">
	<h1><?php esc_html_e( 'CourseVerify — Settings', 'courseverify-student-identity-checkpoints' ); ?></h1>

	<form method="post" action="options.php">
		<?php
		settings_fields( \CourseVerify\Admin\Settings_Page::OPTION_GROUP );
		do_settings_sections( $page_slug );
		submit_button();
		?>
	</form>

	<p class="description">
		<?php esc_html_e( 'Per-course checkpoint schedules, attempt limits, and final exam configuration are set on each course\'s edit screen.', 'courseverify-student-identity-checkpoints' ); ?>
	</p>
</div>
