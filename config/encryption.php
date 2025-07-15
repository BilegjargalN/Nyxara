<?php

if (!defined('NYXARA_ROOT')) {
    define('NYXARA_ROOT', true);
}

if (!function_exists('loadEnv')) {
    require_once __DIR__ . '/env_loader.php';
}

$encryption_key = getenv('ENCRYPTION_KEY');

if (!$encryption_key || strlen($encryption_key) < 32) {
    throw new Exception(
        'ENCRYPTION_KEY is not set or is not long enough. ' .
        'Please generate a 32-character random string and add it to your .env file. ' .
        'Example: ENCRYPTION_KEY="your_32_character_random_string_here"'
    );
}

define('ENCRYPTION_KEY', $encryption_key);
define('ENCRYPTION_METHOD', 'AES-256-CBC');

?>