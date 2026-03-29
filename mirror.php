<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/i18n.php';

const MIRROR_DIR = STORAGE_ROOT . '/mirror';
const MIRROR_ZIP_NAME = 'pastechi-mirror.zip';

function mirror_zip_path(): string
{
    return MIRROR_DIR . '/' . MIRROR_ZIP_NAME;
}

function mirror_rel_zip_url(): string
{
    return app_relative_url('storage/mirror/' . MIRROR_ZIP_NAME);
}

function mirror_repo_root(): string
{
    return __DIR__;
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

$hash = '';
$size = 0;
$error = '';
$zipExists = is_file(mirror_zip_path());

if (isset($_GET['generate']) && $_GET['generate'] === '1') {
    $generated = generate_mirror_zip();
    if (!($generated['ok'] ?? false)) {
        $error = (string) ($generated['error'] ?? 'Archive generation failed.');
    } else {
        $zipExists = true;
        $hash = (string) ($generated['hash'] ?? '');
        $size = (int) ($generated['size'] ?? 0);
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
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

            <div class="mb-3">
                <a class="btn btn-outline-light" href="https://github.com/alisentinel/pastechi" target="_blank" rel="noopener noreferrer">GitHub repository</a>
                <a class="btn btn-primary" href="<?= htmlspecialchars(app_lang_url('mirror.php', ['generate' => '1']), ENT_QUOTES, 'UTF-8') ?>">Generate / Refresh ZIP</a>
            </div>

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
                    No ZIP generated yet. Click <strong>Generate / Refresh ZIP</strong>.
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<script type="module" src="<?= htmlspecialchars(app_relative_url('assets/js/ui.js?v=20260327a'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
