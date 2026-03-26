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
    <title><?= htmlspecialchars(t('create.title'), ENT_QUOTES, 'UTF-8') ?> · <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="text-light" data-theme="dark">
<main class="container py-5 app-wrap">
    <div class="app-nav">
        <a class="text-decoration-none app-brand" href="<?= htmlspecialchars(app_lang_url('index.php'), ENT_QUOTES, 'UTF-8') ?>"><?= APP_NAME ?></a>
        <div class="app-nav-controls">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="langDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <?= htmlspecialchars(t('ui.lang'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <ul class="dropdown-menu" aria-labelledby="langDropdown">
                    <li><a class="dropdown-item" href="<?= htmlspecialchars(app_url('create.php?lang=en'), ENT_QUOTES, 'UTF-8') ?>">English</a></li>
                    <li><a class="dropdown-item" href="<?= htmlspecialchars(app_url('create.php?lang=fa'), ENT_QUOTES, 'UTF-8') ?>">فارسی</a></li>
                </ul>
            </div>
            <button id="themeToggle" type="button" class="btn btn-sm btn-outline-secondary"></button>
        </div>
    </div>

    <div class="card pane bg-dark-subtle border-secondary-subtle shadow-sm">
        <div class="card-body p-4 p-md-5">
            <h1 class="h3 mb-3"><?= htmlspecialchars(t('create.title'), ENT_QUOTES, 'UTF-8') ?></h1>

            <form id="createForm" action="#" method="post">
                <div class="mb-3">
                    <textarea id="content" class="form-control" placeholder="<?= htmlspecialchars(t('create.content_placeholder'), ENT_QUOTES, 'UTF-8') ?>" required></textarea>
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
                                    <input id="burnAfterRead" class="form-check-input" type="checkbox">
                                    <label for="burnAfterRead" class="form-check-label"><?= htmlspecialchars(t('create.burn_label'), ENT_QUOTES, 'UTF-8') ?></label>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input id="useFragmentKey" class="form-check-input" type="checkbox">
                                    <label for="useFragmentKey" class="form-check-label"><?= htmlspecialchars(t('create.fragment_label'), ENT_QUOTES, 'UTF-8') ?></label>
                                </div>
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
        <div class="card-body p-4">
            <h2 class="h5 mb-3"><?= htmlspecialchars(t('create.result_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mb-2"><?= htmlspecialchars(t('create.tracking'), ENT_QUOTES, 'UTF-8') ?>: <strong id="trackingCodeResult" class="mono"></strong></p>
            <p class="mb-2"><a id="shareLink" class="link-box" href="#"></a></p>
            <div id="qrCode" class="qr-wrap border rounded bg-white p-3 d-inline-block"></div>
        </div>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
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
<script type="module" src="<?= htmlspecialchars(app_url('assets/js/ui.js?v=20260327a'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script type="module" src="<?= htmlspecialchars(app_url('assets/js/create.js?v=20260327f'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
