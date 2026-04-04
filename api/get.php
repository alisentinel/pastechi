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

$codeHash = code_hash($code);
$timestamp = now();

try {
    $pdo = get_db();
    $pdo->beginTransaction();

    $select = $pdo->prepare('SELECT * FROM pastes WHERE codeHash = :codeHash FOR UPDATE');
    $select->execute([':codeHash' => $codeHash]);
    $record = $select->fetch(PDO::FETCH_ASSOC);

    if (!is_array($record)) {
        $pdo->commit();
        app_log('info', 'get_unavailable', ['codeHash' => $codeHash]);
        json_response(['ok' => false, 'error' => 'unavailable'], 404);
    }

    $expireAt = (int) ($record['expireAt'] ?? 0);
    $maxViews = (int) ($record['maxViews'] ?? 0);
    $views = (int) ($record['views'] ?? 0);
    $uniqueViewsOnly = (bool) ($record['uniqueViewsOnly'] ?? false);

    if (($expireAt > 0 && $timestamp >= $expireAt) || ($maxViews > 0 && $views >= $maxViews)) {
        $delete = $pdo->prepare('DELETE FROM pastes WHERE codeHash = :codeHash');
        $delete->execute([':codeHash' => $codeHash]);
        $pdo->commit();
        app_log('info', 'get_expired_or_consumed', ['codeHash' => $codeHash]);
        json_response(['ok' => false, 'error' => 'unavailable'], 404);
    }

    $viewerCookieKey = 'pastechi_uv_' . $code;
    $viewerAlreadyCounted = $uniqueViewsOnly && (($_COOKIE[$viewerCookieKey] ?? '') === '1');
    $countThisView = !$uniqueViewsOnly || !$viewerAlreadyCounted;

    $forensicsBuckets = [];
    $forensicsEnabled = (bool) ($record['modes_forensics'] ?? false);
    if ($forensicsEnabled) {
        $decoded = json_decode((string) ($record['forensics_buckets'] ?? '{}'), true);
        if (is_array($decoded)) {
            $forensicsBuckets = $decoded;
        }
    }

    if ($countThisView) {
        $views += 1;
        if ($forensicsEnabled) {
            $bucket = (string) forensic_bucket($timestamp);
            $forensicsBuckets[$bucket] = ((int) ($forensicsBuckets[$bucket] ?? 0)) + 1;
        }

        $update = $pdo->prepare('UPDATE pastes SET views = :views, forensics_buckets = :forensics_buckets WHERE codeHash = :codeHash');
        $update->execute([
            ':views' => $views,
            ':forensics_buckets' => json_encode($forensicsBuckets, JSON_UNESCAPED_SLASHES),
            ':codeHash' => $codeHash,
        ]);
    }

    if ($uniqueViewsOnly && !$viewerAlreadyCounted) {
        setcookie($viewerCookieKey, '1', [
            'expires' => $timestamp + (60 * 60 * 24 * 365),
            'path' => app_base_path() !== '' ? app_base_path() : '/',
            'secure' => !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    $response = [
        'ok' => true,
        'code' => $code,
        'envelope' => [
            'ciphertext' => (string) ($record['ciphertext'] ?? ''),
            'iv' => (string) ($record['iv'] ?? ''),
            'salt' => (string) ($record['salt'] ?? ''),
            'kdfIterations' => (int) ($record['kdfIterations'] ?? MIN_KDF_ITERATIONS),
            'alg' => 'AES-GCM',
        ],
        'lockUntil' => (int) ($record['lockUntil'] ?? 0),
        'binding' => [
            'type' => (string) ($record['binding_type'] ?? 'none'),
        ],
        'modes' => [
            'discussion' => (bool) ($record['modes_discussion'] ?? false),
            'forensics' => $forensicsEnabled,
        ],
        'discussion' => [
            'salt' => (string) ($record['discussion_salt'] ?? ''),
        ],
        'access' => [
            'requiresFragment' => (bool) ($record['requires_fragment'] ?? false),
            'passwordProtected' => (bool) ($record['password_protected'] ?? true),
        ],
        'views' => $views,
        'maxViews' => $maxViews,
        'uniqueViewsOnly' => $uniqueViewsOnly,
        'expireAt' => $expireAt,
    ];

    if ($forensicsEnabled) {
        $response['forensics'] = [
            'views' => $views,
            'buckets' => $forensicsBuckets,
        ];
    }

    $shouldDelete = (bool) ($record['burnAfterRead'] ?? false);
    if (!$shouldDelete && $maxViews > 0 && $views >= $maxViews) {
        $shouldDelete = true;
    }

    if ($shouldDelete) {
        $delete = $pdo->prepare('DELETE FROM pastes WHERE codeHash = :codeHash');
        $delete->execute([':codeHash' => $codeHash]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log('error', 'get_storage_read_failed', ['codeHash' => $codeHash, 'error' => $e->getMessage()]);
    json_response(['ok' => false, 'error' => 'unavailable'], 404);
}

app_log('info', 'paste_served', ['codeHash' => $codeHash, 'views' => $views]);

json_response($response);
