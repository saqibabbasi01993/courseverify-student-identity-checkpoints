<?php
/**
 * Security-question setup template.
 *
 * Expects (all pre-sanitized by Verification_UI before include):
 *
 * @var int      $course_id       LearnDash course ID.
 * @var int      $question_count  Number of questions required.
 * @var string[] $pool_questions  Admin-configured question texts selected
 *                                 for this student. Empty array means no
 *                                 pool is configured and the student must
 *                                 write their own questions instead.
 * @var string   $nonce           Nonce for courseverify_save_setup.
 * @var string   $ajax_url        admin-ajax.php URL.
 *
 * @package CourseVerify
 */

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Rendered inside a local closure so $using_pool and the loop variables
// ($index, $question_text, $i) are function-local, not treated as
// plugin-defined globals by static analysis.
( function ( $course_id, $question_count, $pool_questions ) {
	$using_pool = ! empty( $pool_questions );
	?>
	<div class="courseverify-container courseverify-setup" data-course-id="<?php echo esc_attr( $course_id ); ?>">
		<h2 class="courseverify-heading"><?php esc_html_e( 'Set Up Identity Verification', 'courseverify-student-identity-checkpoints' ); ?></h2>

		<p class="courseverify-intro">
			<?php if ( $using_pool ) : ?>
				<?php esc_html_e( 'Before starting this course, please answer the security questions below. You will be asked to answer one of these at certain points to verify your identity.', 'courseverify-student-identity-checkpoints' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Before starting this course, please set up security questions. You will be asked to answer one of these at certain points to verify your identity.', 'courseverify-student-identity-checkpoints' ); ?>
			<?php endif; ?>
		</p>

		<?php if ( ! $using_pool ) : ?>
			<p class="courseverify-hint">
				<?php esc_html_e( 'Choose questions and answers that only you would know — avoid using sensitive information such as passwords, ID numbers, or financial details.', 'courseverify-student-identity-checkpoints' ); ?>
			</p>
		<?php endif; ?>

		<form class="courseverify-setup-form" method="post" novalidate>
			<?php if ( $using_pool ) : ?>
				<?php foreach ( array_values( $pool_questions ) as $index => $question_text ) : ?>
					<fieldset class="courseverify-question-field">
						<legend><?php echo esc_html( $question_text ); ?></legend>

						<input type="hidden" name="questions[<?php echo esc_attr( $index + 1 ); ?>][question_text]" value="<?php echo esc_attr( $question_text ); ?>" />

						<label for="courseverify-answer-<?php echo esc_attr( $index + 1 ); ?>">
							<?php esc_html_e( 'Your answer', 'courseverify-student-identity-checkpoints' ); ?>
						</label>
						<input
							type="text"
							id="courseverify-answer-<?php echo esc_attr( $index + 1 ); ?>"
							name="questions[<?php echo esc_attr( $index + 1 ); ?>][answer]"
							class="courseverify-input"
							maxlength="255"
							required
							autocomplete="off"
						/>
					</fieldset>
				<?php endforeach; ?>
			<?php else : ?>
				<?php for ( $i = 1; $i <= $question_count; $i++ ) : ?>
					<fieldset class="courseverify-question-field">
						<legend>
							<?php
							/* translators: %d: question number. */
							printf( esc_html__( 'Question %d', 'courseverify-student-identity-checkpoints' ), (int) $i );
							?>
						</legend>

						<label for="courseverify-question-<?php echo esc_attr( $i ); ?>">
							<?php esc_html_e( 'Your question', 'courseverify-student-identity-checkpoints' ); ?>
						</label>
						<input
							type="text"
							id="courseverify-question-<?php echo esc_attr( $i ); ?>"
							name="questions[<?php echo esc_attr( $i ); ?>][question_text]"
							class="courseverify-input"
							maxlength="255"
							required
						/>

						<label for="courseverify-answer-<?php echo esc_attr( $i ); ?>">
							<?php esc_html_e( 'Your answer', 'courseverify-student-identity-checkpoints' ); ?>
						</label>
						<input
							type="text"
							id="courseverify-answer-<?php echo esc_attr( $i ); ?>"
							name="questions[<?php echo esc_attr( $i ); ?>][answer]"
							class="courseverify-input"
							maxlength="255"
							required
							autocomplete="off"
						/>
					</fieldset>
				<?php endfor; ?>
			<?php endif; ?>

			<div class="courseverify-message" role="alert" aria-live="polite"></div>

			<button type="submit" class="button button-primary courseverify-submit">
				<?php esc_html_e( 'Save & Continue', 'courseverify-student-identity-checkpoints' ); ?>
			</button>
		</form>
	</div>
	<?php
} )( $course_id, $question_count, $pool_questions );
