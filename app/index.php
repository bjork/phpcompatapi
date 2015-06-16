<?php

if ( version_compare ( phpversion(), '5.4.0', '<') ) {
	header( 'HTTP/1.0 500 Internal Server Error' );
	exit( 'Unsupported PHP version. 5.4.0 or above required.' );
}

$php_version_to_test_against = '5.4.0';

wct_run( $php_version_to_test_against );

function wct_run( $php_version_to_test_against ) {

	// Validate the overall request to this API
	$resource = wct_validate_request( $_SERVER['REQUEST_URI'] );
	if ( false === $resource ) {
		wct_error( 'Invalid request' );
	}

	// Validate the request to this specific compatibility test resource
	if ( 'test' !== substr( $resource, 0, 4 ) ) {
		wct_error( 'Unsupported API resource' );
	}

	// Validate HTTP method
	if ( ! isset( $_SERVER['REQUEST_METHOD'] )
		|| 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
		wct_error( 'Invalid HTTP method', 405 );
	}

	if ( ! isset( $_FILES['file'] ) ) {
		wct_error( 'Invalid file upload.' );
	}

	// Get file from the request
	$file_name = wct_get_test_file_from_request( $_FILES['file'] );
	if ( false === $file_name ) {
		wct_error( 'Invalid file upload.' );
	}

	// Parse and analyze file for metrics
	$metrics = wct_get_metrics( $file_name );
	if ( false === $metrics ) {
		wct_error( 'Unable to parse the file.', 500 );
	}

	// Get info on possible non-conforming issues
	$non_passing_info = wct_get_issues( $metrics, $php_version_to_test_against );
	if ( false === $non_passing_info ) {
		wct_error( 'Unable to determine compatibility.', 500 );
	}

	wct_respond_with_results( $non_passing_info );

}

/**
 * Use PHP_CompatInfo to get metrics of code
 * @param string $file_to_analyze PHP file contents as a string
 * @return bool|array False on error, result array otherwise.
 */
function wct_get_metrics( $file_to_analyze ) {
	require_once '../vendor/autoload.php';

	try {

		// creates an instance of client
		$client = new Bartlett\Reflect\Client();

		// request for a Bartlett\Reflect\Api\Analyser
		$api = $client->api( 'analyser' );

		// perform request, on a data source with default analyser
		$analysers  = array( 'compatibility' );

		// run the analyzer
		/** @noinspection PhpUndefinedMethodInspection */
		$metrics = $api->run( $file_to_analyze, $analysers );

	} catch ( Exception $e ) {

		// make sure the temp file gets deleted always
		unlink( $file_to_analyze );
		return false;

	}

	// delete temp file immediately
	unlink( $file_to_analyze );

	return $metrics;
}

/**
 * Test if PHP CompatInfo metrics match required PHP version
 * @param array $metrics PHP CompatInfo metrics
 * @param string $php_version_to_test_against PHP version string the code needs to match.
 * @return bool|array Filtered results that only contain issues. False on failure. Empty return array is a pass.
 */
function wct_get_issues( $metrics, $php_version_to_test_against ) {
	$analyzer_full_name = 'Bartlett\CompatInfo\Analyser\CompatibilityAnalyser';

	if ( ! isset( $metrics[ $analyzer_full_name ],
		$metrics[ $analyzer_full_name ]['versions'] ) ) {
		return false;
	}

	$versions = $metrics[ $analyzer_full_name ]['versions'];
	$passes_requirements = wct_passes( $versions, $php_version_to_test_against );

	$info = wct_get_info_for_non_passing_properties(
		$metrics[ $analyzer_full_name ],
		$php_version_to_test_against
	);

	if ( $passes_requirements && count( $info ) > 0 ) {
		// A conflict was found in the metrics.
		return false;
	}

	return $info;
}

/**
 * Filtered results to only contain issues.
 * @param array $metrics PHP CompatInfo metrics
 * @param string $php_version_to_test_against PHP version string the code needs to match.
 * @return array Filtered results that only contain issues.
 */
function wct_get_info_for_non_passing_properties( $metrics, $php_version_to_test_against ) {
	$info = array();

	foreach ( $metrics as $metric => $properties ) {
		// Skip versions and empty properties
		if ( 'versions' === $metric
			|| ! is_array( $properties )
			|| 0 === count( $properties ) ) {
			continue;
		}

		// Gather property data that does not meet the requirements
		foreach ( $properties as $property_name => $property_data ) {
			if ( ! wct_passes( $property_data, $php_version_to_test_against ) ) {
				if ( ! isset( $info[ $metric ] ) ) {
					$info[ $metric ] = array();
				}
				$info[ $metric ][ $property_name ] = wct_array_with_only_php_min_max( $property_data );
			}
		}
	}

	return $info;
}

/**
 * Pick only two out of PHP CompatInfo's numerous keys
 * @param $array Array to filter
 * @return array Array filtered
 */
function wct_array_with_only_php_min_max( $array ) {

	$result = array( 'php.min' => null, 'php.max' => null );

	foreach ( array_keys( $result ) as $key ) {
		if ( isset( $array[ $key ] ) ) {
			$result[ $key ] = $array[ $key ];
		}
	}

	return $result;
}

/**
 * Test if a property of PHP CompatInfo results array matches required PHP version
 * @param array $property PHP CompatInfo property
 * @param string $php_version_to_test_against PHP version string the code needs to match.
 * @return bool If the property matches the PHP version.
 */
function wct_passes( $property, $php_version_to_test_against ) {
	if ( ! is_array( $property ) ) {
		return true;
	}

	$passes_requirements = true;

	if ( isset( $property['php.min'] ) && $property['php.min'] ) {
		$min_php = $property['php.min'];
		$passes_requirements = version_compare( $php_version_to_test_against, $min_php, '>=' );
	}

	if ( $passes_requirements && isset( $property['php.max'] ) && $property['php.max'] ) {
		$max_php = $property['php.max'];
		$passes_requirements = version_compare( $php_version_to_test_against, $max_php, '<=' );
	}

	return $passes_requirements;
}

function wct_validate_request( $path ) {

	$prefix = '/api/v1/';

	if ( $prefix !== substr( $path, 0, strlen( $prefix ) ) ) {
		return false;
	}

	return substr( $path, 8 );
}

function wct_get_test_file_from_request( $file ) {
	if ( ! is_array( $file )
		|| ! isset( $file['error'] )
		|| ! isset( $file['tmp_name'] )
		|| is_array( $file['error'] )
		|| UPLOAD_ERR_OK !== $file['error']
		|| $file['size'] > 1048576 ) {
		return false;
	}

	$file_info = new finfo( FILEINFO_MIME_TYPE );
	$mime_type = $file_info->file( $file['tmp_name'] );

	if ( 'text/x-php' !== $mime_type ) {
		return false;
	}

	return $file['tmp_name'];
}

function wct_respond_with_results( $non_passing_info ) {
	$results = (object) array( 'passes' => true );

	if ( count( $non_passing_info ) > 0 ) {
		$results->passes = false;
		$results->info = $non_passing_info;
	}

	// deliver results
	wct_response( $results );
}

function wct_error( $message, $status_code = 400 ) {

	$response = (object) array(
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