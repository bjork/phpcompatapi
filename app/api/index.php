<?php

/**
 * API handler
 */

require_once( '../vendor/autoload.php' );
require_once( 'class-extendedcompatibilityanalyzer.php' );

/**
 * The main function of the app.
 */
function run() {

	validate_request();

	$all_issues = array();

	// Stores whether all the files pass requirements.
	$passes = true;

	// Loop through POSTed files
	for ( $i = 0; $i < count( $_FILES['file']['name'] ); $i++ ) {

		$orig_filename = $_FILES['file']['name'][ $i ];

		// Get file from the request
		$temp_file_name = get_test_file_from_request( $_FILES['file'], $i );
		if ( false === $temp_file_name ) {
			respond_error( 'Invalid file upload ' . $orig_filename  . '.' );
		}

		// Parse and analyze file for metrics
		$result = try_get_metrics( $temp_file_name );

		// Make sure the temp file gets deleted immediately
		unlink( $temp_file_name );

		if ( false === $result ) {
			respond_error( 'Unable to parse the file ' . $orig_filename . '.', 500 );
		}

		// Get info on possible non-conforming issues
		$issues = try_get_issues( $result, $passes_requirements );
		if ( false === $issues ) {
			respond_error( 'Unable to determine compatibility of ' . $orig_filename  . '.', 500 );
		}

		// Store issues found by file
		if ( ! $passes_requirements ) {
			$passes = false;
			$all_issues[] = array( 'file' => $orig_filename, 'issues' => $issues );
		}
	}

	// Respond in JSON with the issues found, if any.
	respond_with_results( $passes, $all_issues );

}

/**
 * Do basic validation to the request
 */
function validate_request() {
	// Validate the request to this API
	$prefix = WCT_ROOT_PATH . 'api/v1/test/';
	if ( $prefix !== substr( $_SERVER['REQUEST_URI'], 0, strlen( $prefix ) ) ) {
		respond_error( 'Invalid request' );
	}

	// Validate HTTP method
	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
		respond_error( 'Invalid HTTP method', 405 );
	}

	if ( ! isset( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
		respond_error( 'Missing file upload.' );
	}

	// Only allow max_file_uploads-1 files to be uploaded
	// Without this, those exceeding files would be silently ignored.
	if ( isset( $_FILES['file']['name'][ ini_get( 'max_file_uploads' ) - 1 ] ) ) {
		respond_error( 'Maximum number of files (' . ( ini_get( 'max_file_uploads' ) - 1 ) . ') exceeded.' );
	}
}

/**
 * Get a single file out of request.
 *
 * @param array $files A file descriptor from $_FILES.
 * @param int   $i     Index of the file to get.
 *
 * @return bool|string False if the file was not accepted. Path to temp file if it was.
 */
function get_test_file_from_request( $files, $i ) {
	// Make sure the file is solid and the size is not bigger than the max.
	if ( ! isset( $files['error'][ $i ] )
		|| ! isset( $files['tmp_name'][ $i ] )
		|| UPLOAD_ERR_OK !== $files['error'][ $i ]
		|| WCT_MAX_UPLOAD_SIZE < $files['size'][ $i ] ) {
		return false;
	}

	// Get the mime type of the file from the file system,
	// since we cannot trust the info from the upload.
	$file_info = new finfo( FILEINFO_MIME_TYPE );
	$mime_type = $file_info->file( $files['tmp_name'][ $i ] );

	// Test the mime type. 'text/html' is for PHP files that start with HTML.
	if ( 'text/x-php' !== $mime_type && 'text/html' !== $mime_type ) {
		return false;
	}

	// Move to temporary directory.
	$temp_file_name = tempnam( WCT_TEMP_DIR, 'wct' );
	move_uploaded_file( $files['tmp_name'][ $i ], $temp_file_name );

	return $temp_file_name;
}

/**
 * Use PHP CompatInfo to get metrics of code.
 * 
 * @param string $file_to_analyze PHP file contents as a string.
 * 
 * @return bool|array False on error, result array otherwise.
 */
