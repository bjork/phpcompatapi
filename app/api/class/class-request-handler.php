<?php

namespace WCT;

class RequestHandler {

	protected $responder;
	protected $analyzer;

	public function __construct( $responder, $analyzer ) {
		$this->responder = $responder;
		$this->analyzer  = $analyzer;
	}

	public function run( $php_version_to_test_against, $root_path ) {

		// Validate the overall request to this API
		$resource = $this->validate_request( $_SERVER['REQUEST_URI'], $root_path );
		if ( false === $resource ) {
			$this->responder->respond_error( 'Invalid request' );
		}

		// Validate the request to this specific compatibility test resource
		if ( 'test' !== substr( $resource, 0, 4 ) ) {
			$this->responder->respond_error( 'Unsupported API resource' );
		}

		// Validate HTTP method
		if ( ! isset( $_SERVER['REQUEST_METHOD'] )
			|| 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			$this->responder->respond_error( 'Invalid HTTP method', 405 );
		}

		if ( ! isset( $_FILES['file'] ) ) {
			$this->responder->respond_error( 'Missing file upload.' );
		}

		// Get file from the request
		$file_name = $this->get_test_file_from_request( $_FILES['file'] );
		if ( false === $file_name ) {
			$this->responder->respond_error( 'Invalid file upload.' );
		}

		// Parse and analyze file for metrics
		$result = $this->analyzer->try_get_metrics( $file_name );

		// Make sure the temp file gets deleted immediately
		unlink( $file_name );
		
		if ( false === $result ) {
			$this->responder->respond_error( 'Unable to parse the file.', 500 );
		}

		// Get info on possible non-conforming issues
		$result = $this->analyzer->try_get_issues( $php_version_to_test_against );
		if ( false === $result ) {
			$this->responder->respond_error( 'Unable to determine compatibility.', 500 );
		}

		$this->responder->respond_with_results( $this->analyzer->issues );

	}

	protected function validate_request( $path, $root_path ) {

		$prefix = $root_path . 'api/v1/';

		if ( $prefix !== substr( $path, 0, strlen( $prefix ) ) ) {
			return false;
		}

		return substr( $path, 8 );
	}

	protected function get_test_file_from_request( $file ) {
		if ( ! is_array( $file )
			|| ! isset( $file['error'] )
			|| ! isset( $file['tmp_name'] )
			|| is_array( $file['error'] )
			|| UPLOAD_ERR_OK !== $file['error']
			|| $file['size'] > 1048576 ) {
			return false;
		}

		$file_info = new \finfo( FILEINFO_MIME_TYPE );
		$mime_type = $file_info->file( $file['tmp_name'] );

		if ( 'text/x-php' !== $mime_type && 'text/html' !== $mime_type ) {
			return false;
		}

		return $file['tmp_name'];
	}
}