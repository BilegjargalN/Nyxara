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
$email = $username . '@example.com';

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

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 8 characters long.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT user_id FROM Users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->rowCount() > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'Username is already taken']);
        exit;
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO Users (username, email, password_hash) VALUES (?, ?, ?)");
    $stmt->execute([$username, $email, $password_hash]);
    $user_id = $pdo->lastInsertId();

    // Initialize default game progress in the database
    $default_context = [
        'current_chapter' => 1,
        'nyx_form' => 'human_neutral',
        'relationship_score' => 0,
        'time_remaining' => 300,
        'story_events' => [],
        'first_meeting' => true
    ];
    $progress_stmt = $pdo->prepare("INSERT INTO Game_Progress (user_id, save_data) VALUES (?, ?)");
    $progress_stmt->execute([$user_id, json_encode($default_context)]);

    echo json_encode(['success' => true, 'user_id' => $user_id]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'A database error occurred.']);
}
?>