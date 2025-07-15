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

function decrypt_api_keys($encrypted) {
    if (!$encrypted) return null;
    $data = base64_decode($encrypted);
    $ivlen = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = substr($data, 0, $ivlen);
    $ciphertext = substr($data, $ivlen);
    $decrypted = openssl_decrypt($ciphertext, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    return json_decode($decrypted, true);
}

try {
    require_once __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../../config/auth_check.php';
    $user_id = validate_session_and_get_user_id($pdo);

    $stmt = $pdo->prepare('SELECT api_key_encrypted FROM Users WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    
    $keys = null;
    if ($row && $row['api_key_encrypted']) {
        $keys = decrypt_api_keys($row['api_key_encrypted']);
    }
    
    // Ensure keys is always an array
    if (!$keys) {
        $keys = ['deepseek' => '', 'openai' => '', 'gemini' => ''];
    }

    echo json_encode(['success' => true, 'keys' => $keys]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 