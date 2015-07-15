<?php

// Make sure we are running the PHP version required for the app.
if ( version_compare ( phpversion(), '5.4.0', '<') ) {
	header( 'HTTP/1.0 500 Internal Server Error' );
	exit( 'Unsupported PHP version. 5.4.0 or above required.' );
}

// Load configuration.
if ( file_exists( '../config.php' ) ) {
	include_once( '../config.php' );
} else {
	die( 'Missing config.php. See config-sample.php.' );
}

// If not defined in configuration, define WCT_TEMP_DIR with the default.
if ( ! defined( 'WCT_TEMP_DIR' ) ) {
	define( WCT_TEMP_DIR, sys_get_temp_dir() );
}

// If not defined in configuration, define WCT_MAX_UPLOAD_SIZE with the default one megabyte.
if ( ! defined( 'WCT_MAX_UPLOAD_SIZE' ) ) {
	define( WCT_MAX_UPLOAD_SIZE, 1048576 );
}

// Main controller: run the API or the JS UI.
if ( WCT_ROOT_PATH . 'api/' === substr( $_SERVER['REQUEST_URI'], 0, strlen( WCT_ROOT_PATH . 'api/' ) ) ) {
	include 'api/index.php';
	run();
} else if ( WCT_ROOT_PATH === $_SERVER['REQUEST_URI'] ) {
	include 'app.php';
} else {
	header( 'HTTP/1.0 404 Not Found' );
	exit( '404 Not Found' );
}