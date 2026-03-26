<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

enforce_rate_limit('get');
random_delay();

$code = (string) ($_GET['code'] ?? '');
if (!verify_code($code)) {
    app_log('warn', 'get_invalid_code');
    json_response(['ok' => false, 'error' => 'unavailable'], 404);
}

$path = paste_path($code);
$record = read_json_file($path);
if (!$record) {
    app_log('info', 'get_unavailable', ['code' => $code]);
    json_response(['ok' => false, 'error' => 'unavailable'], 404);
}

$timestamp = now();
$expireAt = (int) ($record['expireAt'] ?? 0);
$maxViews = (int) ($record['maxViews'] ?? 0);
$views = (int) ($record['views'] ?? 0);

if (($expireAt > 0 && $timestamp >= $expireAt) || ($maxViews > 0 && $views >= $maxViews)) {
    @unlink($path);
    @unlink(discussion_path($code));
    app_log('info', 'get_expired_or_consumed', ['code' => $code]);
    json_response(['ok' => false, 'error' => 'unavailable'], 404);
}

$views += 1;
$record['views'] = $views;
if (($record['modes']['forensics'] ?? false) === true) {
    $bucket = (string) forensic_bucket($timestamp);
    $record['forensics']['buckets'][$bucket] = ((int) ($record['forensics']['buckets'][$bucket] ?? 0)) + 1;
}
atomic_write_json($path, $record);

$response = [
    'ok' => true,
    'code' => $code,
    'envelope' => [
        'ciphertext' => (string) ($record['ciphertext'] ?? ''),
        'iv' => (string) ($record['iv'] ?? ''),
        'salt' => (string) ($record['salt'] ?? ''),
        'kdfIterations' => (int) ($record['kdfIterations'] ?? MIN_KDF_ITERATIONS),
        'alg' => (string) ($record['alg'] ?? 'AES-GCM'),
    ],
    'lockUntil' => (int) ($record['lockUntil'] ?? 0),
    'binding' => [
        'type' => (string) ($record['binding']['type'] ?? 'none'),
    ],
    'modes' => [
        'discussion' => (bool) ($record['modes']['discussion'] ?? false),
        'forensics' => (bool) ($record['modes']['forensics'] ?? false),
    ],
    'discussion' => [
        'salt' => (string) ($record['discussion']['salt'] ?? ''),
    ],
    'access' => [
        'requiresFragment' => (bool) ($record['access']['requiresFragment'] ?? false),
        'passwordProtected' => (bool) ($record['access']['passwordProtected'] ?? true),
    ],
    'views' => $views,
    'maxViews' => $maxViews,
    'expireAt' => $expireAt,
];

if (($record['modes']['forensics'] ?? false) === true) {
    $response['forensics'] = [
        'views' => $views,
        'buckets' => $record['forensics']['buckets'] ?? [],
    ];
}

$shouldDelete = (bool) ($record['burnAfterRead'] ?? false);
if (!$shouldDelete && $maxViews > 0 && $views >= $maxViews) {
    $shouldDelete = true;
}

if ($shouldDelete) {
    @unlink($path);
    @unlink(discussion_path($code));
}

app_log('info', 'paste_served', ['code' => $code, 'views' => $views]);

json_response($response);
