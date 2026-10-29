<?php
/**
 * Logs admin view.
 *
 * @var array $rows      Audit rows from Audit_Service::get_filtered().
 * @var int   $course_id Current course filter.
 * @var int   $user_id   Current student filter.
 *
 * @package CourseVerify
 */

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Rendered inside a local closure so its per-row loop variables ($row,
// $student, $actor, $course_title) are function-local, not treated as
// plugin-defined globals by static analysis.
( function ( $rows, $course_id, $user_id ) {
	?>
	<div class="wrap courseverify-admin">
		<h1><?php esc_html_e( 'CourseVerify — Logs', 'courseverify-student-identity-checkpoints' ); ?></h1>

		<form method="get" class="courseverify-filter-form">
			<input type="hidden" name="page" value="courseverify-logs" />
			<input type="number" name="course_id" placeholder="<?php esc_attr_e( 'Course ID', 'courseverify-student-identity-checkpoints' ); ?>" value="<?php echo esc_attr( $course_id ? $course_id : '' ); ?>" min="1" />
			<input type="number" name="user_id" placeholder="<?php esc_attr_e( 'Student ID', 'courseverify-student-identity-checkpoints' ); ?>" value="<?php echo esc_attr( $user_id ? $user_id : '' ); ?>" min="1" />
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'courseverify-student-identity-checkpoints' ); ?></button>
		</form>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'When', 'courseverify-student-identity-checkpoints' ); ?></th>
					<th><?php esc_html_e( 'Event', 'courseverify-student-identity-checkpoints' ); ?></th>
					<th><?php esc_html_e( 'Student', 'courseverify-student-identity-checkpoints' ); ?></th>
					<th><?php esc_html_e( 'Course', 'courseverify-student-identity-checkpoints' ); ?></th>
					<th><?php esc_html_e( 'Checkpoint', 'courseverify-student-identity-checkpoints' ); ?></th>
					<th><?php esc_html_e( 'Performed By', 'courseverify-student-identity-checkpoints' ); ?></th>
					<th><?php esc_html_e( 'Note', 'courseverify-student-identity-checkpoints' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No audit records found.', 'courseverify-student-identity-checkpoints' ); ?></td></tr>
				<?php endif; ?>

				<?php foreach ( $rows as $row ) : ?>
					<?php
					$student      = get_userdata( $row['user_id'] );
					$actor        = $row['actor_user_id'] ? get_userdata( $row['actor_user_id'] ) : null;
					$course_title = get_the_title( $row['course_id'] );
					?>
					<tr>
						<td><?php echo esc_html( $row['created_at'] ); ?></td>
						<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $row['event_type'] ) ) ); ?></td>
						<td><?php echo esc_html( $student ? $student->display_name : '#' . $row['user_id'] ); ?></td>
						<td><?php echo esc_html( $course_title ? $course_title : '#' . $row['course_id'] ); ?></td>
						<td><?php echo esc_html( $row['checkpoint_key'] ? $row['checkpoint_key'] : '—' ); ?></td>
						<td><?php echo esc_html( $actor ? $actor->display_name : __( 'System', 'courseverify-student-identity-checkpoints' ) ); ?></td>
						<td><?php echo esc_html( $row['message'] ? $row['message'] : '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
} )( $rows, $course_id, $user_id );
