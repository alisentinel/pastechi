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

function read_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    if (strlen($raw) > MAX_PAYLOAD_BYTES * 2) {
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
    foreach ([PASTES_DIR, DISCUSSIONS_DIR, RATELIMIT_DIR, LOG_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
    }
}

function redact_context(array $context): array
{
    $sensitive = ['password', 'plaintext', 'content', 'ciphertext', 'urlSecret', 'secret', 'token', 'key', 'hash'];
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

function app_log(string $level, string $message, array $context = []): void
{
    ensure_storage();
    $record = [
        'ts' => now(),
        'level' => strtolower($level),
        'message' => substr($message, 0, 200),
        'path' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
        'context' => redact_context($context),
    ];

    $line = json_encode($record, JSON_UNESCAPED_SLASHES);
    if (!is_string($line)) {
        return;
    }

    if (strlen($line) > MAX_LOG_LINE_BYTES) {
        $record['context'] = ['notice' => 'context_truncated'];
        $line = json_encode($record, JSON_UNESCAPED_SLASHES);
        if (!is_string($line)) {
            return;
        }
    }

    @file_put_contents(LOG_DIR . '/app.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
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

function safe_name(string $value): string
{
    return preg_replace('/[^a-zA-Z0-9._-]/', '_', $value);
}

function paste_path(string $code): string
{
    return PASTES_DIR . '/' . safe_name($code) . '.json';
}

function discussion_path(string $code): string
{
    return DISCUSSIONS_DIR . '/' . safe_name($code) . '.json';
}

function atomic_write_json(string $path, array $data): bool
{
    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $result = file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
    if ($result === false) {
        return false;
    }

    return rename($tmp, $path);
}

function read_json_file(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function now(): int
{
    return time();
}

function enforce_rate_limit(string $key): void
{
    ensure_storage();
    if (!isset(RATE_WINDOWS[$key])) {
        return;
    }

    $window = RATE_WINDOWS[$key];
    $path = RATELIMIT_DIR . '/' . $key . '.json';
    $record = read_json_file($path) ?? ['start' => now(), 'count' => 0];

    if ((now() - (int) $record['start']) > (int) $window['seconds']) {
        $record = ['start' => now(), 'count' => 0];
    }

    $record['count'] = ((int) $record['count']) + 1;
    atomic_write_json($path, $record);

    if ((int) $record['count'] > (int) $window['max']) {
        random_delay();
        json_response(['ok' => false, 'error' => 'rate_limited'], 429);
    }
}

function cleanup_expired_files(): void
{
    ensure_storage();
    $timestamp = now();

    foreach (glob(PASTES_DIR . '/*.json') ?: [] as $pasteFile) {
        $data = read_json_file($pasteFile);
        if (!$data) {
            @unlink($pasteFile);
            continue;
        }

        $expireAt = (int) ($data['expireAt'] ?? 0);
        $maxViews = (int) ($data['maxViews'] ?? 0);
        $views = (int) ($data['views'] ?? 0);

        if (($expireAt > 0 && $timestamp >= $expireAt) || ($maxViews > 0 && $views >= $maxViews)) {
            @unlink($pasteFile);
            $code = (string) ($data['code'] ?? '');
            if ($code !== '') {
                @unlink(discussion_path($code));
            }
        }
    }

    foreach (glob(RATELIMIT_DIR . '/*.json') ?: [] as $rateFile) {
        $data = read_json_file($rateFile);
        if (!$data) {
            @unlink($rateFile);
            continue;
        }
        if ((now() - (int) ($data['start'] ?? 0)) > (60 * 10)) {
            @unlink($rateFile);
        }
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
