<?php
/**
 * Plugin Name:     WordCamp Reports
 * Plugin URI:      https://wordcamp.org
 * Description:     Automated reports for WordCamp.org.
 * Author:          WordCamp.org
 * Author URI:      https://wordcamp.org
 * Version:         1
 *
 * @package         WordCamp\Reports
 */

namespace WordCamp\Reports;
defined( 'WPINC' ) || die();

use WordCamp\Reports\Report;

const JS_VERSION  = 1;
const CSS_VERSION = 1;

define( __NAMESPACE__ . '\PLUGIN_DIR', \plugin_dir_path( __FILE__ ) );
define( __NAMESPACE__ . '\PLUGIN_URL', \plugins_url( '/', __FILE__ ) );

/**
 * Get the path for the includes directory.
 *
 * @return string Path with trailing slash
 */
function get_classes_dir_path() {
	return trailingslashit( PLUGIN_DIR ) . 'classes/';
}

/**
 * Get the path for the views directory.
 *
 * @return string Path with trailing slash
 */
function get_views_dir_path() {
	return trailingslashit( PLUGIN_DIR ) . 'views/';
}

/**
 * Autoloader for plugin classes.
 *
 * @param string $class The fully-qualified class name.
 *
 * @return void
 */
spl_autoload_register( function( $class ) {
	// Project-specific namespace prefix.
	$prefix = 'WordCamp\\Reports\\';

	// Base directory for the namespace prefix.
	$base_dir = get_classes_dir_path();

	// Does the class use the namespace prefix?
	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		// No, move to the next registered autoloader.
		return;
	}

	// Get the relative class name.
	$relative_class = substr( $class, $len );

	// Convert the relative class name to a relative path.
	$relative_path_parts = explode( '\\', $relative_class );
	$filename = 'class-' . array_pop( $relative_path_parts );
	$relative_path = implode( '/', $relative_path_parts ) . "/$filename.php";
	$relative_path = strtolower( $relative_path );
	$relative_path = str_replace( '_', '-', $relative_path );

	$file = $base_dir . $relative_path;

	// If the file exists, require it.
	if ( file_exists( $file ) ) {
		require $file;
	}
} );


function add_reports_page() {
	\add_submenu_page(
		'index.php',
		__( 'Reports', 'wordcamporg' ),
		__( 'Reports', 'wordcamporg' ),
		'manage_network',
		'reports',
		__NAMESPACE__ . '\render_reports_page'
	);
}

add_action( 'admin_menu', __NAMESPACE__ . '\add_reports_page' );


function render_reports_page() {
	include get_views_dir_path() . 'admin-reports.php';
}
