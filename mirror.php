<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/i18n.php';

const MIRROR_DIR = STORAGE_ROOT . '/mirror';
const MIRROR_ZIP_NAME = 'pastechi-mirror.zip';
const MIRROR_LOCK_NAME = 'build.lock';
const MIRROR_STATE_NAME = 'state.json';

function mirror_zip_path(): string
{
    return MIRROR_DIR . '/' . MIRROR_ZIP_NAME;
}

function mirror_rel_zip_url(): string
{
    return app_relative_url('mirror.php?action=download');
}

function mirror_repo_root(): string
{
    return __DIR__;
}

function mirror_lock_path(): string
{
    return MIRROR_DIR . '/' . MIRROR_LOCK_NAME;
}

function mirror_state_path(): string
{
    return MIRROR_DIR . '/' . MIRROR_STATE_NAME;
}

function ensure_mirror_dir(): void
{
    if (!is_dir(MIRROR_DIR)) {
        mkdir(MIRROR_DIR, 0775, true);
    }
}

function should_exclude_path(string $relativePath): bool
{
    $normalized = str_replace('\\', '/', ltrim($relativePath, '/'));
    if ($normalized === '') {
        return true;
    }

    $excludedPrefixes = [
        '.git/',
        'storage/mirror/',
    ];

    foreach ($excludedPrefixes as $prefix) {
        if (str_starts_with($normalized, $prefix)) {
            return true;
        }
    }

    return false;
}

function generate_mirror_zip(): array
{
    ensure_mirror_dir();
    $zipPath = mirror_zip_path();
    $root = mirror_repo_root();

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return [
                'ok' => false,
                'error' => 'Failed to create project archive.',
            ];
        }

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $fullPath = $file->getPathname();
            $relativePath = str_replace('\\', '/', substr($fullPath, strlen($root) + 1));
            if (should_exclude_path($relativePath)) {
                continue;
            }

            $zip->addFile($fullPath, $relativePath);
        }

        $zip->close();
    } elseif (class_exists('PharData')) {
        if (is_file($zipPath)) {
            unlink($zipPath);
        }

        try {
            $archive = new PharData($zipPath, 0, null, Phar::ZIP);
            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo || !$file->isFile()) {
                    continue;
                }

                $fullPath = $file->getPathname();
                $relativePath = str_replace('\\', '/', substr($fullPath, strlen($root) + 1));
                if (should_exclude_path($relativePath)) {
                    continue;
                }

                $archive->addFile($fullPath, $relativePath);
            }
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => 'Archive creation failed. Enable ZipArchive or Phar writing support.',
            ];
        }
    } else {
        return [
            'ok' => false,
            'error' => 'ZIP creation is unavailable (ZipArchive/PharData not installed).',
        ];
    }

    $sha256 = hash_file('sha256', $zipPath);
    if (!is_string($sha256)) {
        return [
            'ok' => false,
            'error' => 'Failed to calculate SHA-256 hash.',
        ];
    }

    return [
        'ok' => true,
        'zipPath' => $zipPath,
        'hash' => $sha256,
        'size' => filesize($zipPath) ?: 0,
    ];
}

function mirror_source_fingerprint(): string
{
    $root = mirror_repo_root();
    $rolling = hash_init('sha256');

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        $fullPath = $file->getPathname();
        $relativePath = str_replace('\\', '/', substr($fullPath, strlen($root) + 1));
        if (should_exclude_path($relativePath)) {
            continue;
        }

        $fileHash = hash_file('sha256', $fullPath);
        if (!is_string($fileHash)) {
            continue;
        }

        hash_update($rolling, $relativePath . '|' . $fileHash . "\n");
    }

    return hash_final($rolling);
}

