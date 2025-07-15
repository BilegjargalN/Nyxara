<?php
if (!defined('NYXARA_ROOT')) {
    define('NYXARA_ROOT', true);
}

if (!function_exists('loadEnv')) {
    require_once __DIR__ . '/env_loader.php';
}

$DEEPSEEK_API_KEY = getenv('DEEPSEEK_API_KEY');
$OPENAI_API_KEY = getenv('OPENAI_API_KEY');
$GEMINI_API_KEY = getenv('GEMINI_API_KEY');

$DEEPSEEK_API_URL = 'https://api.deepseek.com/chat/completions';
$OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
$GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';

?>
