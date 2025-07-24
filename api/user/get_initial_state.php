<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
define('NYXARA_ROOT', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth_check.php';

try {
    $user_id = validate_session_and_get_user_id($pdo);

    $stmt = $pdo->prepare("SELECT response FROM Chatbot_Interactions WHERE user_id = ? ORDER BY timestamp DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $last_interaction = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT save_data FROM Game_Progress WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $game_progress = $stmt->fetch();

    if ($last_interaction && $game_progress) {
        $response_data = json_decode($last_interaction['response'], true);
        $context_data = json_decode($game_progress['save_data'], true);

        if ($response_data && $context_data) {
            echo json_encode([
                'success' => true,
                'has_history' => true,
                'last_dialogue' => $response_data['dialogue'] ?? [],
                'last_choices' => $response_data['choices'] ?? [],
                'context' => $context_data
            ]);
            exit;
        }
    }

    echo json_encode(['success' => true, 'has_history' => false]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
