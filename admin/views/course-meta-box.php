<?php
/**
 * Course meta box view.
 *
 * @var int   $course_id
 * @var bool  $enabled
 * @var int   $question_count
 * @var int[] $hours
 * @var int   $max_attempts
 * @var bool  $lock_on_failure
 * @var bool  $final_exam_enabled
 * @var int   $final_exam_quiz_id
 * @var int[] $quiz_ids
 *
 * @package CourseVerify
 */

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Rendered inside a local closure (rather than at the file's top level) so
// its loop variable ($quiz_id) is function-local, not treated as a
// plugin-defined global by static analysis.
( function ( $enabled, $question_count, $hours, $max_attempts, $lock_on_failure, $final_exam_enabled, $final_exam_quiz_id, $quiz_ids ) {
	?>
	<table class="form-table">
		<tr>
			<th><label for="courseverify_enabled"><?php esc_html_e( 'Enable CourseVerify', 'courseverify-student-identity-checkpoints' ); ?></label></th>
			<td><input type="checkbox" id="courseverify_enabled" name="courseverify_enabled" value="1" <?php checked( $enabled ); ?> /></td>
		</tr>
		<tr>
			<th><label for="courseverify_question_count"><?php esc_html_e( 'Security Questions Required', 'courseverify-student-identity-checkpoints' ); ?></label></th>
			<td><input type="number" min="1" id="courseverify_question_count" name="courseverify_question_count" value="<?php echo esc_attr( $question_count ); ?>" class="small-text" /></td>
		</tr>
		<tr>
			<th><label for="courseverify_checkpoint_hours"><?php esc_html_e( 'Checkpoint Schedule (hours, comma-separated)', 'courseverify-student-identity-checkpoints' ); ?></label></th>
			<td>
				<input type="text" id="courseverify_checkpoint_hours" name="courseverify_checkpoint_hours" value="<?php echo esc_attr( implode( ', ', $hours ) ); ?>" class="regular-text" placeholder="0, 5, 10, 15, 20, 25" />
				<p class="description"><?php esc_html_e( '0 represents course start. Values are hours of tracked course time.', 'courseverify-student-identity-checkpoints' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="courseverify_max_attempts"><?php esc_html_e( 'Maximum Attempts', 'courseverify-student-identity-checkpoints' ); ?></label></th>
			<td><input type="number" min="1" id="courseverify_max_attempts" name="courseverify_max_attempts" value="<?php echo esc_attr( $max_attempts ); ?>" class="small-text" /></td>
		</tr>
		<tr>
			<th><label for="courseverify_lock_on_failure"><?php esc_html_e( 'Lock on Failure', 'courseverify-student-identity-checkpoints' ); ?></label></th>
			<td><input type="checkbox" id="courseverify_lock_on_failure" name="courseverify_lock_on_failure" value="1" <?php checked( $lock_on_failure ); ?> /></td>
		</tr>
		<tr>
			<th><label for="courseverify_final_exam_enabled"><?php esc_html_e( 'Enable Final Exam Verification', 'courseverify-student-identity-checkpoints' ); ?></label></th>
			<td><input type="checkbox" id="courseverify_final_exam_enabled" name="courseverify_final_exam_enabled" value="1" <?php checked( $final_exam_enabled ); ?> /></td>
		</tr>
		<tr>
			<th><label for="courseverify_final_exam_quiz_id"><?php esc_html_e( 'Final Exam Quiz', 'courseverify-student-identity-checkpoints' ); ?></label></th>
			<td>
				<select id="courseverify_final_exam_quiz_id" name="courseverify_final_exam_quiz_id">
					<option value="0"><?php esc_html_e( '— Select a quiz —', 'courseverify-student-identity-checkpoints' ); ?></option>
					<?php foreach ( $quiz_ids as $quiz_id ) : ?>
						<option value="<?php echo esc_attr( $quiz_id ); ?>" <?php selected( $final_exam_quiz_id, $quiz_id ); ?>>
							<?php echo esc_html( get_the_title( $quiz_id ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'The administrator must explicitly select the final exam quiz — it is never assumed.', 'courseverify-student-identity-checkpoints' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
} )( $enabled, $question_count, $hours, $max_attempts, $lock_on_failure, $final_exam_enabled, $final_exam_quiz_id, $quiz_ids );
