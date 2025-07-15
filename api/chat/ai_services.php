<?php
if (!defined('NYXARA_ROOT')) {
    die("Direct access not permitted");
}

interface AIService {
    // Add $api_key as a parameter
    public function generateResponse(string $system_prompt, array $conversation_history, string $user_input, string $api_key): ?string;
}

class ServiceFactory {
    public static function create(string $provider): AIService {
        switch ($provider) {
            case 'openai':
                return new OpenAIService();
            case 'gemini':
                return new GeminiService();
            case 'deepseek':
            default:
                return new DeepSeekService();
        }
    }
}

abstract class BaseAIService {
    protected function makeRequest(string $url, array $headers, array $body): ?string {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_CAINFO, 'C:/wamp64/bin/php/cacert.pem');

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            file_put_contents(__DIR__ . '/../../logs/error.log', "cURL Error in " . get_class($this) . ": " . $error . "\n", FILE_APPEND);
            return null;
        }
        
        return $response;
    }
}

class DeepSeekService extends BaseAIService implements AIService {
    public function generateResponse(string $system_prompt, array $conversation_history, string $user_input, string $api_key): ?string {
        global $DEEPSEEK_API_URL;
        if (empty($api_key)) {
            throw new Exception("DeepSeek API key is not set. Please set it in your settings.");
        }
        $messages = array_merge(
            [['role' => 'system', 'content' => $system_prompt]],
            $conversation_history,
            [['role' => 'user', 'content' => $user_input]]
        );
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ];
        $body = [
            'model' => 'deepseek-chat',
            'messages' => $messages,
            'temperature' => 1.1,
            'max_tokens' => 4096,
            'top_p' => 1,
            'stream' => false
        ];
        $response = $this->makeRequest($DEEPSEEK_API_URL, $headers, $body);
        if (!$response) return null;
        $decoded_response = json_decode($response, true);
        return $decoded_response['choices'][0]['message']['content'] ?? null;
    }
}

class OpenAIService extends BaseAIService implements AIService {
    public function generateResponse(string $system_prompt, array $conversation_history, string $user_input, string $api_key): ?string {
        global $OPENAI_API_URL;
        if (empty($api_key)) {
            throw new Exception("OpenAI API key is not set. Please set it in your settings.");
        }
        $messages = array_merge(
            [['role' => 'system', 'content' => $system_prompt]],
            $conversation_history,
            [['role' => 'user', 'content' => $user_input]]
        );
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ];
        $body = [
            'model' => 'gpt-4-turbo',
            'messages' => $messages,
            'temperature' => 1.1,
            'max_tokens' => 4095,
            'top_p' => 1,
        ];
        $response = $this->makeRequest($OPENAI_API_URL, $headers, $body);
        if (!$response) return null;
        $decoded_response = json_decode($response, true);
        return $decoded_response['choices'][0]['message']['content'] ?? null;
    }
}

class GeminiService extends BaseAIService implements AIService {
    public function generateResponse(string $system_prompt, array $conversation_history, string $user_input, string $api_key): ?string {
        global $GEMINI_API_URL;
        if (empty($api_key)) {
            throw new Exception("Gemini API key is not set. Please set it in your settings.");
        }
        $contents = [];
        if (!empty($system_prompt)) {
             $contents[] = ['role' => 'user', 'parts' => [['text' => "SYSTEM PROMPT (Follow these instructions carefully): " . $system_prompt]]];
             $contents[] = ['role' => 'model', 'parts' => [['text' => "Understood. I will follow the system prompt and generate the response in the requested JSON format."]]];
        }
        foreach ($conversation_history as $msg) {
            $role = ($msg['role'] === 'assistant') ? 'model' : 'user';
            $contents[] = ['role' => $role, 'parts' => [['text' => $msg['content']]]];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $user_input]]];
        $urlWithKey = $GEMINI_API_URL . '?key=' . $api_key;
        $headers = ['Content-Type: application/json'];
        $body = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 1.0,
                'topP' => 1,
                'maxOutputTokens' => 8192,
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
            ]
        ];
        $response = $this->makeRequest($urlWithKey, $headers, $body);
        if (!$response) return null;
        $decoded_response = json_decode($response, true);
        if (isset($decoded_response['promptFeedback']['blockReason'])) {
            $reason = $decoded_response['promptFeedback']['blockReason'];
            throw new Exception("Request blocked by Gemini API for reason: " . $reason);
        }
        return $decoded_response['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }
}
?>