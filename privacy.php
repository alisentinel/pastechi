<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/i18n.php';
?>
<!doctype html>
<html lang="<?= htmlspecialchars(current_lang(), ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars(lang_dir(), ENT_QUOTES, 'UTF-8') ?>" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= APP_NAME ?> · Privacy Policy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_relative_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="text-light" data-theme="dark">
<main class="container py-5 app-wrap">
    <div class="app-nav mb-3">
        <a class="text-decoration-none text-reset app-brand" href="<?= htmlspecialchars(app_lang_url('index.php'), ENT_QUOTES, 'UTF-8') ?>"><?= APP_NAME ?></a>
        <div class="app-nav-controls">
            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_lang_url('documents.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('nav.documents'), ENT_QUOTES, 'UTF-8') ?></a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_lang_url('mirror.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('nav.mirror'), ENT_QUOTES, 'UTF-8') ?></a>
            <button id="themeToggle" type="button" class="btn btn-sm btn-outline-secondary"></button>
        </div>
    </div>

    <div class="card pane bg-dark-subtle border-secondary-subtle shadow-sm">
        <div class="card-body p-4 p-md-5">
            <h1 class="h3 mb-3">Privacy Policy</h1>
            <ul class="mb-4">
                <li>Paste content is encrypted in your browser before upload.</li>
                <li>Server stores encrypted payloads and minimal metadata only.</li>
                <li>No third-party analytics trackers are included by default.</li>
                <li>Optional access controls (password, fragment key, view limits) are user-managed.</li>
            </ul>

            <h2 class="h5 mb-2">What can still be visible</h2>
            <p class="text-secondary mb-3">The server can still observe operational metadata such as request timing, endpoint usage, and rate-limit events needed for service reliability.</p>

            <h2 class="h5 mb-2">Security note about libraries</h2>
            <p class="text-secondary mb-3">Cryptographic security is based on browser crypto libraries and runtime implementations (Web Crypto API). Keep your browser updated and use trusted clients.</p>

            <h2 class="h5 mb-2">AI assistance disclosure</h2>
            <p class="text-secondary mb-0">Parts of this project were developed with help from AI-assisted coding tools.</p>
        </div>
    </div>
</main>
<script type="module" src="<?= htmlspecialchars(app_relative_url('assets/js/ui.js?v=20260327a'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