function try_get_metrics( $file_to_analyze ) {
	// Set up environment variables for PHP CompatInfo to read configuration.
	$app_root_dir = dirname( dirname( __DIR__ ) );
	putenv( 'BARTLETT_SCAN_DIR=' . $app_root_dir );
	putenv( 'BARTLETTRC=php-compatinfo-conf.json' );

	$client = new Bartlett\Reflect\Client();

	// Request for Bartlett\Reflect\Api\Analyser.
	$api = $client->api( 'analyser' );

	$analysers = array( 'extendedcompatibility' );

	try {
		// Run the analyzer.
		$metrics = $api->run( $file_to_analyze, $analysers );
	} catch ( Exception $e ) {
		error_log( 'Analysis failed: ' . $e );
		return false;
	}

	// Analyzer returns an Exception if the temp directory
	// contains resources the current user has no access to.
	if ( is_a( $metrics, 'Exception' ) ) {
		error_log( 'Analysis failed: ' . $metrics );
		return false;
	}

	return $metrics;
}

/**
 * Test if PHP CompatInfo metrics match required PHP version.
 * 
 * @param array  $metrics PHP CompatInfo metrics
 * @param bool   $passes_requirements Weather the requirements are passed. There might be no reported issues but still not passing.
 * 
 * @return bool|array Filtered results that only contain issues. False on failure.
 */
function try_get_issues( $metrics, &$passes_requirements ) {
	$analyzer_full_name = 'ExtendedCompatibilityAnalyser';

	if ( ! isset( $metrics[ $analyzer_full_name ],
		$metrics[ $analyzer_full_name ]['versions'] ) ) {
		return false;
	}

	$versions = $metrics[ $analyzer_full_name ]['versions'];

	$passes_requirements = passes( $versions );

	$issues = get_info_for_non_passing_properties( $metrics[ $analyzer_full_name ] );

	// If issues requiring a PHP version greater than specified are found,
	// but the general level info says it's OK, they probably are nothing
	// to worry about e.g. dealt with proper function_exists calls.
	if ( $passes_requirements && count( $issues ) > 0 ) {
		$issues = [];
	}

	return $issues;
}

/**
 * Filtered results to only contain issues.
 * 
 * @param array $metrics PHP CompatInfo metrics
 * 
 * @return array Filtered results that only contain issues.
 */
function get_info_for_non_passing_properties( $metrics ) {
	$issues = array();

	foreach ( $metrics as $metric => $properties ) {
		// Skip versions and empty properties
		if ( 'versions' === $metric
			|| ! is_array( $properties )
			|| 0 === count( $properties ) ) {
			continue;
		}

		// Gather property data that does not meet the requirements
		foreach ( $properties as $property_name => $property_data ) {
			if ( ! passes( $property_data ) ) {
				if ( ! isset( $issues[ $metric ] ) ) {
					$issues[ $metric ] = array();
				}
				$issues[ $metric ][ $property_name ] = array_with_only_php_min_max( $property_data );
			}
		}
	}

	return $issues;
}

/**
 * Pick only two out of PHP CompatInfo's numerous keys
 * 
 * @param $array Array to filter
 * 
 * @return array Array filtered
 */
function array_with_only_php_min_max( $array ) {
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
 * 
 * @param array $property PHP CompatInfo property
 * 
 * @return bool If the property matches the PHP version.
 */
function passes( $property ) {
	$passes_requirements = true;

	if ( isset( $property['php.min'] ) && $property['php.min'] ) {
		$min_php = $property['php.min'];
		$passes_requirements = version_compare( WCT_PHP_VERSION, $min_php, '>=' );
	}

	if ( $passes_requirements && isset( $property['php.max'] ) && $property['php.max'] ) {
		$max_php = $property['php.max'];
		$passes_requirements = version_compare( WCT_PHP_VERSION, $max_php, '<=' );
	}

	return $passes_requirements;
}

/**
 * Respond with results of analysis
 * 
 * @param bool $passes Does it pass requirements
 * @param array $issues Issues data
 */
function respond_with_results( $passes, $issues ) {
	$results = (object) array( 'passes' => $passes );

	if ( count( $issues ) > 0 ) {
		$results->passes = false;
		$results->info = $issues;
	}

	// deliver results
	do_response_with( $results, 200 );
}

/**
 * Respond with a JSON error message
 * 
 * @param string $message Error message
 * @param int $status_code HTTP Status Code
 */
function respond_error( $message, $status_code = 400 ) {
	$response = (object) array( 'error' => $message );
	do_response_with( $response, $status_code );
}

/**
 * Respond JSON with HTTP status code
 * 
 * @param object $response_object Response object to be JSON encoded
 * @param int $status_code HTTP Status Code
 */
function do_response_with( $response_object, $status_code ) {
	if ( ! headers_sent() ) {
		header( 'Content-Type: application/json' );
		http_response_code( $status_code );
	}

	$json = json_encode( $response_object );
	echo $json;
	exit();
}