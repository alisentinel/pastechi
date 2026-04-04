<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/i18n.php';
require_once __DIR__ . '/lib/navbar.php';
?>
<!doctype html>
<html lang="<?= htmlspecialchars(current_lang(), ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars(lang_dir(), ENT_QUOTES, 'UTF-8') ?>" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= APP_NAME ?> · Privacy Policy</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(app_relative_url('assets/vendor/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_relative_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="text-light" data-theme="dark">
<main class="container py-5 app-wrap">
    <?php render_app_navbar(); ?>

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
<script src="<?= htmlspecialchars(app_relative_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
