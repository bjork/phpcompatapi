<?php

// Load configuration
if ( file_exists( 'config.php' ) ) {
	include_once( 'config.php' );
} else {
	die( 'Missing config.php. See config-sample.php.' );
}

// Validate configuration
if ( ! defined('WCT_ROOT_PATH') || ! defined('WCT_PHP_VERSION') ) {
	die( 'Invalid configuration. See config-sample.php.' );
}

// Make sure root path begins and ends with a slash
if ( '/' !== substr( WCT_ROOT_PATH, 0, 1 )
	|| '/' !== substr( WCT_ROOT_PATH, strlen( WCT_ROOT_PATH ) - 1 ) ) {
	die( 'Invalid configuration. WCT_ROOT_PATH should begin and end with /.' );
}

if ( ! defined( 'WCT_TEMP_DIR' ) ) {
	define( WCT_TEMP_DIR, sys_get_temp_dir() );
}

// Main controller: run the API or the JS UI.
if ( '/api/' === substr( $_SERVER['REQUEST_URI'], 0, 5 ) ) {
	include 'api/index.php';
} else {
	include 'app.php';
}