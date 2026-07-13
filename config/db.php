<?php
/**
 * Database Configuration
 *
 * Establishes a PDO connection to MySQL and exposes a singleton-style
 * getter. All queries in the application use this connection.
 */

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'parcel_delivery_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application-wide constants
define('APP_NAME',    'ParcelTrack Pro');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    'http://localhost/parcel_delivery_system');
define('UPLOAD_DIR',  __DIR__ . '/../assets/uploads/proofs/');
define('UPLOAD_URL',  BASE_URL . '/assets/uploads/proofs/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB

/**
 * Returns a shared PDO instance (lazy singleton).
 *
 * @return PDO
 * @throws RuntimeException on connection failure
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Log to server error log; never expose DB credentials to the browser.
        error_log('[DB] Connection failed: ' . $e->getMessage());
        http_response_code(503);
        die('Database connection unavailable. Please try again later.');
    }

    return $pdo;
}
