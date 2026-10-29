<?php
/**
 * Registers all actions and filters for the plugin.
 *
 * @package CourseVerify
 */

namespace CourseVerify;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Loader
 *
 * Maintains a list of hooks to be registered with WordPress, then registers
 * them all in one place. Keeps hook wiring out of individual classes.
 */
class Loader {

	/**
	 * Registered actions.
	 *
	 * @var array
	 */
	protected $actions = array();

	/**
	 * Registered filters.
	 *
	 * @var array
	 */
	protected $filters = array();

	/**
	 * Add a new action to the collection.
	 *
	 * @param string $hook          WordPress action hook name.
	 * @param object $component     Object instance containing the callback.
	 * @param string $callback      Method name on $component.
	 * @param int    $priority      Optional. Hook priority. Default 10.
	 * @param int    $accepted_args Optional. Number of accepted args. Default 1.
	 * @return void
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a new filter to the collection.
	 *
	 * @param string $hook          WordPress filter hook name.
	 * @param object $component     Object instance containing the callback.
	 * @param string $callback      Method name on $component.
	 * @param int    $priority      Optional. Hook priority. Default 10.
	 * @param int    $accepted_args Optional. Number of accepted args. Default 1.
	 * @return void
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Utility used by add_action()/add_filter() to build a hook entry.
	 *
	 * @param array  $hooks         Existing collection (actions or filters).
	 * @param string $hook          Hook name.
	 * @param object $component     Object instance.
	 * @param string $callback      Method name.
	 * @param int    $priority      Hook priority.
	 * @param int    $accepted_args Accepted argument count.
	 * @return array Updated collection.
	 */
	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return $hooks;
	}

	/**
	 * Register all stored actions and filters with WordPress.
	 *
	 * @return void
	 */
	public function run() {
		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
	}
}
