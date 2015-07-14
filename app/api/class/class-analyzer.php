<?php

namespace WCT;

use \Bartlett\Reflect\Client;

class Analyzer {

	public $metrics = array();

	public $issues = array();

	public $passes_requirements = false;

	// Perform request by default with WCT\ExtendedCompatibilityAnalyser.
	public $analysers = array( 'extendedcompatibility' );

	protected $api;

	public function __construct() {

		// Set up environment variables for PHP CompatInfo to read configuration.
		$app_root_dir = dirname( dirname( dirname( __DIR__ ) ) );
		putenv( 'BARTLETT_SCAN_DIR=' . $app_root_dir );
		putenv( 'BARTLETTRC=php-compatinfo-conf.json' );

		$client = new Client();

		// Request for Bartlett\Reflect\Api\Analyser.
		$this->api = $client->api( 'analyser' );
	}

	/**
	 * Use PHP CompatInfo to get metrics of code.
	 * @param string $file_to_analyze PHP file contents as a string
	 * @return bool|array False on error, result array otherwise.
	 */
	public function try_get_metrics( $file_to_analyze ) {

		try {

			// Run the analyzer.
			$metrics = $this->api->run( $file_to_analyze, $this->analysers );

			// Analyzer returns an Exception if the temp directory
			// contains resources the current user has no access to.
			if ( is_a( $metrics, 'Exception' ) ) {
				error_log( 'Analysis failed: ' . $metrics );
				return false;
			}

			$this->metrics = $metrics;

		} catch ( Exception $e ) {
			error_log( 'Analysis failed: ' . $e );
			return false;
		}

		return true;
	}

	/**
	 * Test if PHP CompatInfo metrics match required PHP version.
	 * @param array $metrics PHP CompatInfo metrics
	 * @param string $php_version_to_test_against PHP version string the code needs to match.
	 * @return bool|array Filtered results that only contain issues. False on failure. Empty return array is a pass.
	 */
	public function try_get_issues( $php_version_to_test_against ) {
		$analyzer_full_name = 'WCT\ExtendedCompatibilityAnalyser';

		if ( ! isset( $this->metrics[ $analyzer_full_name ],
			$this->metrics[ $analyzer_full_name ]['versions'] ) ) {
			return false;
		}

		$versions = $this->metrics[ $analyzer_full_name ]['versions'];

		$this->passes_requirements = $this->passes( $versions, $php_version_to_test_against );

		$issues = $this->get_info_for_non_passing_properties(
			$this->metrics[ $analyzer_full_name ],
			$php_version_to_test_against
		);

		// If issues requiring a PHP version greater than specified are found,
		// but the general level info says it's OK, they probably are nothing
		// to worry about e.g. dealt with proper function_exists calls.
		if ( $this->passes_requirements && count( $issues ) > 0 ) {
			$issues = [];
		}

		$this->issues = $issues;

		return true;
	}

	/**
	 * Filtered results to only contain issues.
	 * @param array $metrics PHP CompatInfo metrics
	 * @param string $php_version_to_test_against PHP version string the code needs to match.
	 * @return array Filtered results that only contain issues.
	 */
	function get_info_for_non_passing_properties( $metrics, $php_version_to_test_against ) {
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
				if ( ! $this->passes( $property_data, $php_version_to_test_against ) ) {
					if ( ! isset( $issues[ $metric ] ) ) {
						$issues[ $metric ] = array();
					}
					$issues[ $metric ][ $property_name ] = $this->array_with_only_php_min_max( $property_data );
				}
			}
		}

		return $issues;
	}

	/**
	 * Pick only two out of PHP CompatInfo's numerous keys
	 * @param $array Array to filter
	 * @return array Array filtered
	 */
	function array_with_only_php_min_max( $array ) {

		$result = array( 'php_min' => null, 'php_max' => null );

		foreach ( array_keys( $result ) as $key ) {
			if ( isset( $array[ str_replace( '_', '.', $key ) ] ) ) {
				$result[ str_replace( '.', '_', $key ) ] = $array[ str_replace( '_', '.', $key ) ];
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
	function passes( $property, $php_version_to_test_against ) {
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
}