<?php

/**
 * API bootsrapper file
 */

namespace WCT;

// Make sure we are running the PHP version required for the app.
if ( version_compare ( phpversion(), '5.4.0', '<') ) {
	header( 'HTTP/1.0 500 Internal Server Error' );
	exit( 'Unsupported PHP version. 5.4.0 or above required.' );
}

require_once( '../vendor/autoload.php' );

require_once( 'class/class-extendedcompatibilityanalyzer.php' );
require_once( 'class/class-responder.php' );
require_once( 'class/class-analyzer.php' );
require_once( 'class/class-request-handler.php' );

$options = array(
	'php_version'     => WCT_PHP_VERSION,
	'root_path'       => WCT_ROOT_PATH,
	'temp_dir'        => WCT_TEMP_DIR,
	'max_upload_size' => WCT_MAX_UPLOAD_SIZE
);
// Bootstrap the app by instantiating the classes.
$request_handler = new RequestHandler( new Responder, new Analyzer, $options );
$request_handler->run();