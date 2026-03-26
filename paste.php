<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/i18n.php';

$code = (string) ($_GET['code'] ?? '');
if (!preg_match('/^[0-9]{6}$/', $code)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(current_lang(), ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars(lang_dir(), ENT_QUOTES, 'UTF-8') ?>" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(t('home.open_button'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?> · <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body data-code="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" class="text-light" data-theme="dark">
<main class="container py-5 app-wrap">
    <div class="app-nav">
        <a class="text-decoration-none text-reset app-brand" href="<?= htmlspecialchars(app_lang_url('index.php'), ENT_QUOTES, 'UTF-8') ?>"><?= APP_NAME ?></a>
        <div class="app-nav-controls">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="langDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <?= htmlspecialchars(t('ui.lang'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <ul class="dropdown-menu" aria-labelledby="langDropdown">
                    <li><a class="dropdown-item" href="<?= htmlspecialchars(app_url('paste.php?code=' . urlencode($code) . '&lang=en'), ENT_QUOTES, 'UTF-8') ?>">English</a></li>
                    <li><a class="dropdown-item" href="<?= htmlspecialchars(app_url('paste.php?code=' . urlencode($code) . '&lang=fa'), ENT_QUOTES, 'UTF-8') ?>">فارسی</a></li>
                </ul>
            </div>
            <button id="themeToggle" type="button" class="btn btn-sm btn-outline-secondary"></button>
        </div>
    </div>

    <h1 class="h4 mono mb-2"><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></h1>
    <p id="status" class="text-secondary"><?= htmlspecialchars(t('paste.loading'), ENT_QUOTES, 'UTF-8') ?></p>

    <form id="decryptForm" class="card pane bg-dark-subtle border-secondary-subtle shadow-sm d-none mb-3">
        <div class="card-body">
            <div class="input-group">
                <input id="password" class="form-control" type="password" placeholder="<?= htmlspecialchars(t('create.password_placeholder'), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn-primary"><?= htmlspecialchars(t('paste.decrypt'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </div>
    </form>

    <section id="contentCard" class="card pane bg-dark-subtle border-secondary-subtle shadow-sm d-none mb-3">
        <div class="card-body">
            <h2 id="pasteTitle" class="h5"><?= htmlspecialchars(t('paste.untitled'), ENT_QUOTES, 'UTF-8') ?></h2>
            <div id="pasteOutput"></div>
        </div>
    </section>

    <section id="forensicsCard" class="card pane bg-dark-subtle border-secondary-subtle shadow-sm d-none mb-3">
        <div class="card-body">
            <h2 class="h6"><?= htmlspecialchars(t('paste.forensics'), ENT_QUOTES, 'UTF-8') ?></h2>
            <pre id="forensicsOutput" class="mb-0"></pre>
        </div>
    </section>

    <section id="discussionCard" class="card pane bg-dark-subtle border-secondary-subtle shadow-sm d-none">
        <div class="card-body">
            <h2 class="h6"><?= htmlspecialchars(t('paste.discussion'), ENT_QUOTES, 'UTF-8') ?></h2>
            <div id="discussionList" class="vstack gap-2 mb-3"></div>
            <form id="discussionForm" class="input-group">
                <input id="discussionInput" class="form-control" type="text" placeholder="<?= htmlspecialchars(t('paste.message_placeholder'), ENT_QUOTES, 'UTF-8') ?>" maxlength="2000">
                <button type="submit" class="btn btn-outline-light"><?= htmlspecialchars(t('paste.send'), ENT_QUOTES, 'UTF-8') ?></button>
            </form>
        </div>
    </section>
</main>
<script>window.__APP_BASE = <?= json_encode(app_base_path(), JSON_UNESCAPED_SLASHES) ?>;</script>
<script>window.__I18N = <?= json_encode(i18n_messages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script>window.__APP_LANG = <?= json_encode(current_lang(), JSON_UNESCAPED_SLASHES) ?>;</script>
<script type="module" src="<?= htmlspecialchars(app_url('assets/js/ui.js?v=20260327a'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script type="module" src="<?= htmlspecialchars(app_url('assets/js/view.js?v=20260327f'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
