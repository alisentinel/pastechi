<?php

declare(strict_types=1);

require_once __DIR__ . '/db-config.php';
auto_migrate_if_possible();

const SUPPORTED_LANGS = ['en', 'fa'];
const LANG_COOKIE_KEY = 'pastechi_lang_v2';

function current_lang(): string
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }

    $requested = strtolower((string) ($_GET['lang'] ?? ''));
    if (in_array($requested, SUPPORTED_LANGS, true)) {
        $lang = $requested;
        setcookie(LANG_COOKIE_KEY, $lang, [
            'expires' => time() + (60 * 60 * 24 * 180),
            'path' => app_base_path() !== '' ? app_base_path() . '/' : '/',
            'samesite' => 'Lax',
        ]);
        return $lang;
    }

    $cookie = strtolower((string) ($_COOKIE[LANG_COOKIE_KEY] ?? ''));
    if (in_array($cookie, SUPPORTED_LANGS, true)) {
        $lang = $cookie;
        return $lang;
    }

    return 'fa';
}

function lang_dir(): string
{
    return current_lang() === 'fa' ? 'rtl' : 'ltr';
}

function is_rtl_lang(): bool
{
    return current_lang() === 'fa';
}

function i18n_messages(): array
{
    static $messages = null;
    if ($messages !== null) {
        return $messages;
    }

    $basePath = __DIR__ . '/../lang/';
    $en = require $basePath . 'en.php';
    $active = current_lang() === 'en' ? $en : array_merge($en, require $basePath . current_lang() . '.php');
    $messages = $active;

    return $messages;
}

function t(string $key, array $replacements = []): string
{
    $messages = i18n_messages();
    $text = (string) ($messages[$key] ?? $key);
    foreach ($replacements as $name => $value) {
        $text = str_replace('{' . $name . '}', (string) $value, $text);
    }

    return $text;
}

function app_lang_url(string $path = '', array $query = []): string
{
    $query['lang'] = current_lang();
    $url = app_url($path);
    $qs = http_build_query($query);
    if ($qs === '') {
        return $url;
    }

    return $url . (str_contains($url, '?') ? '&' : '?') . $qs;
}
