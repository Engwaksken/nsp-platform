<?php

// Router for `php -S`: a minimal stand-in for the Messages API used by AnthropicClientTest.
$body = (string) file_get_contents('php://input');
$headers = array_change_key_case(getallheaders(), CASE_LOWER);
file_put_contents(sys_get_temp_dir().'/nsp-anthropic-mock-'.$_SERVER['SERVER_PORT'].'.json', json_encode([
    'path' => $_SERVER['REQUEST_URI'], 'beta' => $headers['anthropic-beta'] ?? null, 'body' => json_decode($body, true),
]));
header('Content-Type: application/json');
if (str_contains($body, 'TRIGGER_429')) {
    http_response_code(429);
    echo json_encode(['type' => 'error', 'error' => ['type' => 'rate_limit_error', 'message' => 'slow down']]);

    return;
}
$refuse = str_contains($body, 'TRIGGER_REFUSAL');
echo json_encode([
    'id' => 'msg_test', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
    'content' => $refuse ? [] : [['type' => 'text', 'text' => 'A clearer description.']],
    'stop_reason' => $refuse ? 'refusal' : 'end_turn', 'stop_sequence' => null,
    'usage' => ['input_tokens' => 100, 'output_tokens' => $refuse ? 0 : 12],
]);
