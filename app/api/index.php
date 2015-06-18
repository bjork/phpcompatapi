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

require_once( 'class/class-responder.php' );
require_once( 'class/class-analyzer.php' );
require_once( 'class/class-request-handler.php' );

// Bootstrap the app by instantiating the classes.
$request_handler = new RequestHandler( new Responder, new Analyzer );
$request_handler->run( WCT_PHP_VERSION, WCT_ROOT_PATH );