<?php

namespace WCT;

class RequestHandler {

	protected $responder;
	protected $analyzer;

	protected $php_version;
	protected $root_path;
	protected $temp_dir;
	protected $max_upload_size;

	public function __construct( $responder, $analyzer, $options = array() ) {
		$this->responder = $responder;
		$this->analyzer  = $analyzer;

		$this->set_options( $options );
	}

	/**
	 * Set options of the API.
	 * @param array The options.
	 */
	protected function set_options( $options ) {
		$defaults = array(
			'php_version' => '5.4.0',
			'root_path' => '/',
			'temp_dir' => sys_get_temp_dir(),
			'max_upload_size' => 1048576,
		);

		foreach ( $defaults as $key => $value ) {
			if ( isset( $options[ $key ] ) && ! empty( $options[ $key ] ) ) {
				$this->$key = $options[ $key ];
			} else {
				$this->$key = $value;
			}
		}
	}

	/**
	 * The main function of the app. The only place where $_SERVER and $_FILES are read from. 
	 */
	public function run() {

		// Validate the overall request to this API
		$resource = $this->validate_request( $_SERVER['REQUEST_URI'], $this->root_path );
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

		$issues = array();

		// Loop through POSTed files
		for ( $i = 0; $i < count( $_FILES['file']['name'] ); $i++ ) {

			$orig_filename = $_FILES['file']['name'][ $i ];

			// Get file from the request
			$file_name = $this->get_test_file_from_request( $_FILES['file'], $i );
			if ( false === $file_name ) {
				$this->responder->respond_error( 'Invalid file upload ' . $orig_filename  . '.' );
			}

			// Move to temporary directory.
			$temp_file_name = tempnam( $this->temp_dir, 'wct');
			move_uploaded_file( $file_name, $temp_file_name );

			// Parse and analyze file for metrics
			$result = $this->analyzer->try_get_metrics( $temp_file_name );

			// Make sure the temp file gets deleted immediately
			unlink( $temp_file_name );

			if ( false === $result ) {
				$this->responder->respond_error( 'Unable to parse the file ' . $orig_filename . '.', 500 );
			}

			// Get info on possible non-conforming issues
			$result = $this->analyzer->try_get_issues( $this->php_version );
			if ( false === $result ) {
				$this->responder->respond_error( 'Unable to determine compatibility of ' . $orig_filename  . '.', 500 );
			}

			// Store issues found by file
			if ( count( $this->analyzer->issues ) > 0 ) {
				$issues[] = array( 'file' => $orig_filename, 'issues' => $this->analyzer->issues );
			}
		}

		// Respond in JSON with the issues found, if any.
		$this->responder->respond_with_results( $issues );

	}

	/**
	 * Make user the request path starts with api/v1
	 * @param string $path Full request path.
	 * @param string $root_path Configured application path, slash for root installation.
	 * @return bool|string False if not matching. The rest of the path otherwise.
	 */
	protected function validate_request( $path, $root_path ) {

		$prefix = $root_path . 'api/v1/';

		if ( $prefix !== substr( $path, 0, strlen( $prefix ) ) ) {
			return false;
		}

		return substr( $path, strlen( $prefix ) );
	}

	/**
	 * Description
	 *
	 * @param array $files A file descriptor from $_FILES.
	 * @param int   $i     Index of the file to get.
	 *
	 * @return bool|string False if the file was not accepted. Path to temp file if it was.
	 */
	protected function get_test_file_from_request( $files, $i ) {

		// Make sure the file is solid and the size is not bigger than the max.
		if ( ! is_array( $files )
			|| ! isset( $files['error'][ $i ] )
			|| ! isset( $files['tmp_name'][ $i ] )
			|| UPLOAD_ERR_OK !== $files['error'][ $i ]
			|| $this->max_upload_size < $files['size'][ $i ] ) {
			return false;
		}

		// Get the mime type of the file from the file system,
		// since we cannot trust the info from the upload.
		$file_info = new \finfo( FILEINFO_MIME_TYPE );
		$mime_type = $file_info->file( $files['tmp_name'][ $i ] );

		// Test the mime type. 'text/html' is for PHP files that start with HTML.
		if ( 'text/x-php' !== $mime_type && 'text/html' !== $mime_type ) {
			return false;
		}

		return $files['tmp_name'][ $i ];
	}
}