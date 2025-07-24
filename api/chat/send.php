<?php
// Handle preflight OPTIONS requests (for CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(200);
    exit();
}

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
define('NYXARA_ROOT', true);

ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('ERROR_LOG', __DIR__.'/../../logs/error.log');
define('INVALID_RESP_LOG', __DIR__.'/../../logs/invalid_responses.log');
define('DB_ERROR_LOG', __DIR__.'/../../logs/db_errors.log');

try {
    require_once __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../../config/auth_check.php';
    require_once __DIR__ . '/../../config/api_config.php';
    require_once __DIR__ . '/../../config/encryption.php';
    require_once __DIR__ . '/ai_services.php';

    $user_id = validate_session_and_get_user_id($pdo);
    
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['input'])) {
        throw new Exception("Input is required.");
    }

    $user_input = trim($input['input']);
    $provider = $input['provider'] ?? 'deepseek';

    if (empty($user_input)) {
        throw new Exception("Input cannot be empty.");
    }
    
    $allowed_providers = ['deepseek', 'openai', 'gemini'];
    if (!in_array($provider, $allowed_providers)) {
        throw new Exception("Invalid AI provider specified.");
    }

    if (strlen($user_input) > 2000) {
        throw new Exception("User input is too long. Please keep it under 2000 characters.");
    }

    $stmt = $pdo->prepare("SELECT api_key_encrypted FROM Users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    $user_keys = ['deepseek' => '', 'openai' => '', 'gemini' => ''];
    if ($row && $row['api_key_encrypted']) {
        
        $data = base64_decode($row['api_key_encrypted']);
        $ivlen = openssl_cipher_iv_length(ENCRYPTION_METHOD);
        $iv = substr($data, 0, $ivlen);
        $ciphertext = substr($data, $ivlen);
        $decrypted = openssl_decrypt($ciphertext, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
        $user_keys = json_decode($decrypted, true) ?: $user_keys;
    }
    $api_key = $user_keys[$provider] ?? '';
    if (empty($api_key)) {
        throw new Exception(ucfirst($provider) . " API key is not set. Please set it in the settings menu (gear icon).");
    }

    $stmt = $pdo->prepare("SELECT input, response FROM Chatbot_Interactions WHERE user_id = ? ORDER BY timestamp DESC LIMIT 10");
    $stmt->execute([$user_id]);
    $history = array_reverse($stmt->fetchAll());

    $conversation_history = [];
    foreach ($history as $interaction) {
        $conversation_history[] = ['role' => 'user', 'content' => $interaction['input']];
        $response_data = json_decode($interaction['response'], true);
        if ($response_data && isset($response_data['dialogue'])) {
            $assistant_response = "";
            foreach ($response_data['dialogue'] as $dialogue) {
                $assistant_response .= $dialogue['speaker'] . ": " . $dialogue['text'] . "\n";
            }
            $conversation_history[] = ['role' => 'assistant', 'content' => trim($assistant_response)];
        }
    }

    $default_context = [
        'current_chapter' => 1, 'nyx_form' => 'human_neutral', 'relationship_score' => 0,
        'time_remaining' => 300, 'story_events' => [], 'first_meeting' => true
    ];

    $stmt = $pdo->prepare("SELECT save_data FROM Game_Progress WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    
    $context = $row && $row['save_data'] ? json_decode($row['save_data'], true) : $default_context;
    $context = array_merge($default_context, $context);

    $recent_events = implode(", ", array_slice($context['story_events'], -3));
    $history_for_prompt = implode("\n", array_map(function($item) {
        return $item['role'] . ': ' . $item['content'];
    }, $conversation_history));

    $system_prompt = <<<EOD
You are the narrative director for "Nyxara: Apophasis" - a dungeon escape visual novel. The player is a prisoner trying to escape, and Nyx is their guard who has supernatural abilities.

CURRENT GAME STATE:
- Nyx Form: {$context['nyx_form']}
- Relationship Score: {$context['relationship_score']} (-100 to +100)
- Time Remaining: {$context['time_remaining']} seconds
- Chapter: {$context['current_chapter']}
- Recent Events: {$recent_events}
- First Meeting: {$context['first_meeting']}

CONVERSATION HISTORY:
{$history_for_prompt}

CHARACTER FORMS AVAILABLE:
- human_neutral, human_happy, human_sad, human_suspicious
- demon_angry, shadow_mysterious, ethereal_magical, dragon_powerful

STORYTELLING RULES:
1. Nyx ALWAYS starts in human form unless relationship is very high (80+).
2. Form changes must be story-driven.
3. Dialogue must be 2-4 sentences minimum.
4. Remember ALL previous conversations.
5. Relationship changes based on interaction quality (-5 to +5).
6. Time cost varies by interaction complexity (8-15 seconds).
7. Track meaningful story events.

RESPONSE FORMAT (JSON ONLY):
{
    "dialogue": [
        {"speaker": "Nyx", "text": "Long, detailed character dialogue..."},
        {"speaker": "Narrator", "text": "Rich atmospheric description..."}
    ],
    "choices": [
        {"text": "A short, distinct choice for the player."},
        {"text": "A second, different choice."},
        {"text": "A third, contrasting choice."}
    ],
    "form_change": "human_neutral",
    "relationship_change": 0,
    "time_cost": 10,
    "story_event": "Brief description of what happened this turn"
}

Player Input: {$input['input']}
EOD;

    $raw_content = null;
    try {
        $ai_service = ServiceFactory::create($provider);
        $raw_content = $ai_service->generateResponse($system_prompt, $conversation_history, $input['input'], $api_key);
    } catch (Exception $e) {
        throw new Exception("AI Service Error: " . $e->getMessage());
    }

    if ($raw_content === null) {
        throw new Exception("AI service returned an empty response.");
    }

    $clean_json = preg_replace('/^```json|```$/m', '', trim($raw_content));
    $game_data = json_decode($clean_json, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($game_data['dialogue'])) {
        error_log(date('[Y-m-d H:i:s] ')."Invalid JSON from {$provider}: $clean_json\n", 3, INVALID_RESP_LOG);
        $game_data = [
            'dialogue' => [['speaker' => 'Nyx', 'text' => 'I watch you carefully...'], ['speaker' => 'Narrator', 'text' => 'The dungeon air grows heavy...']],
            'choices' => [['text' => 'Wait and see what happens.'], ['text' => 'Say something.']],
            'form_change' => $context['nyx_form'], 'relationship_change' => 0, 'time_cost' => 10, 'story_event' => 'Tense standoff'
        ];
    }

    $game_data['time_cost'] = max(8, min(15, (int)($game_data['time_cost'] ?? 10)));
    $game_data['relationship_change'] = max(-5, min(5, (int)($game_data['relationship_change'] ?? 0)));
    
    $valid_forms = ['human_neutral', 'human_happy', 'human_sad', 'human_suspicious', 'demon_angry', 'shadow_mysterious', 'ethereal_magical', 'dragon_powerful'];
    if (!in_array($game_data['form_change'] ?? '', $valid_forms)) {
        $game_data['form_change'] = $context['nyx_form'];
    }

    if ($context['first_meeting'] || $context['relationship_score'] < 50) {
        $supernatural_forms = ['demon_angry', 'shadow_mysterious', 'ethereal_magical', 'dragon_powerful'];
        if (in_array($game_data['form_change'], $supernatural_forms)) {
            $game_data['form_change'] = 'human_suspicious';
        }
    }

    $context['time_remaining'] = max(0, $context['time_remaining'] - $game_data['time_cost']);
    $context['relationship_score'] = max(-100, min(100, $context['relationship_score'] + $game_data['relationship_change']));
    $context['nyx_form'] = $game_data['form_change'];
    $context['first_meeting'] = false;
    
    if (!empty($game_data['story_event'])) {
        $context['story_events'][] = $game_data['story_event'];
        $context['story_events'] = array_slice($context['story_events'], -10);
    }

    

    try {
        $stmt = $pdo->prepare("INSERT INTO Chatbot_Interactions (user_id, input, response, nyx_state, time_cost) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, substr($input['input'], 0, 65535), json_encode($game_data), $context['nyx_form'], $game_data['time_cost']]);

        $progress_stmt = $pdo->prepare("INSERT INTO Game_Progress (user_id, save_data) VALUES (?, ?) ON DUPLICATE KEY UPDATE save_data = VALUES(save_data), last_saved = NOW()");
        $progress_stmt->execute([$user_id, json_encode($context)]);
    } catch (PDOException $e) {
        error_log(date('[Y-m-d H:i:s] ').$e->getMessage()."\n", 3, DB_ERROR_LOG);
    }

    ob_clean();
    echo json_encode(['success' => true, 'dialogue' => $game_data['dialogue'], 'choices' => $game_data['choices'] ?? [], 'context' => $context]);

} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

ob_end_flush();
?>
