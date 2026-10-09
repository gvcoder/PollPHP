<?php
/**
 * Sample Configuration for Deployment
 * Copy this file to config.php on your remote server and fill in production credentials.
 */

if (!defined('POLLPHP_EXEC')) {
    define('POLLPHP_EXEC', true);
}

define('APP_DEBUG', false);
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

define('APP_NAME', 'PollPHP');
define('APP_URL', 'https://yourdomain.com');
define('APP_SALT', 'replace_with_a_random_32_char_secret_string');

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'your_cpanel_dbname');
define('DB_USER', 'your_cpanel_dbuser');
define('DB_PASS', 'your_cpanel_dbpass');
define('DB_CHARSET', 'utf8mb4');

define('POLL_DURATIONS', [3, 5, 7]);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}
