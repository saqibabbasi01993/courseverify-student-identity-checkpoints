<?php
/**
 * Audit logging service.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Services;

use CourseVerify\Repositories\Audit_Repository;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Audit_Service
 */
class Audit_Service {

	/**
	 * @var Audit_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param Audit_Repository $repository Audit repository instance.
	 */
	public function __construct( Audit_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Log an audit event and fire the corresponding action hook.
	 *
	 * @param string $event_type     One of the recognized event-type slugs.
	 * @param int    $user_id        Student user ID.
	 * @param int    $course_id      Course ID.
	 * @param string $checkpoint_key Optional checkpoint key.
	 * @param string $message        Optional human-readable note (e.g. an
	 *                                admin-supplied unlock reason).
	 * @param int    $actor_user_id  Optional. Who performed the action.
	 *                                Defaults to the current user, or 0 for
	 *                                system-initiated events.
	 * @return void
	 */
	public function log( $event_type, $user_id, $course_id, $checkpoint_key = '', $message = '', $actor_user_id = null ) {

		if ( null === $actor_user_id ) {
			$actor_user_id = get_current_user_id();
		}

		$this->repository->record(
			array(
				'user_id'        => $user_id,
				'course_id'      => $course_id,
				'checkpoint_key' => $checkpoint_key,
				'event_type'     => $event_type,
				'actor_user_id'  => $actor_user_id,
				'message'        => $message,
			)
		);

		/**
		 * Fires after any CourseVerify audit event is logged.
		 *
		 * @param string $event_type     Event type slug.
		 * @param int    $user_id        Student user ID.
		 * @param int    $course_id      Course ID.
		 * @param string $checkpoint_key Checkpoint key, if applicable.
		 * @param int    $actor_user_id  User ID who performed the action.
		 */
		do_action( 'courseverify_audit_event', $event_type, $user_id, $course_id, $checkpoint_key, $actor_user_id );
	}

	/**
	 * Fetch a filtered, paginated log listing for the admin Logs screen.
	 *
	 * @param array $args See Audit_Repository::get_filtered().
	 * @return array[]
	 */
	public function get_filtered( array $args = array() ) {
		return $this->repository->get_filtered( $args );
	}
}
