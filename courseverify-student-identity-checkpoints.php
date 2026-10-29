<?php
/**
 * Plugin Name:       CourseVerify – Student Identity Checkpoints
 * Description:       Configurable identity-verification checkpoints for online courses, gated on LearnDash course progress and course time.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            SKFreelancers
 * Author URI:        https://skfreelancers.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       courseverify-student-identity-checkpoints
 *
 * CourseVerify is an independent plugin and is not affiliated with LearnDash,
 * Uncanny Owl, or any regulatory agency. It provides a configurable
 * identity-verification mechanism; educational providers are responsible for
 * configuring it to meet their own institutional and regulatory requirements.
 *
 * @package CourseVerify
 */

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'COURSEVERIFY_VERSION', '1.0.1' );
define( 'COURSEVERIFY_DB_VERSION', '1.0.0' ); // Unchanged — no schema change in this release.
define( 'COURSEVERIFY_PLUGIN_FILE', __FILE__ );
define( 'COURSEVERIFY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'COURSEVERIFY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'COURSEVERIFY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Minimum supported LearnDash / Uncanny Toolkit Pro versions.
// Defined as constants only; not currently enforced as a hard block because
// no specific version dependency has been verified yet (see Integrations
// classes, added in later phases).
define( 'COURSEVERIFY_MIN_LEARNDASH_VERSION', '4.0.0' );

/**
 * PSR-4-ish autoloader for the CourseVerify\ namespace.
 *
 * Maps CourseVerify\Sub\Namespace\Class_Name to
 * includes/Sub/Namespace/class-class-name.php, following WordPress file
 * naming conventions rather than a literal PSR-4 file layout.
 *
 * @param string $class Fully qualified class name being requested.
 * @return void
 */
function courseverify_autoloader( $class ) {

	$prefix = 'CourseVerify\\';

	if ( 0 !== strpos( $class, $prefix ) ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );
	$parts    = explode( '\\', $relative );
	$class_name = array_pop( $parts );

	$file_name = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';

	// Interfaces live under Contracts/ and use an interface- prefix instead.
	if ( ! empty( $parts ) && 'Contracts' === $parts[0] ) {
		$file_name = 'interface-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
	}

	// CourseVerify\Admin\* lives under admin/, CourseVerify\Frontend\* lives
	// under public/ (to match WordPress.org's admin/public directory
	// convention); everything else lives under includes/.
	$base_dir = 'includes';

	if ( ! empty( $parts ) && 'Admin' === $parts[0] ) {
		$base_dir = 'admin';
		$parts    = array_slice( $parts, 1 );
	} elseif ( ! empty( $parts ) && 'Frontend' === $parts[0] ) {
		$base_dir = 'public';
		$parts    = array_slice( $parts, 1 );
	}

	$path_parts = array_merge( array( $base_dir ), $parts, array( $file_name ) );
	$path       = COURSEVERIFY_PLUGIN_DIR . implode( DIRECTORY_SEPARATOR, $path_parts );

	if ( file_exists( $path ) ) {
		require_once $path;
	}
}
spl_autoload_register( 'courseverify_autoloader' );

/**
 * Begins plugin execution.
 *
 * @return void
 */
function courseverify_run() {
	$plugin = new CourseVerify\Plugin();
	$plugin->run();
}

register_activation_hook( __FILE__, array( 'CourseVerify\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CourseVerify\\Deactivator', 'deactivate' ) );

add_action( 'plugins_loaded', 'courseverify_run' );
