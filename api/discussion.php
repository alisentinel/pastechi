<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
    enforce_rate_limit('discussion_post');
} else {
    enforce_rate_limit('discussion_get');
}
random_delay();

$code = (string) ($_GET['code'] ?? ($_POST['code'] ?? ''));
if (!verify_code($code)) {
    app_log('warn', 'discussion_invalid_code');
    json_response(['ok' => false, 'error' => 'invalid_code'], 400);
}

$paste = read_json_file(paste_path($code));
if (!$paste || (($paste['modes']['discussion'] ?? false) !== true)) {
    app_log('info', 'discussion_unavailable', ['code' => $code]);
    json_response(['ok' => false, 'error' => 'discussion_unavailable'], 404);
}

if ($method === 'GET') {
    $since = max(0, (int) ($_GET['since'] ?? 0));
    $discussion = read_json_file(discussion_path($code)) ?? ['messages' => []];
    $all = $discussion['messages'] ?? [];
    $messages = array_values(array_filter($all, static fn ($item) => ((int) ($item['id'] ?? 0)) > $since));

    json_response([
        'ok' => true,
        'messages' => $messages,
    ]);
}

if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$input = read_json_input();
$envelope = $input['envelope'] ?? null;
if (!is_array($envelope)) {
    json_response(['ok' => false, 'error' => 'missing_envelope'], 400);
}

$ciphertext = (string) ($envelope['ciphertext'] ?? '');
$iv = (string) ($envelope['iv'] ?? '');
if ($ciphertext === '' || $iv === '' || strlen($ciphertext) > MAX_MESSAGE_BYTES * 2 || strlen($iv) > 128) {
    json_response(['ok' => false, 'error' => 'invalid_message'], 400);
}

$path = discussion_path($code);
$discussion = read_json_file($path) ?? ['messages' => []];
$messages = $discussion['messages'] ?? [];
$lastId = (int) (($messages[count($messages) - 1]['id'] ?? 0));
$newId = $lastId + 1;

$messages[] = [
    'id' => $newId,
    'ts' => now(),
    'ciphertext' => $ciphertext,
    'iv' => $iv,
];

if (count($messages) > 200) {
    $messages = array_slice($messages, -200);
}

$discussion['messages'] = $messages;
if (!atomic_write_json($path, $discussion)) {
    app_log('error', 'discussion_storage_write_failed', ['code' => $code]);
    json_response(['ok' => false, 'error' => 'storage_write_failed'], 500);
}

app_log('info', 'discussion_message_posted', ['code' => $code, 'id' => $newId]);

json_response(['ok' => true, 'id' => $newId]);
