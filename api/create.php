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

try {
    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT INTO pastes (
        code,
        ciphertext,
        iv,
        salt,
        kdfIterations,
        createdAt,
        expireAt,
        views,
        maxViews,
        burnAfterRead,
        lockUntil,
        binding_type,
        binding_hash,
        modes_discussion,
        modes_forensics,
        discussion_salt,
        requires_fragment,
        password_protected,
        forensics_buckets
    ) VALUES (
        :code,
        :ciphertext,
        :iv,
        :salt,
        :kdfIterations,
        :createdAt,
        :expireAt,
        :views,
        :maxViews,
        :burnAfterRead,
        :lockUntil,
        :binding_type,
        :binding_hash,
        :modes_discussion,
        :modes_forensics,
        :discussion_salt,
        :requires_fragment,
        :password_protected,
        CAST(:forensics_buckets AS JSON)
    )');

    $stmt->execute([
        ':code' => $code,
        ':ciphertext' => $ciphertext,
        ':iv' => $iv,
        ':salt' => $salt,
        ':kdfIterations' => $kdfIterations,
        ':createdAt' => now(),
        ':expireAt' => $expireAt,
        ':views' => 0,
        ':maxViews' => $maxViews,
        ':burnAfterRead' => $burnAfterRead ? 1 : 0,
        ':lockUntil' => $lockUntil,
        ':binding_type' => $type,
        ':binding_hash' => '',
        ':modes_discussion' => $discussionEnabled ? 1 : 0,
        ':modes_forensics' => $forensicsEnabled ? 1 : 0,
        ':discussion_salt' => $discussionSalt,
        ':requires_fragment' => $requiresFragment ? 1 : 0,
        ':password_protected' => $passwordProtected ? 1 : 0,
        ':forensics_buckets' => '{}',
    ]);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        app_log('info', 'create_code_collision', ['code' => $code]);
        random_delay();
        json_response(['ok' => false, 'error' => 'code_unavailable'], 409);
    }

    app_log('error', 'create_storage_write_failed', ['code' => $code, 'error' => $e->getMessage()]);
    json_response(['ok' => false, 'error' => 'storage_write_failed'], 500);
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
    'path' => preg_replace('#/api$#', '', app_base_path()) . '/' . $code,
]);
