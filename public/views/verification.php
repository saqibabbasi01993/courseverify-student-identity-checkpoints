<?php
/**
 * Verification prompt template.
 *
 * Expects (all pre-sanitized by Verification_UI before include):
 *
 * @var int    $course_id      LearnDash course ID.
 * @var string $checkpoint_key Checkpoint key being verified.
 * @var int    $question_id    Question ID to submit back.
 * @var string $question_text  Question text to display.
 *
 * @package CourseVerify
 */

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div
	class="courseverify-container courseverify-verification"
	data-course-id="<?php echo esc_attr( $course_id ); ?>"
	data-checkpoint-key="<?php echo esc_attr( $checkpoint_key ); ?>"
	data-question-id="<?php echo esc_attr( $question_id ); ?>"
>
	<h2 class="courseverify-heading"><?php esc_html_e( 'Student Identity Verification', 'courseverify-student-identity-checkpoints' ); ?></h2>

	<p class="courseverify-intro">
		<?php esc_html_e( 'To continue your course, please verify your identity.', 'courseverify-student-identity-checkpoints' ); ?>
	</p>

	<form class="courseverify-verify-form" method="post" novalidate>
		<label for="courseverify-verify-answer" class="courseverify-question">
			<?php echo esc_html( $question_text ); ?>
		</label>
		<input
			type="text"
			id="courseverify-verify-answer"
			name="answer"
			class="courseverify-input"
			required
			autocomplete="off"
		/>

		<div class="courseverify-message" role="alert" aria-live="polite"></div>

		<button type="submit" class="button button-primary courseverify-submit">
			<?php esc_html_e( 'Verify Identity', 'courseverify-student-identity-checkpoints' ); ?>
		</button>
	</form>
</div>
