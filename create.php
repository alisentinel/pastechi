<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Paste · <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="text-light">
<main class="container py-5 app-wrap">
    <div class="card bg-dark-subtle border-secondary-subtle shadow-sm">
        <div class="card-body p-4 p-md-5">
            <h1 class="h3 mb-3">Create Paste</h1>

            <form id="createForm" action="#" method="post">
                <div class="mb-3">
                    <textarea id="content" class="form-control" placeholder="Paste content" required></textarea>
                </div>

                <details class="border rounded p-3 mb-3">
                    <summary class="fw-semibold">Advanced</summary>
                    <div class="pt-3">
                        <div class="row g-3">
                            <div class="col-12">
                                <input id="title" class="form-control" type="text" placeholder="Title (optional)">
                            </div>
                            <div class="col-12">
                                <input id="password" class="form-control" type="password" placeholder="Password (Optional + stronger encryption)">
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="ttlSeconds" class="form-label">Destroy after time</label>
                                <select id="ttlSeconds" class="form-select">
                                    <option value="600">10 minutes</option>
                                    <option value="1800">30 minutes</option>
                                    <option value="3600" selected>1 hour</option>
                                    <option value="21600">6 hours</option>
                                    <option value="43200">12 hours</option>
                                    <option value="86400">24 hours</option>
                                    <option value="172800">48 hours</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="maxViews" class="form-label">Destroy after views (0 = unlimited)</label>
                                <input id="maxViews" class="form-control" type="number" min="0" max="1000" step="1" value="0">
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input id="burnAfterRead" class="form-check-input" type="checkbox">
                                    <label for="burnAfterRead" class="form-check-label">Burn after read</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input id="useFragmentKey" class="form-check-input" type="checkbox">
                                    <label for="useFragmentKey" class="form-check-label">Use URL fragment key (recommended)</label>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="timeLock" class="form-label">Time-lock until</label>
                                <input id="timeLock" class="form-control" type="datetime-local">
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="bindingType" class="form-label">Link binding</label>
                                <select id="bindingType" class="form-select">
                                    <option value="none">None</option>
                                    <option value="ip">IP hash</option>
                                    <option value="fingerprint">Browser fingerprint hash</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input id="discussionMode" class="form-check-input" type="checkbox">
                                    <label for="discussionMode" class="form-check-label">Discussion mode (E2EE polling)</label>
                                </div>
                                <div class="form-check">
                                    <input id="forensicsMode" class="form-check-input" type="checkbox">
                                    <label for="forensicsMode" class="form-check-label">Forensics mode (aggregated views)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </details>

                <div class="d-grid">
                    <button id="submitBtn" type="submit" class="btn btn-primary">Encrypt & Create</button>
                </div>
                <p id="createStatus" class="small text-secondary mt-3 mb-0"></p>
            </form>
        </div>
    </div>

    <section id="resultBox" class="card bg-dark-subtle border-secondary-subtle shadow-sm mt-4 d-none">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">Paste ready</h2>
            <p class="mb-2">Tracking code: <strong id="trackingCodeResult" class="mono"></strong></p>
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
            status.textContent = "Create script failed to load. Hard refresh (Ctrl+F5) and retry.";
        }
        alert("Create script failed to load. Hard refresh (Ctrl+F5) and retry.");
    });
})();
</script>
<script>window.__APP_BASE = <?= json_encode(app_base_path(), JSON_UNESCAPED_SLASHES) ?>;</script>
<script type="module" src="<?= htmlspecialchars(app_url('assets/js/create.js?v=20260327e'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
