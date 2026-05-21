<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package AmirissetMetaManager
 */

// Determine the tests directory and the WordPress directory.
$_tests_dir = dirname( __DIR__ ) . '/tests';
$_wp_dir    = dirname( __DIR__ ) . '/tmp/wordpress';

if ( ! file_exists( $_wp_dir ) ) {
	echo "WordPress test environment not installed. Run the install script first.\n";
	exit( 1 );
}

// Include the WordPress test library.
require_once $_wp_dir . '/wp-load.php';

// Include the WordPress testing framework.
require_once $_wp_dir . '/wp-content/plugins/wordpress-tests-lib/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( __DIR__ ) . '/amiriset-meta-manager.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Include the WordPress testing framework.
require_once $_wp_dir . '/wp-content/plugins/wordpress-tests-lib/includes/bootstrap.php';

// Load test helpers.
require_once $_tests_dir . '/Helpers/class-test-case.php';
