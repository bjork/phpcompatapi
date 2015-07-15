<?php

// Define the PHP version to test against.
define( 'WCT_PHP_VERSION', '5.4.0' );

// Define the URL path where the app is run in.
// Should be just a slash, if run on root.
// If you do a subdirectory install, also edit
// the second line of app/.htaccess to be:
// RewriteBase /app/
define( 'WCT_ROOT_PATH', '/' );

// Define temporary directory. Optional, by default
// uses sys_get_temp_dir()
// define( 'WCT_TEMP_DIR', '/path/to/temp/dir' );

// Define single file upload size limit in bytes. Defaults to one megabyte.
// define( 'WCT_MAX_UPLOAD_SIZE', 1048576 );