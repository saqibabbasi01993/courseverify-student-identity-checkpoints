<?php
/**
 * Dashboard view.
 *
 * @var array $stats Stats array from Dashboard_Page::get_stats().
 *
 * @package CourseVerify
 */

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap courseverify-admin">
	<h1><?php esc_html_e( 'CourseVerify Dashboard', 'courseverify-student-identity-checkpoints' ); ?></h1>

	<div class="courseverify-stat-grid">
		<div class="courseverify-stat-card">
			<span class="courseverify-stat-number"><?php echo esc_html( number_format_i18n( $stats['protected_courses'] ) ); ?></span>
			<span class="courseverify-stat-label"><?php esc_html_e( 'Protected Courses', 'courseverify-student-identity-checkpoints' ); ?></span>
		</div>
		<div class="courseverify-stat-card">
			<span class="courseverify-stat-number"><?php echo esc_html( number_format_i18n( $stats['students_verified'] ) ); ?></span>
			<span class="courseverify-stat-label"><?php esc_html_e( 'Students Verified', 'courseverify-student-identity-checkpoints' ); ?></span>
		</div>
		<div class="courseverify-stat-card">
			<span class="courseverify-stat-number"><?php echo esc_html( number_format_i18n( $stats['pending_verifications'] ) ); ?></span>
			<span class="courseverify-stat-label"><?php esc_html_e( 'Pending Verifications', 'courseverify-student-identity-checkpoints' ); ?></span>
		</div>
		<div class="courseverify-stat-card courseverify-stat-card-warning">
			<span class="courseverify-stat-number"><?php echo esc_html( number_format_i18n( $stats['locked_students'] ) ); ?></span>
			<span class="courseverify-stat-label"><?php esc_html_e( 'Locked Students', 'courseverify-student-identity-checkpoints' ); ?></span>
		</div>
		<div class="courseverify-stat-card">
			<span class="courseverify-stat-number"><?php echo esc_html( number_format_i18n( $stats['failed_attempts'] ) ); ?></span>
			<span class="courseverify-stat-label"><?php esc_html_e( 'Total Recorded Attempts', 'courseverify-student-identity-checkpoints' ); ?></span>
		</div>
	</div>

	<p class="description">
		<?php esc_html_e( 'Manage individual students and unlock checkpoints from the Students screen. View the full verification history on the Logs screen.', 'courseverify-student-identity-checkpoints' ); ?>
	</p>
</div>