function mirror_read_state(): array
{
    $statePath = mirror_state_path();
    if (!is_file($statePath)) {
        return [];
    }

    $raw = file_get_contents($statePath);
    if (!is_string($raw) || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function mirror_write_state(string $sourceFingerprint, string $zipHash): void
{
    $state = [
        'sourceFingerprint' => $sourceFingerprint,
        'zipHash' => $zipHash,
        'updatedAt' => time(),
    ];

    file_put_contents(
        mirror_state_path(),
        json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

function should_rebuild_mirror_zip(string $sourceFingerprint): bool
{
    if (!is_file(mirror_zip_path())) {
        return true;
    }

    $state = mirror_read_state();
    $previous = (string) ($state['sourceFingerprint'] ?? '');

    if ($previous === '') {
        return true;
    }

    return !hash_equals($previous, $sourceFingerprint);
}

function auto_refresh_mirror_zip(): array
{
    ensure_mirror_dir();
    $sourceFingerprint = mirror_source_fingerprint();

    if (!should_rebuild_mirror_zip($sourceFingerprint)) {
        return [
            'ok' => true,
            'generated' => false,
            'reason' => 'up_to_date',
        ];
    }

    $lockHandle = fopen(mirror_lock_path(), 'c+');
    if ($lockHandle === false) {
        return [
            'ok' => false,
            'generated' => false,
            'error' => 'Could not open build lock file.',
        ];
    }

    try {
        if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
            return [
                'ok' => true,
                'generated' => false,
                'reason' => 'generation_in_progress',
            ];
        }

        $sourceFingerprintAfterLock = mirror_source_fingerprint();
        if (!should_rebuild_mirror_zip($sourceFingerprintAfterLock)) {
            return [
                'ok' => true,
                'generated' => false,
                'reason' => 'already_refreshed',
            ];
        }

        $generated = generate_mirror_zip();
        if (!($generated['ok'] ?? false)) {
            return [
                'ok' => false,
                'generated' => false,
                'error' => (string) ($generated['error'] ?? 'Archive generation failed.'),
            ];
        }

        $zipHash = (string) ($generated['hash'] ?? '');
        if ($zipHash !== '') {
            mirror_write_state($sourceFingerprintAfterLock, $zipHash);
        }

        return [
            'ok' => true,
            'generated' => true,
            'reason' => 'generated',
            'hash' => $zipHash,
            'size' => (int) ($generated['size'] ?? 0),
        ];
    } finally {
        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);
    }
}

function mirror_stream_zip(): void
{
    if (!is_file(mirror_zip_path())) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }

    $downloadName = 'pastechi-mirror.zip';
    header('Content-Type: application/zip');
    header('Content-Length: ' . (string) filesize(mirror_zip_path()));
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('X-Content-Type-Options: nosniff');
    readfile(mirror_zip_path());
    exit;
}

$action = (string) ($_GET['action'] ?? '');
if ($action === 'download') {
    $downloadState = auto_refresh_mirror_zip();
    if (!($downloadState['ok'] ?? false)) {
        http_response_code(500);
        echo (string) ($downloadState['error'] ?? 'Archive generation failed.');
        exit;
    }

    mirror_stream_zip();
}

$hash = '';
$size = 0;
$error = '';
$status = '';
$zipExists = is_file(mirror_zip_path());

$auto = auto_refresh_mirror_zip();
if (!($auto['ok'] ?? false)) {
    $error = (string) ($auto['error'] ?? 'Archive generation failed.');
} else {
    if (($auto['generated'] ?? false) === true) {
        $status = 'Mirror ZIP was refreshed automatically.';
        $zipExists = true;
        $hash = (string) ($auto['hash'] ?? '');
        $size = (int) ($auto['size'] ?? 0);
    } else {
        $status = 'Mirror ZIP is up to date (regenerates only when files change).';
    }
}

if ($zipExists && $hash === '') {
    $hash = (string) (hash_file('sha256', mirror_zip_path()) ?: '');
    $size = (int) (filesize(mirror_zip_path()) ?: 0);
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(current_lang(), ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars(lang_dir(), ENT_QUOTES, 'UTF-8') ?>" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= APP_NAME ?> · <?= htmlspecialchars(t('nav.mirror'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(app_relative_url('assets/vendor/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_relative_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="text-light" data-theme="dark">
<main class="container py-5 app-wrap">
    <div class="app-nav mb-3">
        <a class="text-decoration-none text-reset app-brand" href="<?= htmlspecialchars(app_lang_url('index.php'), ENT_QUOTES, 'UTF-8') ?>"><?= APP_NAME ?></a>
        <div class="app-nav-controls">
            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_lang_url('documents.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('nav.documents'), ENT_QUOTES, 'UTF-8') ?></a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_lang_url('privacy.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('nav.privacy'), ENT_QUOTES, 'UTF-8') ?></a>
            <button id="themeToggle" type="button" class="btn btn-sm btn-outline-secondary"></button>
        </div>
    </div>

    <div class="card pane bg-dark-subtle border-secondary-subtle shadow-sm">
        <div class="card-body p-4 p-md-5">
            <h1 class="h3 mb-3">Create your mirror</h1>
            <p class="text-secondary">Use this page to mirror the current project source quickly.</p>
            <p class="text-secondary">ZIP refresh runs automatically on visit, with lock protection and file-change detection.</p>

            <div class="mb-3">
                <span class="small text-secondary">Offline mode enabled: no external repository links.</span>
            </div>

            <?php if ($status !== ''): ?>
                <div class="alert alert-info" role="alert">
                    <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if ($zipExists): ?>
                <div class="border rounded p-3 bg-body-tertiary">
                    <p class="mb-2"><strong>Project ZIP:</strong> <a href="<?= htmlspecialchars(mirror_rel_zip_url(), ENT_QUOTES, 'UTF-8') ?>">Download latest auto-generated archive</a></p>
                    <p class="mb-2"><strong>SHA-256:</strong> <span class="mono"><?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?></span></p>
                    <p class="mb-0"><strong>Size:</strong> <?= number_format($size) ?> bytes</p>
                </div>
            <?php else: ?>
                <div class="alert alert-secondary" role="alert">
                    No ZIP generated yet. Reload this page after enabling ZIP support on server.
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<script type="module" src="<?= htmlspecialchars(app_relative_url('assets/js/ui.js?v=20260327a'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(app_relative_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
