<?php

use Bartlett\Reflect\Client;

require_once '../vendor/autoload.php';

if ( version_compare ( phpversion(), '5.4', '<') ) {
	header( 'HTTP/1.0 500 Internal Server Error' );
	exit( 'Unsupported PHP version. 5.4 or above required.' );
}

$php_version_to_test_against = '5.4';

// get the path
$path = $_SERVER['REQUEST_URI'];

// remove leading '/'
$path = ltrim( $path, '/' );

// make sure path starts with api/v1
$the_rest = wct_validate_base_request( $path );

// make sure the request is otherwice ok
wct_validate_test_request( $the_rest );

// get the file from request
$file_to_analyze = wct_get_test_file_request();

// write file to archive with a temporary name
$zip = new \ZipArchive();
$dir = dirname( dirname( __FILE__ ) ) . '/temp/';
$temp_name = $dir . uniqid( 'wct', true ) . '.zip';
if ( true !== $zip->open( $temp_name, \ZipArchive::CREATE ) ) {
	wct_error( 'Insufficient permissions', 500 );
}
$zip->addFromString( 'test.php', $file_to_analyze );
$zip->close();

// creates an instance of client
$client = new Client();

// request for a Bartlett\Reflect\Api\Analyser
$api = $client->api( 'analyser' );

// perform request, on a data source with default analyser
$analysers  = array( 'compatibility' );

// run the actual analyzer
$metrics = $api->run( $temp_name, $analysers );

// delete archive immediately
// todo make sure we delete the file even if something above fails
unlink( $temp_name );

$passes_requirements = true;

$analyzer_full_name = 'Bartlett\CompatInfo\Analyser\CompatibilityAnalyser';

if ( isset( $metrics[ $analyzer_full_name ],
	$metrics[ $analyzer_full_name ]['versions'],
	$metrics[ $analyzer_full_name ]['versions']['php.min'] ) ) {
	$min_php = $metrics[ $analyzer_full_name ]['versions']['php.min'];
	$passes_requirements = version_compare( $php_version_to_test_against, $min_php, '>=' );
	// todo it seems in some cases php.min under versions is not the max of all min php versions
	// instead, we should loop through all the data in extensions, functions and constants at least
	// to find max php version
} else {
	wct_error( 'Unable to determine compatibility', 500 );
}

$php_lib_results_data = $metrics[ $analyzer_full_name ];

$results = (object) array(
	'result' => $passes_requirements,
	'data'   => $php_lib_results_data,
);

// finally return results
wct_response( $results );

function wct_validate_base_request( $path ) {

	$parts = explode( '/', $path );

	// there should be more than 1 parts and the first one should be "api"
	if ( count( $parts ) <= 1 || 'api' !== $parts[ 0 ] ) {
		wct_error( 'Invalid request' );
	}

	// the second part should be "v1"
	if ( 'v1' !== $parts[ 1 ] ) {
		wct_error( 'Invalid version' );
	}

	// remove the first 2 parts from the parts array
	array_splice( $parts, 0, 2 );

	// return everything after the tested part
	return implode( '/', $parts );

}

function wct_validate_test_request( $path ) {
	$parts = explode('/', $path );
	$base_resource = $parts[0];

	if ( 'test' !== $base_resource ) {
		wct_error( 'Unsupported API resource' );
	}

	if ( ! isset( $_SERVER['CONTENT_TYPE'] ) || 'application/json' !== $_SERVER['CONTENT_TYPE'] ) {
		wct_error( 'Invalid Content-Type' );
	}

	if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
		wct_error( 'Invalid HTTP method', 405 );
	}
}

function wct_get_test_file_request() {
	$request_data = json_decode( file_get_contents( "php://input" ) );

	if ( ! is_object( $request_data ) ) {
		wct_error( 'Invalid data. We only accept JSON.' );
	}

	if ( ! property_exists( $request_data, 'file' ) ) {
		wct_error( 'Missing file' );
	}

	$file_to_analyze = base64_decode( $request_data->file );

	return $file_to_analyze;
}

function wct_error( $message, $status_code = 400 ) {

	$response = (object) array(
		'result' => false,
		'error' => $message
	);

	wct_response( $response, $status_code );

}

function wct_response( $response_object, $status_code = 200 ) {

	if ( ! headers_sent() ) {
		header( 'Content-Type: application/json' );
		http_response_code( $status_code );
	}

	$json = json_encode( $response_object );
	echo $json;

	exit();

}
