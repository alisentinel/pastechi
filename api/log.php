<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

enforce_rate_limit('log');

$input = read_json_input();
$level = (string) ($input['level'] ?? 'info');
$message = (string) ($input['message'] ?? '');
$context = $input['context'] ?? [];

if ($message === '') {
    json_response(['ok' => false, 'error' => 'message_required'], 400);
}

if (!is_array($context)) {
    $context = [];
}

app_log($level, 'client:' . $message, $context);
json_response(['ok' => true]);
