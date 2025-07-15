<?php
if (!defined('NYXARA_ROOT')) {
    define('NYXARA_ROOT', true);
}

require_once __DIR__ . '/env_loader.php';

// Set default timezone
date_default_timezone_set('UTC');

// Database credentials
$host = getenv('DB_HOST') ?: '127.0.0.1';
$dbname = getenv('DB_NAME') ?: 'nyxara2';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$port = getenv('DB_PORT') ?: '3306';

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Log the detailed error to a file for the developer
    $log_message = "[" . date("Y-m-d H:i:s") . "] " . "Database connection failed: " . $e->getMessage() . "\n";
    if (!file_exists(__DIR__ . '/../logs')) {
        mkdir(__DIR__ . '/../logs', 0777, true);
    }
    file_put_contents(__DIR__ . '/../logs/db_errors.log', $log_message, FILE_APPEND);
    
    // Throw a generic exception to be caught by the API endpoint
    throw new Exception('Could not connect to the database. Please try again later.');
}
?>