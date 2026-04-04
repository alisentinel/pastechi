<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/i18n.php';
require_once __DIR__ . '/lib/navbar.php';
require_once __DIR__ . '/lib/ui-components.php';
?>
<!doctype html>
<html lang="<?= htmlspecialchars(current_lang(), ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars(lang_dir(), ENT_QUOTES, 'UTF-8') ?>" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(t('create.title'), ENT_QUOTES, 'UTF-8') ?> · <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(app_relative_url('assets/vendor/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_relative_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="text-light" data-theme="dark">
<main class="container py-5 app-wrap">
    <?php render_app_navbar(); ?>

    <div id="createPane" class="card pane bg-dark-subtle border-secondary-subtle shadow-sm">
        <div class="card-body p-4 p-md-5">
            <h1 class="h3 mb-3"><?= htmlspecialchars(t('create.title'), ENT_QUOTES, 'UTF-8') ?></h1>

            <form id="createForm" action="#" method="post">
                <div class="mb-3">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <label class="form-label mb-0"><?= htmlspecialchars(t('create.content_placeholder'), ENT_QUOTES, 'UTF-8') ?></label>
                        <button id="addMessageBtn" class="btn btn-sm btn-outline-light" type="button">+ Add textbox</button>
                    </div>
                    <div id="messageBlocks" class="vstack gap-2"></div>
                    <?php render_create_message_block_template(); ?>
                </div>
                <div class="mb-3">
                    <label for="attachment" class="form-label"><?= htmlspecialchars(t('create.attachment_label'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input id="attachment" class="form-control" type="file">
                    <div id="attachmentPolicyHint" class="form-text text-secondary"><?= htmlspecialchars(t('create.attachment_hint'), ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <details class="border rounded p-3 mb-3">
                    <summary class="fw-semibold"><?= htmlspecialchars(t('create.advanced'), ENT_QUOTES, 'UTF-8') ?></summary>
                    <div class="pt-3">
                        <div class="row g-3">
                            <div class="col-12">
                                <input id="title" class="form-control" type="text" placeholder="<?= htmlspecialchars(t('create.title_placeholder'), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-12">
                                <input id="password" class="form-control" type="password" placeholder="<?= htmlspecialchars(t('create.password_placeholder'), ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="ttlSeconds" class="form-label"><?= htmlspecialchars(t('create.ttl_label'), ENT_QUOTES, 'UTF-8') ?></label>
                                <select id="ttlSeconds" class="form-select">
                                    <option value="600"><?= htmlspecialchars(t('create.ttl.10m'), ENT_QUOTES, 'UTF-8') ?></option>
                                    <option value="1800"><?= htmlspecialchars(t('create.ttl.30m'), ENT_QUOTES, 'UTF-8') ?></option>
                                    <option value="3600" selected><?= htmlspecialchars(t('create.ttl.1h'), ENT_QUOTES, 'UTF-8') ?></option>
                                    <option value="21600"><?= htmlspecialchars(t('create.ttl.6h'), ENT_QUOTES, 'UTF-8') ?></option>
                                    <option value="43200"><?= htmlspecialchars(t('create.ttl.12h'), ENT_QUOTES, 'UTF-8') ?></option>
                                    <option value="86400"><?= htmlspecialchars(t('create.ttl.24h'), ENT_QUOTES, 'UTF-8') ?></option>
                                    <option value="172800"><?= htmlspecialchars(t('create.ttl.48h'), ENT_QUOTES, 'UTF-8') ?></option>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="maxViews" class="form-label"><?= htmlspecialchars(t('create.views_label'), ENT_QUOTES, 'UTF-8') ?></label>
                                <input id="maxViews" class="form-control" type="number" min="0" max="1000" step="1" value="0">
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input id="uniqueViewsOnly" class="form-check-input" type="checkbox">
                                    <label for="uniqueViewsOnly" class="form-check-label"><?= htmlspecialchars(t('create.unique_views_label'), ENT_QUOTES, 'UTF-8') ?></label>
                                </div>
                                <div class="form-text text-secondary"><?= htmlspecialchars(t('create.unique_views_hint'), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input id="useFragmentKey" class="form-check-input" type="checkbox">
                                    <label for="useFragmentKey" class="form-check-label"><?= htmlspecialchars(t('create.fragment_label'), ENT_QUOTES, 'UTF-8') ?></label>
                                </div>
                                <div class="form-text text-secondary"><?= htmlspecialchars(t('create.fragment_hint'), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="timeLock" class="form-label"><?= htmlspecialchars(t('create.timelock_label'), ENT_QUOTES, 'UTF-8') ?></label>
                                <input id="timeLock" class="form-control" type="datetime-local">
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="bindingType" class="form-label"><?= htmlspecialchars(t('create.binding_label'), ENT_QUOTES, 'UTF-8') ?></label>
                                <select id="bindingType" class="form-select">
                                    <option value="none"><?= htmlspecialchars(t('create.binding.none'), ENT_QUOTES, 'UTF-8') ?></option>
                                    <option value="ip"><?= htmlspecialchars(t('create.binding.ip'), ENT_QUOTES, 'UTF-8') ?></option>
                                    <option value="fingerprint"><?= htmlspecialchars(t('create.binding.fingerprint'), ENT_QUOTES, 'UTF-8') ?></option>
                                </select>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input id="discussionMode" class="form-check-input" type="checkbox">
                                    <label for="discussionMode" class="form-check-label"><?= htmlspecialchars(t('create.discussion_label'), ENT_QUOTES, 'UTF-8') ?></label>
                                </div>
                                <div class="form-check">
                                    <input id="forensicsMode" class="form-check-input" type="checkbox">
                                    <label for="forensicsMode" class="form-check-label"><?= htmlspecialchars(t('create.forensics_label'), ENT_QUOTES, 'UTF-8') ?></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </details>

                <div class="d-grid">
                    <button id="submitBtn" type="submit" class="btn btn-primary"><?= htmlspecialchars(t('create.submit'), ENT_QUOTES, 'UTF-8') ?></button>
                </div>
                <p id="createStatus" class="small text-secondary mt-3 mb-0"></p>
            </form>
        </div>
    </div>

    <section id="resultBox" class="card pane bg-dark-subtle border-secondary-subtle shadow-sm mt-4 d-none">
        <div class="card-body p-4 text-center">
            <h2 class="h5 mb-3"><?= htmlspecialchars(t('create.result_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mb-2"><?= htmlspecialchars(t('create.tracking'), ENT_QUOTES, 'UTF-8') ?>: <strong id="trackingCodeResult" class="mono"></strong></p>
            <p class="mb-2"><a id="shareLink" class="link-box" href="#"></a></p>
            <div id="qrCode" class="qr-wrap border rounded bg-white p-3 d-inline-block"></div>
            <div class="mt-4">
                <button id="createAnotherBtn" type="button" class="btn btn-outline-primary"><?= htmlspecialchars(t('create.new_one'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </div>
    </section>
</main>
<script src="<?= htmlspecialchars(app_relative_url('assets/vendor/qrcode/qrcode.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
window.__createModuleLoaded = false;
(function () {
    const form = document.getElementById("createForm");
    const status = document.getElementById("createStatus");
    if (!form) {
        return;
    }
    form.addEventListener("submit", function (event) {
        if (window.__createModuleLoaded) {
            return;
        }
        event.preventDefault();
        if (status) {
            status.textContent = window.__I18N?.["js.create.script_load_failed"] || "Create script failed to load. Hard refresh (Ctrl+F5) and retry.";
        }
        alert(window.__I18N?.["js.create.script_load_failed"] || "Create script failed to load. Hard refresh (Ctrl+F5) and retry.");
    });
})();
</script>
<script>window.__APP_BASE = <?= json_encode(app_base_path(), JSON_UNESCAPED_SLASHES) ?>;</script>
<script>window.__I18N = <?= json_encode(i18n_messages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script>window.__APP_LANG = <?= json_encode(current_lang(), JSON_UNESCAPED_SLASHES) ?>;</script>
<script>
window.__ATTACHMENT_POLICY = <?= json_encode([
    'maxBytes' => ATTACHMENT_MAX_BYTES,
    'allowedExtensions' => attachment_allowed_extensions_list(),
    'raw' => ATTACHMENT_ALLOWED_EXTENSIONS,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script type="module" src="<?= htmlspecialchars(app_relative_url('assets/js/ui.js?v=20260327a'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script type="module" src="<?= htmlspecialchars(app_relative_url('assets/js/input-markdown.js?v=20260404a'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script type="module" src="<?= htmlspecialchars(app_relative_url('assets/js/create.js?v=20260327i'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(app_relative_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
