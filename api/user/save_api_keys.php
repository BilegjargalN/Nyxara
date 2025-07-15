<?php
// Handle preflight OPTIONS requests (for CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(200);
    exit();
}

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/encryption.php';

function encrypt_api_keys($data) {
    $ivlen = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $ciphertext = openssl_encrypt(json_encode($data), ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    return base64_encode($iv . $ciphertext);
}

try {
    require_once __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../../config/auth_check.php';
    $user_id = validate_session_and_get_user_id($pdo);

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['keys'])) {
        throw new Exception('Missing keys.');
    }
    $keys = $input['keys'];

    if (!is_array($keys)) {
        throw new Exception('Invalid keys format.');
    }

    // Validate individual API keys
    foreach ($keys as $provider => $key) {
        if (!is_string($key)) {
            throw new Exception("API key for $provider must be a string.");
        }
        if (strlen($key) > 512) { // Increased limit for safety
            throw new Exception("API key for $provider is too long.");
        }
    }

    // Encrypt and store API keys
    $encrypted = encrypt_api_keys($keys);
    $stmt = $pdo->prepare('UPDATE Users SET api_key_encrypted = ? WHERE user_id = ?');
    $stmt->execute([$encrypted, $user_id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 