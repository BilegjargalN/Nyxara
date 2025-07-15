<?php
// Handle preflight OPTIONS requests (for CORS)
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

define('NYXARA_ROOT', true);

ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('DB_ERROR_LOG', __DIR__.'/../../logs/db_errors.log');

try {
    require_once __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../../config/auth_check.php';
    $user_id = validate_session_and_get_user_id($pdo);

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $offset = isset($input['offset']) ? (int)$input['offset'] : 0;
    $limit = isset($input['limit']) ? (int)$input['limit'] : 10;

    // Fetch chat history using interaction_id for reliable ordering
    $stmt = $pdo->prepare("SELECT input, response FROM Chatbot_Interactions WHERE user_id = ? ORDER BY interaction_id DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

    $formatted_history = [];
    foreach ($history as $interaction) {
        $formatted_history[] = ['speaker' => 'You', 'text' => $interaction['input']];
        $response_data = json_decode($interaction['response'], true);
        if ($response_data && isset($response_data['dialogue'])) {
            foreach ($response_data['dialogue'] as $dialogue) {
                $formatted_history[] = ['speaker' => $dialogue['speaker'], 'text' => $dialogue['text']];
            }
        }
    }

    ob_clean(); // Clean the buffer before output
    echo json_encode(['success' => true, 'history' => $formatted_history]);

} catch (Exception $e) {
    ob_clean(); // Clean the buffer in case of an error
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} finally {
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
}
?>