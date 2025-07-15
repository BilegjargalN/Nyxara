<?php
if (!defined('NYXARA_ROOT')) {
    define('NYXARA_ROOT', true);
}

function get_bearer_token() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
         if (preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            return $matches[1];
        }
    }
    return null;
}

function validate_session_and_get_user_id(PDO $pdo) {
    $log_file = __DIR__ . '/../logs/auth.log';
    $log_message = "--- [" . date('Y-m-d H:i:s') . "] ---" . PHP_EOL;

    $token = get_bearer_token();
    $log_message .= "Authorization Header: " . ($_SERVER['HTTP_AUTHORIZATION'] ?? 'Not found') . PHP_EOL;
    $log_message .= "Extracted Token: " . ($token ?? 'Not found') . PHP_EOL;

    if (!$token) {
        file_put_contents($log_file, $log_message, FILE_APPEND);
        throw new Exception("Authorization token not found.");
    }

    try {
        $stmt = $pdo->prepare("SELECT user_id, expires_at FROM Sessions WHERE session_id = ?");
        $stmt->execute([$token]);
        $session = $stmt->fetch();
    } catch (PDOException $e) {
        $log_message .= "DB Query Failed: " . $e->getMessage() . PHP_EOL;
        file_put_contents($log_file, $log_message, FILE_APPEND);
        throw new Exception("Database error during session validation.");
    }
    
    if (!$session) {
        $log_message .= "DB Result: Token not found in database." . PHP_EOL;
        file_put_contents($log_file, $log_message, FILE_APPEND);
        throw new Exception("Invalid or expired session. Please log in again.");
    }

    $expires_at = new DateTime($session['expires_at']);
    $now = new DateTime();
    if ($now >= $expires_at) {
        $log_message .= "DB Result: Token found, but expired at " . $expires_at->format('Y-m-d H:i:s') . PHP_EOL;
        file_put_contents($log_file, $log_message, FILE_APPEND);
        throw new Exception("Invalid or expired session. Please log in again.");
    }

    $log_message .= "DB Result: Success! User ID: " . $session['user_id'] . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);

    return $session['user_id'];
}
?>