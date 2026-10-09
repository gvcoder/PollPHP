<?php
/**
 * Database Connection Provider (PDO Singleton)
 * PollPHP
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                if (APP_DEBUG) {
                    die('Database Connection Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
                } else {
                    die('A system error occurred. Please try again later.');
                }
            }
        }

        return self::$instance;
    }
}

/**
 * Global helper function to get PDO instance quickly
 */
function get_db(): PDO {
    return Database::getConnection();
}
