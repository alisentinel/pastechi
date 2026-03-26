<?php
declare(strict_types=1);

const APP_NAME = 'PasteChi';
const APP_VERSION = '0.1.0';

const STORAGE_ROOT = __DIR__ . '/../storage';
const PASTES_DIR = STORAGE_ROOT . '/pastes';
const DISCUSSIONS_DIR = STORAGE_ROOT . '/discussions';
const RATELIMIT_DIR = STORAGE_ROOT . '/ratelimit';
const LOG_DIR = STORAGE_ROOT . '/logs';

const MAX_PAYLOAD_BYTES = 1024 * 1024;
const MAX_MESSAGE_BYTES = 16 * 1024;
const MAX_TTL_SECONDS = 60 * 60 * 24 * 7;
const MIN_KDF_ITERATIONS = 120000;
const MAX_KDF_ITERATIONS = 800000;

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

const SERVER_PEPPER = 'change-me-in-production-32-random-bytes';
const FORENSIC_BUCKET_SECONDS = 3600;
const MAX_LOG_LINE_BYTES = 4096;

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
