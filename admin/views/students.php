<?php
/**
 * Students admin view.
 *
 * @var array  $rows      Checkpoint rows from Checkpoint_Repository::get_filtered().
 * @var string $status    Current status filter.
 * @var int    $course_id Current course filter.
 * @var array  $statuses  All valid status values, for the filter dropdown.
 *
 * @package CourseVerify
 */

use CourseVerify\Security;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Rendered inside a local closure so its per-row loop variables ($row,
// $student, $course_title, $status_option) are function-local, not
// treated as plugin-defined globals by static analysis.
( function ( $rows, $status, $course_id, $statuses ) {
	?>
	<div class="wrap courseverify-admin">
		<h1><?php esc_html_e( 'CourseVerify — Students', 'courseverify-student-identity-checkpoints' ); ?></h1>

		<?php if ( isset( $_GET['courseverify_notice'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Action completed.', 'courseverify-student-identity-checkpoints' ); ?></p></div>
		<?php endif; ?>

		<form method="get" class="courseverify-filter-form">
			<input type="hidden" name="page" value="courseverify-students" />

			<select name="status">
				<option value=""><?php esc_html_e( 'All statuses', 'courseverify-student-identity-checkpoints' ); ?></option>
				<?php foreach ( $statuses as $status_option ) : ?>
					<option value="<?php echo esc_attr( $status_option ); ?>" <?php selected( $status, $status_option ); ?>>
						<?php echo esc_html( ucwords( str_replace( '_', ' ', $status_option ) ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<input
				type="number"
				name="course_id"
				placeholder="<?php esc_attr_e( 'Course ID', 'courseverify-student-identity-checkpoints' ); ?>"
				value="<?php echo esc_attr( $course_id ? $course_id : '' ); ?>"
				min="1"
			/>

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'courseverify-student-identity-checkpoints' ); ?></button>
		</form>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Student', 'courseverify-student-identity-checkpoints' ); ?></th>
					<th><?php esc_html_e( 'Course', 'courseverify-student-identity-checkpoints' ); ?></th>
					<th><?php esc_html_e( 'Checkpoint', 'courseverify-student-identity-checkpoints' ); ?></th>
					<th><?php esc_html_e( 'Status', 'courseverify-student-identity-checkpoints' ); ?></th>
					<th><?php esc_html_e( 'Attempts', 'courseverify-student-identity-checkpoints' ); ?></th>
					<th><?php esc_html_e( 'Updated', 'courseverify-student-identity-checkpoints' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'courseverify-student-identity-checkpoints' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No checkpoint records found.', 'courseverify-student-identity-checkpoints' ); ?></td></tr>
				<?php endif; ?>

				<?php foreach ( $rows as $row ) : ?>
					<?php
					$student      = get_userdata( $row['user_id'] );
					$course_title = get_the_title( $row['course_id'] );
					?>
					<tr>
						<td><?php echo esc_html( $student ? $student->display_name : '#' . $row['user_id'] ); ?></td>
						<td><?php echo esc_html( $course_title ? $course_title : '#' . $row['course_id'] ); ?></td>
						<td><?php echo esc_html( $row['checkpoint_key'] ); ?></td>
						<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $row['status'] ) ) ); ?></td>
						<td><?php echo esc_html( $row['attempts'] ); ?></td>
						<td><?php echo esc_html( $row['updated_at'] ); ?></td>
						<td class="courseverify-row-actions">
							<?php if ( 'locked' === $row['status'] && Security::current_user_can_unlock() ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="courseverify-inline-form">
									<?php wp_nonce_field( Security::NONCE_UNLOCK_STUDENT ); ?>
									<input type="hidden" name="action" value="courseverify_unlock_student" />
									<input type="hidden" name="user_id" value="<?php echo esc_attr( $row['user_id'] ); ?>" />
									<input type="hidden" name="course_id" value="<?php echo esc_attr( $row['course_id'] ); ?>" />
									<input type="hidden" name="checkpoint_key" value="<?php echo esc_attr( $row['checkpoint_key'] ); ?>" />
									<input type="text" name="reason" placeholder="<?php esc_attr_e( 'Reason (optional)', 'courseverify-student-identity-checkpoints' ); ?>" class="courseverify-reason-input" />
									<button type="submit" class="button button-small"><?php esc_html_e( 'Unlock', 'courseverify-student-identity-checkpoints' ); ?></button>
								</form>
							<?php endif; ?>

							<?php if ( Security::current_user_can_manage() ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="courseverify-inline-form">
									<?php wp_nonce_field( Security::NONCE_RESET_CHECKPOINT ); ?>
									<input type="hidden" name="action" value="courseverify_reset_checkpoint" />
									<input type="hidden" name="user_id" value="<?php echo esc_attr( $row['user_id'] ); ?>" />
									<input type="hidden" name="course_id" value="<?php echo esc_attr( $row['course_id'] ); ?>" />
									<input type="hidden" name="checkpoint_key" value="<?php echo esc_attr( $row['checkpoint_key'] ); ?>" />
									<button type="submit" class="button button-small"><?php esc_html_e( 'Reset Checkpoint', 'courseverify-student-identity-checkpoints' ); ?></button>
								</form>

								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="courseverify-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'This asks the student to set brand-new security answers next time they visit the course. Their old answers cannot be viewed or recovered — this replaces them. If the student is currently locked, this also clears the lock. Continue?', 'courseverify-student-identity-checkpoints' ) ); ?>');">
									<?php wp_nonce_field( Security::NONCE_RESET_QUESTIONS ); ?>
									<input type="hidden" name="action" value="courseverify_reset_questions" />
									<input type="hidden" name="user_id" value="<?php echo esc_attr( $row['user_id'] ); ?>" />
									<input type="hidden" name="course_id" value="<?php echo esc_attr( $row['course_id'] ); ?>" />
									<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Request New Security Answers', 'courseverify-student-identity-checkpoints' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
} )( $rows, $status, $course_id, $statuses );
