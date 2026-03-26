<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

enforce_rate_limit('create');
$input = read_json_input();

$code = (string) ($input['code'] ?? '');
if (!verify_code($code)) {
    app_log('warn', 'create_invalid_code');
    random_delay();
    json_response(['ok' => false, 'error' => 'invalid_code'], 400);
}

$envelope = $input['envelope'] ?? null;
if (!is_array($envelope)) {
    json_response(['ok' => false, 'error' => 'missing_envelope'], 400);
}

$ciphertext = (string) ($envelope['ciphertext'] ?? '');
$iv = (string) ($envelope['iv'] ?? '');
$salt = (string) ($envelope['salt'] ?? '');
$kdfIterations = (int) ($envelope['kdfIterations'] ?? 0);

if ($ciphertext === '' || $iv === '' || $salt === '') {
    json_response(['ok' => false, 'error' => 'invalid_envelope'], 400);
}
if (strlen($ciphertext) > MAX_PAYLOAD_BYTES * 2 || strlen($iv) > 128 || strlen($salt) > 128) {
    json_response(['ok' => false, 'error' => 'payload_too_large'], 413);
}
if ($kdfIterations < MIN_KDF_ITERATIONS || $kdfIterations > MAX_KDF_ITERATIONS) {
    json_response(['ok' => false, 'error' => 'invalid_kdf_iterations'], 400);
}

$ttlSeconds = normalize_ttl(isset($input['ttlSeconds']) ? (int) $input['ttlSeconds'] : 0);
$maxViews = max(0, (int) ($input['maxViews'] ?? 0));
$burnAfterRead = (bool) ($input['burnAfterRead'] ?? false);
if ($burnAfterRead) {
    $maxViews = 1;
}

$lockUntil = max(0, (int) ($input['lockUntil'] ?? 0));
$expireAt = $ttlSeconds > 0 ? (now() + $ttlSeconds) : 0;

$bindingInput = $input['binding'] ?? ['type' => 'none', 'hash' => ''];
$type = (string) ($bindingInput['type'] ?? 'none');
$bindingHash = (string) ($bindingInput['hash'] ?? '');
if (!in_array($type, ['none', 'ip', 'fingerprint'], true)) {
    $type = 'none';
    $bindingHash = '';
}
if ($type !== 'none' && !preg_match('/^[a-f0-9]{64}$/', $bindingHash)) {
    json_response(['ok' => false, 'error' => 'invalid_binding'], 400);
}

$modesInput = $input['modes'] ?? [];
$discussionEnabled = (bool) ($modesInput['discussion'] ?? false);
$forensicsEnabled = (bool) ($modesInput['forensics'] ?? false);
$accessInput = $input['access'] ?? [];
$requiresFragment = (bool) ($accessInput['requiresFragment'] ?? true);
$passwordProtected = (bool) ($accessInput['passwordProtected'] ?? false);
$discussionSalt = (string) ($input['discussionSalt'] ?? '');
if ($discussionEnabled && $discussionSalt === '') {
    json_response(['ok' => false, 'error' => 'missing_discussion_salt'], 400);
}
if ($discussionEnabled && strlen($discussionSalt) > 128) {
    json_response(['ok' => false, 'error' => 'invalid_discussion_salt'], 400);
}

$path = paste_path($code);
if (is_file($path)) {
    app_log('info', 'create_code_collision', ['code' => $code]);
    random_delay();
    json_response(['ok' => false, 'error' => 'code_unavailable'], 409);
}

$record = [
    'version' => 1,
    'code' => $code,
    'createdAt' => now(),
    'expireAt' => $expireAt,
    'maxViews' => $maxViews,
    'burnAfterRead' => $burnAfterRead,
    'views' => 0,
    'ciphertext' => $ciphertext,
    'iv' => $iv,
    'salt' => $salt,
    'kdfIterations' => $kdfIterations,
    'alg' => 'AES-GCM',
    'lockUntil' => $lockUntil,
    'binding' => [
        'type' => $type,
        'hash' => '',
    ],
    'modes' => [
        'discussion' => $discussionEnabled,
        'forensics' => $forensicsEnabled,
    ],
    'discussion' => [
        'salt' => $discussionSalt,
    ],
    'access' => [
        'requiresFragment' => $requiresFragment,
        'passwordProtected' => $passwordProtected,
    ],
    'forensics' => [
        'buckets' => [],
    ],
];

if (!atomic_write_json($path, $record)) {
    app_log('error', 'create_storage_write_failed', ['code' => $code]);
    json_response(['ok' => false, 'error' => 'storage_write_failed'], 500);
}

if ($discussionEnabled) {
    atomic_write_json(discussion_path($code), ['messages' => []]);
}

app_log('info', 'paste_created', [
    'code' => $code,
    'ttlSeconds' => $ttlSeconds,
    'maxViews' => $maxViews,
    'burnAfterRead' => $burnAfterRead,
    'discussion' => $discussionEnabled,
    'forensics' => $forensicsEnabled,
]);

json_response([
    'ok' => true,
    'code' => $code,
    'path' => '/' . $code,
]);
