<?php

// Router for `php -S`: a minimal stand-in for the Messages API used by AnthropicClientTest.
$body = (string) file_get_contents('php://input');
$headers = array_change_key_case(getallheaders(), CASE_LOWER);
file_put_contents(sys_get_temp_dir().'/nsp-anthropic-mock-'.$_SERVER['SERVER_PORT'].'.json', json_encode([
    'path' => $_SERVER['REQUEST_URI'], 'beta' => $headers['anthropic-beta'] ?? null, 'body' => json_decode($body, true),
]));
header('Content-Type: application/json');
// GET /v1/models/{id}: used by the connection check. "bad-key" is rejected, "missing-model" does not exist.
if (preg_match('#^/v1/models/([^/?]+)#', $_SERVER['REQUEST_URI'], $match)) {
    if (in_array('bad-key', [$headers['x-api-key'] ?? '', substr($headers['authorization'] ?? '', 7)], true)) {
        http_response_code(401);
        echo json_encode(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']]);
    } elseif ($match[1] === 'missing-model') {
        http_response_code(404);
        echo json_encode(['type' => 'error', 'error' => ['type' => 'not_found_error', 'message' => 'model not found']]);
    } else {
        echo json_encode(['type' => 'model', 'id' => $match[1], 'display_name' => 'Test model', 'created_at' => '2026-01-01T00:00:00Z',
            'deprecated_at' => null, 'lifecycle' => 'active', 'line' => null, 'capabilities' => null, 'max_input_tokens' => 1000000, 'max_tokens' => 128000]);
    }

    return;
}
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
