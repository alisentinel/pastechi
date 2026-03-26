<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/db-config.php';

$setupRequired = db_setup_required();
$dbConnected = db_can_connect();

if ($setupRequired) {
    header('Location: ' . app_url('install.php'));
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= APP_NAME ?> · Zero-knowledge paste</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="text-light">
<main class="container py-5 app-wrap">
    <div class="card bg-dark-subtle border-secondary-subtle shadow-sm">
        <div class="card-body p-4 p-md-5">
            <h1 class="h3 mb-2"><?= APP_NAME ?></h1>
            <p class="text-secondary mb-4">If the server cannot read it, nobody can.</p>

            <?php if (!$dbConnected): ?>
                <div class="alert alert-warning" role="alert">
                    Database connection failed. Go to <a href="<?= htmlspecialchars(app_url('install.php'), ENT_QUOTES, 'UTF-8') ?>" class="alert-link">installer</a> to fix configuration.
                </div>
            <?php endif; ?>

            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-8">
                    <label for="codeInput" class="form-label">Open by 6-digit code</label>
                    <form id="findForm" class="input-group">
                        <input id="codeInput" class="form-control mono" type="text" maxlength="6" minlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="123456" required>
                        <button type="submit" class="btn btn-primary">Open Paste</button>
                    </form>
                </div>
                <div class="col-12 col-md-4 d-grid">
                    <button id="createBtn" class="btn btn-outline-light" type="button">Create Paste</button>
                </div>
            </div>
        </div>
    </div>
</main>
<script>window.__APP_BASE = <?= json_encode(app_base_path(), JSON_UNESCAPED_SLASHES) ?>;</script>
<script type="module" src="<?= htmlspecialchars(app_url('assets/js/home.js?v=20260327b'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
