<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Load local database config if available, otherwise use default
if (is_file(__DIR__ . '/db-config.local.php')) {
    require_once __DIR__ . '/db-config.local.php';
} else {
    require_once __DIR__ . '/db-config.php';
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json_input(?int $maxBytes = null): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $limit = $maxBytes ?? (MAX_PAYLOAD_BYTES * 2);
    if (strlen($raw) > $limit) {
        json_response(['ok' => false, 'error' => 'payload_too_large'], 413);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        json_response(['ok' => false, 'error' => 'invalid_json'], 400);
    }

    return $decoded;
}

function ensure_storage(): void
{
    // Storage directories are no longer required for runtime persistence.
}

function redact_context(array $context): array
{
    $sensitive = [
        'password',
        'plaintext',
        'content',
        'ciphertext',
        'urlSecret',
        'secret',
        'token',
        'key',
        'hash',
        'code',
        'ip',
        'remote',
        'forwarded',
        'address',
    ];
    $clean = [];
    foreach ($context as $key => $value) {
        $name = strtolower((string) $key);
        $isSensitive = false;
        foreach ($sensitive as $marker) {
            if (str_contains($name, $marker)) {
                $isSensitive = true;
                break;
            }
        }

        if ($isSensitive) {
            $clean[$key] = '[redacted]';
            continue;
        }

        if (is_scalar($value) || $value === null) {
            $clean[$key] = $value;
            continue;
        }

        $clean[$key] = '[complex]';
    }

    return $clean;
}

function sanitize_log_path(string $rawPath): string
{
    $path = parse_url($rawPath, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = '/';
    }

    // Replace 6-digit code segments with a stable hash prefix before persisting logs.
    return preg_replace_callback(
        '#(?<=/)([0-9]{6})(?=/|$)#',
        static function (array $matches): string {
            $code = (string) ($matches[1] ?? '');
            if (!verify_code($code)) {
                return 'code';
            }
            return 'code_' . substr(code_hash($code), 0, 12);
        },
        $path
    ) ?? '/';
}

function app_log(string $level, string $message, array $context = []): void
{
    $record = [
        'ts' => now(),
        'level' => strtolower($level),
        'message' => substr($message, 0, 200),
        'path' => sanitize_log_path((string) ($_SERVER['REQUEST_URI'] ?? '')),
        'context' => redact_context($context),
    ];

    $contextJson = json_encode($record['context'], JSON_UNESCAPED_SLASHES);
    if (!is_string($contextJson)) {
        $contextJson = '{}';
    }
    if (strlen($contextJson) > MAX_LOG_LINE_BYTES) {
        $contextJson = '{"notice":"context_truncated"}';
    }

    try {
        ensure_database_schema();
        $pdo = get_db();
        $stmt = $pdo->prepare('INSERT INTO logs (ts, level, message, path, context_json) VALUES (:ts, :level, :message, :path, :context_json)');
        $stmt->execute([
            ':ts' => (int) $record['ts'],
            ':level' => (string) $record['level'],
            ':message' => (string) $record['message'],
            ':path' => (string) $record['path'],
            ':context_json' => $contextJson,
        ]);
    } catch (Throwable $e) {
        // Avoid recursive logging failures.
    }
}

function random_delay(): void
{
    usleep(random_int(MIN_DELAY_MS, MAX_DELAY_MS) * 1000);
}

function client_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || $candidate === '') {
            continue;
        }
        if (str_contains($candidate, ',')) {
            $candidate = trim(explode(',', $candidate)[0]);
        }
        return $candidate;
    }

    return '0.0.0.0';
}

function ip_hash(): string
{
    $ip = client_ip();
    $ipPrefix = $ip;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        $ipPrefix = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0';
    }

    return hash('sha256', SERVER_PEPPER . '|' . $ipPrefix);
}

function verify_code(string $code): bool
{
    return (bool) preg_match('/^[0-9]{6}$/', $code);
}

function is_base64url_string(string $value, int $maxLength = 0): bool
{
    if ($value === '') {
        return false;
    }

    if (!preg_match('/^[A-Za-z0-9\-_]+$/', $value)) {
        return false;
    }

    if ($maxLength > 0 && strlen($value) > $maxLength) {
        return false;
    }

    return true;
}

function verify_envelope_fields(string $ciphertext, string $iv, string $salt, int $ciphertextMaxBytes): bool
{
    if (!is_base64url_string($ciphertext)) {
        return false;
    }

    // AES-GCM IV is 96-bit (12 bytes) => 16 base64url chars without padding.
    if (!preg_match('/^[A-Za-z0-9\-_]{16}$/', $iv)) {
        return false;
    }

    // PBKDF2 salt is 16 bytes in this app => 22 base64url chars without padding.
    if (!preg_match('/^[A-Za-z0-9\-_]{22}$/', $salt)) {
        return false;
    }

    return strlen($ciphertext) <= $ciphertextMaxBytes;
}

