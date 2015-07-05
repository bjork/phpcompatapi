## Synopsis

PHP Compat API creates an API to test PHP code compatibility against the PHP version that you configure. It uses the [PHP CompatInfo library](https://github.com/llaville/php-compat-info) to determine the results. There is also a simple React based UI to test your files from.

## Installation

Download the application files and set your web server to point to the app directory of the application. Copy config-sample.php as config.php and modify the contents to suit your needs. Make sure you have [Composer](https://getcomposer.org/) installed. Run 

	composer install
 
at the project root.

## API Reference

There is only one API endpoint: `/api/v1/test/` and it only accepts `POST`. Upload one or more files to it. The files should be named "file[]".

## License

GPLv3