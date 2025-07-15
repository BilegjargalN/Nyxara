<?php
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400');
    http_response_code(200);
    exit();
}

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['username'], $data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password are required.']);
    exit;
}

$username = trim($data['username']);
$password = $data['password'];

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password cannot be empty.']);
    exit;
}

if (!is_string($username) || !is_string($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input types.']);
    exit;
}

if (strlen($username) < 3 || strlen($username) > 50) {
    http_response_code(400);
    echo json_encode(['error' => 'Username must be between 3 and 50 characters.']);
    exit;
}

// Password length check (password_hash handles complexity)
if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 8 characters long.']);
    exit;
}

$stmt = $pdo->prepare("SELECT user_id, password_hash FROM Users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials.']);
    exit;
}

$session_token = bin2hex(random_bytes(32));
$expires_at = new DateTime('+1 hour');
$expires_at_str = $expires_at->format('Y-m-d H:i:s');

$stmt = $pdo->prepare("INSERT INTO Sessions (session_id, user_id, expires_at) VALUES (?, ?, ?)");
$stmt->execute([$session_token, $user['user_id'], $expires_at_str]);

echo json_encode([
    'success' => true,
    'session_token' => $session_token,
    'expires_at' => $expires_at_str
]);
?>
