<?php
/**
 * Locked-out template.
 *
 * @var string $message Locked message text (already translated/filtered).
 *
 * @package CourseVerify
 */

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="courseverify-container courseverify-locked">
	<h2 class="courseverify-heading"><?php esc_html_e( 'Course Access Locked', 'courseverify-student-identity-checkpoints' ); ?></h2>
	<p class="courseverify-message-text"><?php echo esc_html( $message ); ?></p>
</div>
