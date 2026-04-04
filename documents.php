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
    <title><?= APP_NAME ?> · Documents</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(app_relative_url('assets/vendor/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_relative_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="text-light" data-theme="dark">
<main class="container py-5 app-wrap">
    <div class="app-nav mb-3">
        <a class="text-decoration-none text-reset app-brand" href="<?= htmlspecialchars(app_lang_url('index.php'), ENT_QUOTES, 'UTF-8') ?>"><?= APP_NAME ?></a>
        <div class="app-nav-controls">
            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_lang_url('privacy.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('nav.privacy'), ENT_QUOTES, 'UTF-8') ?></a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_lang_url('mirror.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('nav.mirror'), ENT_QUOTES, 'UTF-8') ?></a>
            <button id="themeToggle" type="button" class="btn btn-sm btn-outline-secondary"></button>
        </div>
    </div>

    <div class="card pane bg-dark-subtle border-secondary-subtle shadow-sm">
        <div class="card-body p-4 p-md-5">
            <h1 class="h3 mb-3">How this website works</h1>
            <p class="text-secondary mb-3">PasteChi encrypts your paste in the browser first, then uploads only encrypted data to the server.</p>
            <ol class="mb-4">
                <li>You create a paste and optional attachment in <strong>create page</strong>.</li>
                <li>Encryption runs in your browser via Web Crypto (AES-GCM + PBKDF2).</li>
                <li>The server stores ciphertext, metadata, and hashed code.</li>
                <li>The recipient opens your link and decrypts in their browser.</li>
            </ol>

            <h2 class="h5 mb-2">Security responsibility</h2>
            <p class="text-secondary mb-4">Security depends heavily on browser cryptography libraries and runtime implementations (Web Crypto API), strong passwords, and secure link sharing. Server-side data remains encrypted, but endpoint/browser safety is still critical.</p>

            <h2 class="h5 mb-2">Development note</h2>
            <p class="text-secondary mb-0">This website was developed with help from AI-assisted coding tools, then adapted for self-hosting on standard PHP environments.</p>
        </div>
    </div>
</main>
<script type="module" src="<?= htmlspecialchars(app_relative_url('assets/js/ui.js?v=20260327a'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(app_relative_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
