<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/db-config.php';
require_once __DIR__ . '/lib/i18n.php';

$setupRequired = db_setup_required();
$dbConnected = db_can_connect();

if ($setupRequired) {
    header('Location: ' . app_url('install.php'));
    exit;
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(current_lang(), ENT_QUOTES, 'UTF-8') ?>"
    dir="<?= htmlspecialchars(lang_dir(), ENT_QUOTES, 'UTF-8') ?>" data-bs-theme="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= APP_NAME ?> · <?= htmlspecialchars(t('home.title'), ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_relative_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>

<body class="text-light" data-theme="dark">
    <main class="container app-wrap min-vh-100 py-3 d-flex flex-column">
        <div class="app-nav">
            <div class="app-brand"><?= APP_NAME ?></div>
            <div class="app-nav-controls">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="langDropdown"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <?= htmlspecialchars(t('ui.lang'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="langDropdown">
                        <li><a class="dropdown-item"
                                href="<?= htmlspecialchars(app_relative_url('?lang=en'), ENT_QUOTES, 'UTF-8') ?>">English</a>
                        </li>
                        <li><a class="dropdown-item"
                                href="<?= htmlspecialchars(app_relative_url('?lang=fa'), ENT_QUOTES, 'UTF-8') ?>">فارسی</a></li>
                    </ul>
                </div>
                <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_lang_url('documents.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('nav.documents'), ENT_QUOTES, 'UTF-8') ?></a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_lang_url('privacy.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('nav.privacy'), ENT_QUOTES, 'UTF-8') ?></a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_lang_url('mirror.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('nav.mirror'), ENT_QUOTES, 'UTF-8') ?></a>
                <button id="themeToggle" type="button" class="btn btn-sm btn-outline-secondary"></button>
            </div>
        </div>

        <div class="flex-grow-1 d-flex align-items-center">
            <div class="card pane bg-dark-subtle border-secondary-subtle shadow-sm w-100">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 mb-2"><?= APP_NAME ?></h1>
                    <p class="text-secondary mb-4"><?= htmlspecialchars(t('app.tagline'), ENT_QUOTES, 'UTF-8') ?></p>

                    <?php if (!$dbConnected): ?>
                        <div class="alert alert-warning" role="alert">
                            <?= htmlspecialchars(t('home.db_failed'), ENT_QUOTES, 'UTF-8') ?> <a
                                href="<?= htmlspecialchars(app_lang_url('install.php'), ENT_QUOTES, 'UTF-8') ?>"
                                class="alert-link"><?= htmlspecialchars(t('home.installer'), ENT_QUOTES, 'UTF-8') ?></a>.
                        </div>
                    <?php endif; ?>

                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-8">
                            <label for="codeInput"
                                class="form-label"><?= htmlspecialchars(t('home.open_label'), ENT_QUOTES, 'UTF-8') ?></label>
                            <form id="findForm" class="input-group">
                                <input id="codeInput" class="form-control mono" type="text" maxlength="6" minlength="6"
                                    pattern="[0-9]{6}" inputmode="numeric" placeholder="123456" required>
                                <button type="submit"
                                    class="btn btn-primary"><?= htmlspecialchars(t('home.open_button'), ENT_QUOTES, 'UTF-8') ?></button>
                            </form>
                        </div>
                        <div class="col-12 col-md-4 d-grid">
                            <button id="createBtn" class="btn btn-outline-light"
                                type="button"><?= htmlspecialchars(t('home.create_button'), ENT_QUOTES, 'UTF-8') ?></button>
                        </div>
                    </div>
                    <div class="form-text text-secondary mt-2">
                        <strong>
                            <?= htmlspecialchars(t('home.security.title'), ENT_QUOTES, 'UTF-8') ?>
                        </strong>
                        <ul class="mb-0 mt-1 ps-3 small">
                            <li>
                                <?= htmlspecialchars(t('home.security.client_side'), ENT_QUOTES, 'UTF-8') ?>
                            </li>
                            <li>
                                <?= htmlspecialchars(t('home.security.algorithm'), ENT_QUOTES, 'UTF-8') ?>
                            </li>
                            <li>
                                <?= htmlspecialchars(t('home.security.code_hash'), ENT_QUOTES, 'UTF-8') ?>
                            </li>
                            <li>
                                <?= htmlspecialchars(t('home.security.tracking'), ENT_QUOTES, 'UTF-8') ?>
                            </li>
                            <li>
                                <?= htmlspecialchars(t('home.security.storage'), ENT_QUOTES, 'UTF-8') ?>
                            </li>
                            <li>
                                <?= htmlspecialchars(t('home.security.ip'), ENT_QUOTES, 'UTF-8') ?>
                            </li>
                            <li>
                                <?= htmlspecialchars(t('home.security.dependency'), ENT_QUOTES, 'UTF-8') ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script>window.__APP_BASE = <?= json_encode(app_base_path(), JSON_UNESCAPED_SLASHES) ?>;</script>
    <script>window.__I18N = <?= json_encode(i18n_messages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
    <script>window.__APP_LANG = <?= json_encode(current_lang(), JSON_UNESCAPED_SLASHES) ?>;</script>
    <script type="module"
        src="<?= htmlspecialchars(app_relative_url('assets/js/ui.js?v=20260327a'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script type="module"
        src="<?= htmlspecialchars(app_relative_url('assets/js/home.js?v=20260327c'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
</body>

</html>