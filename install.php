<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/db-config.php';
require_once __DIR__ . '/lib/i18n.php';

$setupRequired = db_setup_required();
if (!$setupRequired && db_can_connect()) {
    header('Location: ' . app_url());
    exit;
}

$errors = [];
if (isset($_GET['error']) && $_GET['error'] !== '') {
    $errors[] = (string) $_GET['error'];
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(current_lang(), ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars(lang_dir(), ENT_QUOTES, 'UTF-8') ?>" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(t('install.title'), ENT_QUOTES, 'UTF-8') ?> · <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="text-light" data-theme="dark">
<main class="container py-5 app-wrap">
    <div class="app-nav">
        <a class="text-decoration-none text-reset fw-semibold" href="<?= htmlspecialchars(app_lang_url('index.php'), ENT_QUOTES, 'UTF-8') ?>"><?= APP_NAME ?></a>
        <div class="app-nav-controls">
            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('install.php?lang=en'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('ui.lang.en'), ENT_QUOTES, 'UTF-8') ?></a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('install.php?lang=fa'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('ui.lang.fa'), ENT_QUOTES, 'UTF-8') ?></a>
            <button id="themeToggle" type="button" class="btn btn-sm btn-outline-secondary" data-day-label="<?= htmlspecialchars(t('ui.theme.day'), ENT_QUOTES, 'UTF-8') ?>" data-night-label="<?= htmlspecialchars(t('ui.theme.night'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('ui.theme.day'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </div>

    <div class="card pane bg-dark-subtle border-secondary-subtle shadow-sm">
        <div class="card-body p-4 p-md-5">
            <h1 class="h3 mb-2"><?= htmlspecialchars(t('install.title'), ENT_QUOTES, 'UTF-8') ?> <?= APP_NAME ?></h1>
            <p class="text-secondary mb-4"><?= htmlspecialchars(t('install.subtitle'), ENT_QUOTES, 'UTF-8') ?></p>

            <?php if ($errors !== []): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars(implode(' | ', $errors), ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= htmlspecialchars(app_url('api/install.php'), ENT_QUOTES, 'UTF-8') ?>" class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="dbHost" class="form-label"><?= htmlspecialchars(t('install.db_host'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input id="dbHost" name="db_host" class="form-control" type="text" value="<?= htmlspecialchars((string) getenv('DB_HOST'), ENT_QUOTES, 'UTF-8') ?: 'localhost' ?>" required>
                </div>
                <div class="col-12 col-md-6">
                    <label for="dbPort" class="form-label"><?= htmlspecialchars(t('install.db_port'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input id="dbPort" name="db_port" class="form-control" type="number" min="1" max="65535" value="<?= htmlspecialchars((string) getenv('DB_PORT'), ENT_QUOTES, 'UTF-8') ?: '3306' ?>" required>
                </div>
                <div class="col-12 col-md-6">
                    <label for="dbName" class="form-label"><?= htmlspecialchars(t('install.db_name'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input id="dbName" name="db_name" class="form-control" type="text" value="<?= htmlspecialchars((string) getenv('DB_NAME'), ENT_QUOTES, 'UTF-8') ?: 'pastechi' ?>" required>
                </div>
                <div class="col-12 col-md-6">
                    <label for="dbUser" class="form-label"><?= htmlspecialchars(t('install.db_user'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input id="dbUser" name="db_user" class="form-control" type="text" value="<?= htmlspecialchars((string) getenv('DB_USER'), ENT_QUOTES, 'UTF-8') ?: 'root' ?>" required>
                </div>
                <div class="col-12">
                    <label for="dbPass" class="form-label"><?= htmlspecialchars(t('install.db_pass'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input id="dbPass" name="db_pass" class="form-control" type="password" value="" autocomplete="new-password">
                </div>
                <div class="col-12 d-grid mt-2">
                    <button type="submit" class="btn btn-primary"><?= htmlspecialchars(t('install.submit'), ENT_QUOTES, 'UTF-8') ?></button>
                </div>
            </form>
        </div>
    </div>
</main>
<script type="module" src="<?= htmlspecialchars(app_url('assets/js/ui.js?v=20260327a'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
