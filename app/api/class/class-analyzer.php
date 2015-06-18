<?php

namespace WCT;

class Analyzer {

	public $metrics = [];

	public $issues = [];

	/**
	 * Use PHP CompatInfo to get metrics of code.
	 * @param string $file_to_analyze PHP file contents as a string
	 * @return bool|array False on error, result array otherwise.
	 */
	public function try_get_metrics( $file_to_analyze ) {
		require_once '../vendor/autoload.php';

		try {

			// creates an instance of client
			$client = new \Bartlett\Reflect\Client();

			// request for a Bartlett\Reflect\Api\Analyser
			$api = $client->api( 'analyser' );

			// perform request, on a data source with default analyser
			$analysers  = [ 'compatibility' ];

			// run the analyzer
			$this->metrics = $api->run( $file_to_analyze, $analysers );

		} catch ( Exception $e ) {

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
		$analyzer_full_name = 'Bartlett\CompatInfo\Analyser\CompatibilityAnalyser';

		if ( ! isset( $this->metrics[ $analyzer_full_name ],
			$this->metrics[ $analyzer_full_name ]['versions'] ) ) {
			return false;
		}

		$versions = $this->metrics[ $analyzer_full_name ]['versions'];
		$passes_requirements = $this->passes( $versions, $php_version_to_test_against );

		$info = $this->get_info_for_non_passing_properties(
			$this->metrics[ $analyzer_full_name ],
			$php_version_to_test_against
		);

		if ( $passes_requirements && count( $info ) > 0 ) {
			// A conflict was found in the metrics.
			return false;
		}

		$this->issues = $info;

		return true;
	}

	/**
	 * Filtered results to only contain issues.
	 * @param array $metrics PHP CompatInfo metrics
	 * @param string $php_version_to_test_against PHP version string the code needs to match.
	 * @return array Filtered results that only contain issues.
	 */
	function get_info_for_non_passing_properties( $metrics, $php_version_to_test_against ) {
		$info = [];

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
					if ( ! isset( $info[ $metric ] ) ) {
						$info[ $metric ] = [];
					}
					$info[ $metric ][ $property_name ] = $this->array_with_only_php_min_max( $property_data );
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
	function array_with_only_php_min_max( $array ) {

		$result = [ 'php_min' => null, 'php_max' => null ];

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