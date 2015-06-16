<?php

$test_data = file_get_contents( dirname( dirname( __FILE__ ) ) . '/index.php' );
//$test_data = file_get_contents( __FILE__ );

$payload = (object) array(
	'file' => base64_encode( $test_data )
);

$url = 'http://phpcompatapi.dev/api/v1/test/';

$ch = curl_init();

curl_setopt( $ch, CURLOPT_URL, $url );
curl_setopt( $ch, CURLOPT_POST, true );
curl_setopt( $ch, CURLOPT_HEADER, 1 );
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
curl_setopt( $ch, CURLOPT_POSTFIELDS, array(
	'file' => '@' . realpath('../index.php') . ';type=text/x-php'
	//'file' => '@' . realpath('test.php') . ';type=text/x-php'
) );

$response = curl_exec( $ch );

$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($response, 0, $header_size);
$body = substr( $response, $header_size );

print_r( $body );

curl_close( $ch );