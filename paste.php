<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/i18n.php';
require_once __DIR__ . '/lib/navbar.php';
require_once __DIR__ . '/lib/ui-components.php';

$code = (string) ($_GET['code'] ?? '');
if (!preg_match('/^[0-9]{6}$/', $code)) {
    http_response_code(404);
    ?>
<!doctype html>
<html lang="<?= htmlspecialchars(current_lang(), ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars(lang_dir(), ENT_QUOTES, 'UTF-8') ?>" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 · <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(app_relative_url('assets/vendor/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_relative_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="text-light" data-theme="dark">
<main class="container py-5 app-wrap">
    <div class="card pane bg-dark-subtle border-secondary-subtle shadow-sm">
        <div class="card-body p-4 p-md-5 text-center">
            <h1 class="h4 mb-2">404</h1>
            <p class="text-secondary mb-3">Not found</p>
            <a class="btn btn-primary" href="<?= htmlspecialchars(app_lang_url('create.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('home.create_button'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
    </div>
</main>
</body>
</html>
<?php
    exit;
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(current_lang(), ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars(lang_dir(), ENT_QUOTES, 'UTF-8') ?>" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(t('home.open_button'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?> · <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(app_relative_url('assets/vendor/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_relative_url('assets/vendor/highlight/github-dark.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_relative_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body data-code="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" class="text-light" data-theme="dark">
<main class="container py-5 app-wrap">
    <?php render_app_navbar(); ?>

    <h1 class="h4 mono mb-2"><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></h1>
    <p id="status" class="text-secondary"><?= htmlspecialchars(t('paste.loading'), ENT_QUOTES, 'UTF-8') ?></p>
    <a id="newPasteBtn" class="btn btn-sm btn-outline-light d-none mb-3" href="<?= htmlspecialchars(app_lang_url('create.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('home.create_button'), ENT_QUOTES, 'UTF-8') ?></a>

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
            <div id="attachmentBox" class="mt-3 d-none">
                <h3 class="h6 mb-2"><?= htmlspecialchars(t('paste.attachment'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p id="attachmentMeta" class="small text-secondary mb-2"></p>
                <button id="downloadAttachmentBtn" class="btn btn-sm btn-outline-light" type="button"><?= htmlspecialchars(t('paste.download_attachment'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
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
            <div id="discussionList" class="chat-thread mb-3"></div>
            <?php render_discussion_input_form(); ?>
        </div>
    </section>
</main>
<script>window.__APP_BASE = <?= json_encode(app_base_path(), JSON_UNESCAPED_SLASHES) ?>;</script>
<script>window.__I18N = <?= json_encode(i18n_messages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script>window.__APP_LANG = <?= json_encode(current_lang(), JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= htmlspecialchars(app_relative_url('assets/vendor/highlight/highlight.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script type="module" src="<?= htmlspecialchars(app_relative_url('assets/js/ui.js?v=20260327a'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script type="module" src="<?= htmlspecialchars(app_relative_url('assets/js/input-markdown.js?v=20260404a'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script type="module" src="<?= htmlspecialchars(app_relative_url('assets/js/view.js?v=20260327g'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(app_relative_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
