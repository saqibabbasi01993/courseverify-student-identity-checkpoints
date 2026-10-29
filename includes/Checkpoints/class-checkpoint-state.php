<?php
/**
 * Checkpoint state machine definition.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Checkpoints;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Checkpoint_State
 *
 * Pure state-machine logic — no database access. States and their allowed
 * transitions:
 *
 *   not_reached    -> required
 *   required       -> in_progress
 *   in_progress    -> passed
 *   in_progress    -> failed
 *   failed         -> required
 *   in_progress    -> locked
 *   locked         -> admin_unlocked
 *   admin_unlocked -> required
 */
class Checkpoint_State {

	/**
	 * Allowed transitions map: current state => array of allowed next states.
	 *
	 * @var array
	 */
	private static $transitions = array(
		'not_reached'    => array( 'required' ),
		'required'       => array( 'in_progress' ),
		'in_progress'    => array( 'passed', 'failed', 'locked' ),
		'failed'         => array( 'required' ),
		'locked'         => array( 'admin_unlocked' ),
		'admin_unlocked' => array( 'required' ),
		'passed'         => array(),
	);

	/**
	 * Whether a transition from one state to another is valid.
	 *
	 * @param string $from Current state.
	 * @param string $to   Proposed next state.
	 * @return bool
	 */
	public static function can_transition( $from, $to ) {
		if ( ! isset( self::$transitions[ $from ] ) ) {
			return false;
		}

		return in_array( $to, self::$transitions[ $from ], true );
	}

	/**
	 * All states that are considered "verification required" from the
	 * student's point of view — course access is blocked in these states.
	 *
	 * @return string[]
	 */
	public static function get_blocking_states() {
		return array( 'required', 'in_progress', 'locked' );
	}

	/**
	 * All states considered a resolved "pass" — course access is allowed.
	 *
	 * @return string[]
	 */
	public static function get_passing_states() {
		return array( 'passed' );
	}
}
