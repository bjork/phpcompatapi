<?php

// Load configuration
if ( file_exists( '../config.php' ) ) {
	include_once( '../config.php' );
} else {
	die( 'Missing config.php. See config-sample.php.' );
}

// Validate configuration.
if ( ! defined('WCT_ROOT_PATH') || ! defined('WCT_PHP_VERSION') ) {
	die( 'Invalid configuration. See config-sample.php.' );
}

// Make sure root path begins and ends with a slash.
if ( '/' !== substr( WCT_ROOT_PATH, 0, 1 )
	|| '/' !== substr( WCT_ROOT_PATH, strlen( WCT_ROOT_PATH ) - 1 ) ) {
	die( 'Invalid configuration. WCT_ROOT_PATH should begin and end with /.' );
}

// If not defined in configuration, define WCT_TEMP_DIR with the default.
if ( ! defined( 'WCT_TEMP_DIR' ) ) {
	define( WCT_TEMP_DIR, sys_get_temp_dir() );
}

// Main controller: run the API or the JS UI.
if ( WCT_ROOT_PATH . 'api/' === substr( $_SERVER['REQUEST_URI'], 0, strlen( WCT_ROOT_PATH . 'api/' ) ) ) {
	include 'api/index.php';
} else if ( WCT_ROOT_PATH === $_SERVER['REQUEST_URI'] ) {
	include 'app.php';
} else {
	header( 'HTTP/1.0 404 Not Found' );
	exit( '404 Not Found' );
}