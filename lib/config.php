<?php
declare(strict_types=1);

function pastechi_load_env_file(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim(trim($value), "\"'");
        if ($key === '') {
            continue;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

function pastechi_load_env_once(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $root = dirname(__DIR__);
    pastechi_load_env_file($root . '/.env');
    pastechi_load_env_file($root . '/.env.local');
    $loaded = true;
}

function env_int(string $key, int $default): int
{
    $value = getenv($key);
    if (!is_string($value) || trim($value) === '') {
        return $default;
    }

    return (int) $value;
}

function env_string(string $key, string $default): string
{
    $value = getenv($key);
    if (!is_string($value) || trim($value) === '') {
        return $default;
    }

    return trim($value);
}

pastechi_load_env_once();

define('APP_NAME', env_string('APP_NAME', 'PasteChi'));
const APP_VERSION = '0.1.0';

const STORAGE_ROOT = __DIR__ . '/../storage';
const PASTES_DIR = STORAGE_ROOT . '/pastes';
const DISCUSSIONS_DIR = STORAGE_ROOT . '/discussions';
const RATELIMIT_DIR = STORAGE_ROOT . '/ratelimit';
const LOG_DIR = STORAGE_ROOT . '/logs';

define('MAX_PAYLOAD_BYTES', max(8 * 1024, env_int('MAX_PAYLOAD_BYTES', 1024 * 1024)));
const MAX_MESSAGE_BYTES = 16 * 1024;
const MAX_TTL_SECONDS = 60 * 60 * 24 * 7;
const MIN_KDF_ITERATIONS = 260000;
const MAX_KDF_ITERATIONS = 800000;
define('ATTACHMENT_MAX_BYTES', max(0, env_int('ATTACHMENT_MAX_BYTES', 5 * 1024 * 1024)));
define('ATTACHMENT_ALLOWED_EXTENSIONS', env_string('ATTACHMENT_ALLOWED_EXTENSIONS', '*'));
define(
    'MAX_CREATE_REQUEST_BYTES',
    max(
        MAX_PAYLOAD_BYTES * 2,
        env_int('MAX_CREATE_REQUEST_BYTES', (MAX_PAYLOAD_BYTES * 2) + (ATTACHMENT_MAX_BYTES * 3) + (512 * 1024))
    )
);
define('APP_ENFORCE_HTTPS', env_int('APP_ENFORCE_HTTPS', 1));

const RATE_WINDOWS = [
    'create' => ['seconds' => 60, 'max' => 12],
    'get' => ['seconds' => 60, 'max' => 80],
    'discussion_post' => ['seconds' => 60, 'max' => 40],
    'discussion_get' => ['seconds' => 60, 'max' => 120],
    'context' => ['seconds' => 60, 'max' => 120],
    'log' => ['seconds' => 60, 'max' => 80],
];

const MIN_DELAY_MS = 200;
const MAX_DELAY_MS = 550;

define('SERVER_PEPPER', env_string('SERVER_PEPPER', 'change-me-in-production-32-random-bytes'));
const FORENSIC_BUCKET_SECONDS = 3600;
const MAX_LOG_LINE_BYTES = 4096;
const API_REQUEST_TOKEN_TTL_SECONDS = 180;

function attachment_allowed_extensions_list(): array
{
    $raw = trim((string) ATTACHMENT_ALLOWED_EXTENSIONS);
    if ($raw === '' || $raw === '*') {
        return ['*'];
    }

    $parts = preg_split('/\s*,\s*/', strtolower($raw));
    if (!is_array($parts)) {
        return ['*'];
    }

    $normalized = [];
    foreach ($parts as $part) {
        $candidate = ltrim(trim((string) $part), '.');
        if ($candidate === '') {
            continue;
        }
        $normalized[] = $candidate;
    }

    $unique = array_values(array_unique($normalized));
    if ($unique === []) {
        return ['*'];
    }

    return $unique;
}

function is_attachment_extension_allowed(string $extension): bool
{
    $allowed = attachment_allowed_extensions_list();
    if ($allowed === ['*']) {
        return true;
    }

    $normalized = ltrim(strtolower(trim($extension)), '.');
    if ($normalized === '') {
        return false;
    }

    return in_array($normalized, $allowed, true);
}

function app_base_path(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = rtrim(dirname($scriptName), '/');
    if ($base === '' || $base === '.') {
        return '';
    }
    return $base;
}

function app_url(string $path = ''): string
{
    $base = app_base_path();
    $normalized = ltrim($path, '/');
    if ($normalized === '') {
        return $base !== '' ? ($base . '/') : '/';
    }
    return ($base !== '' ? $base : '') . '/' . $normalized;
}

function app_relative_url(string $path = ''): string
{
    $raw = trim($path);
    if ($raw === '') {
        return './';
    }

    if (str_starts_with($raw, '?') || str_starts_with($raw, '#')) {
        return $raw;
    }

    return ltrim($raw, '/');
}

function is_cli_runtime(): bool
{
    return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}

function request_host(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
    $host = strtolower(trim($host));
    if ($host === '') {
        return '';
    }

    if (str_contains($host, ':')) {
        $parts = explode(':', $host, 2);
        $host = $parts[0];
    }

    return $host;
}

function is_local_host(string $host): bool
{
    if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
        return true;
    }

    return str_ends_with($host, '.localhost');
}

function request_is_secure(): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off' && $https !== '0') {
        return true;
    }

    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }

    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if (str_contains($forwardedProto, 'https')) {
        return true;
    }

    $forwardedSsl = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
    if ($forwardedSsl === 'on') {
        return true;
    }

    return false;
}

function enforce_https_if_needed(): void
{
    if (is_cli_runtime() || (int) APP_ENFORCE_HTTPS !== 1) {
        return;
    }

    $host = request_host();
    if ($host === '' || is_local_host($host) || request_is_secure()) {
        return;
    }

    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    if ($uri === '') {
        $uri = '/';
    }

    header('Location: https://' . $host . $uri, true, 307);
    exit;
}

enforce_https_if_needed();