function base64url_encode(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function base64url_decode(string $encoded): string|false
{
    $padded = strtr($encoded, '-_', '+/');
    $padding = strlen($padded) % 4;
    if ($padding > 0) {
        $padded .= str_repeat('=', 4 - $padding);
    }
    return base64_decode($padded, true);
}

function issue_api_request_token(string $scope, int $ttlSeconds = API_REQUEST_TOKEN_TTL_SECONDS): string
{
    $issuedAt = now();
    $nonce = bin2hex(random_bytes(16));
    $payload = $scope . '|' . $issuedAt . '|' . ip_hash() . '|' . $nonce;
    $signature = hash_hmac('sha256', $payload, (string) SERVER_PEPPER);
    $token = json_encode([
        's' => $scope,
        'iat' => $issuedAt,
        'ttl' => max(10, $ttlSeconds),
        'n' => $nonce,
        'sig' => $signature,
    ], JSON_UNESCAPED_SLASHES);
    if (!is_string($token)) {
        return '';
    }

    return base64url_encode($token);
}

function verify_api_request_token(string $token, string $expectedScope): bool
{
    if (!is_base64url_string($token, 2048)) {
        return false;
    }

    $decoded = base64url_decode($token);
    if (!is_string($decoded) || $decoded === '') {
        return false;
    }

    $data = json_decode($decoded, true);
    if (!is_array($data)) {
        return false;
    }

    $scope = (string) ($data['s'] ?? '');
    $issuedAt = (int) ($data['iat'] ?? 0);
    $ttl = (int) ($data['ttl'] ?? 0);
    $nonce = (string) ($data['n'] ?? '');
    $sig = (string) ($data['sig'] ?? '');

    if ($scope !== $expectedScope || $scope === '' || $issuedAt <= 0 || $ttl <= 0 || $nonce === '' || $sig === '') {
        return false;
    }

    $maxTtl = max(30, API_REQUEST_TOKEN_TTL_SECONDS * 2);
    if ($ttl > $maxTtl) {
        return false;
    }

    $age = now() - $issuedAt;
    if ($age < -10 || $age > $ttl) {
        return false;
    }

    $payload = $scope . '|' . $issuedAt . '|' . ip_hash() . '|' . $nonce;
    $expectedSig = hash_hmac('sha256', $payload, (string) SERVER_PEPPER);

    return hash_equals($expectedSig, $sig);
}

function code_hash(string $code): string
{
    return hash('sha256', SERVER_PEPPER . '|code|' . $code);
}

function now(): int
{
    return time();
}

function enforce_rate_limit(string $key): void
{
    if (!isset(RATE_WINDOWS[$key])) {
        return;
    }

    $window = RATE_WINDOWS[$key];
    $timestamp = now();
    $count = 0;

    try {
        ensure_database_schema();
        $pdo = get_db();
        $pdo->beginTransaction();

        $select = $pdo->prepare('SELECT window_start, count FROM rate_limits WHERE `key` = :key FOR UPDATE');
        $select->execute([':key' => $key]);
        $row = $select->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            $count = 1;
            $insert = $pdo->prepare('INSERT INTO rate_limits (`key`, window_start, count) VALUES (:key, :window_start, :count)');
            $insert->execute([
                ':key' => $key,
                ':window_start' => $timestamp,
                ':count' => $count,
            ]);
        } else {
            $windowStart = (int) ($row['window_start'] ?? $timestamp);
            $existingCount = (int) ($row['count'] ?? 0);

            if (($timestamp - $windowStart) > (int) $window['seconds']) {
                $windowStart = $timestamp;
                $existingCount = 0;
            }

            $count = $existingCount + 1;
            $update = $pdo->prepare('UPDATE rate_limits SET window_start = :window_start, count = :count WHERE `key` = :key');
            $update->execute([
                ':window_start' => $windowStart,
                ':count' => $count,
                ':key' => $key,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return;
    }

    if ($count > (int) $window['max']) {
        random_delay();
        json_response(['ok' => false, 'error' => 'rate_limited'], 429);
    }
}

function cleanup_expired_files(): void
{
    $timestamp = now();

    try {
        ensure_database_schema();
        $pdo = get_db();
        $deletePastes = $pdo->prepare('DELETE FROM pastes WHERE (expireAt > 0 AND expireAt <= :now_ts) OR (maxViews > 0 AND views >= maxViews)');
        $deletePastes->execute([':now_ts' => $timestamp]);

        $deleteRates = $pdo->prepare('DELETE FROM rate_limits WHERE window_start < :threshold');
        $deleteRates->execute([':threshold' => $timestamp - (60 * 10)]);
    } catch (Throwable $e) {
        app_log('warn', 'cleanup_sql_failed', ['message' => $e->getMessage()]);
    }
}

function normalize_ttl(?int $ttl): int
{
    if ($ttl === null || $ttl <= 0) {
        return 0;
    }

    return min($ttl, MAX_TTL_SECONDS);
}

function forensic_bucket(int $timestamp): int
{
    return (int) (floor($timestamp / FORENSIC_BUCKET_SECONDS) * FORENSIC_BUCKET_SECONDS);
}
