<?php

namespace WCT;

class Responder {

	public function respond_with_results( $passes, $issues ) {
		$results = (object) array( 'passes' => $passes );

		if ( count( $issues ) > 0 ) {
			$results->passes = false;
			$results->info = $issues;
		}

		// deliver results
		$this->do_response_with( $results );
	}

	public function respond_error( $message, $status_code = 400 ) {

		$response = (object) array( 'error' => $message );

		$this->do_response_with( $response, $status_code );

	}

	public function do_response_with( $response_object, $status_code = 200 ) {

		if ( ! headers_sent() ) {
			header( 'Content-Type: application/json' );
			http_response_code( $status_code );
		}

		$json = json_encode( $response_object );
		echo $json;

		exit();

	}
}