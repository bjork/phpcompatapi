<?php

if ( '/api/' === substr( $_SERVER['REQUEST_URI'], 0, 5 ) ) {
	include 'api/index.php';
} else {
	include 'app.html';
}