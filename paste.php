<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';

$code = (string) ($_GET['code'] ?? '');
if (!preg_match('/^[0-9]{6}$/', $code)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Paste <?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?> · <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body data-code="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" class="text-light">
<main class="container py-5 app-wrap">
    <h1 class="h4 mono mb-2"><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></h1>
    <p id="status" class="text-secondary">Loading encrypted payload…</p>

    <form id="decryptForm" class="card bg-dark-subtle border-secondary-subtle shadow-sm d-none mb-3">
        <div class="card-body">
            <div class="input-group">
                <input id="password" class="form-control" type="password" placeholder="Password (Optional + stronger encryption)">
                <button type="submit" class="btn btn-primary">Decrypt</button>
            </div>
        </div>
    </form>

    <section id="contentCard" class="card bg-dark-subtle border-secondary-subtle shadow-sm d-none mb-3">
        <div class="card-body">
            <h2 id="pasteTitle" class="h5">Untitled</h2>
            <div id="pasteOutput"></div>
        </div>
    </section>

    <section id="forensicsCard" class="card bg-dark-subtle border-secondary-subtle shadow-sm d-none mb-3">
        <div class="card-body">
            <h2 class="h6">Forensics</h2>
            <pre id="forensicsOutput" class="mb-0"></pre>
        </div>
    </section>

    <section id="discussionCard" class="card bg-dark-subtle border-secondary-subtle shadow-sm d-none">
        <div class="card-body">
            <h2 class="h6">Discussion</h2>
            <div id="discussionList" class="vstack gap-2 mb-3"></div>
            <form id="discussionForm" class="input-group">
                <input id="discussionInput" class="form-control" type="text" placeholder="Message" maxlength="2000">
                <button type="submit" class="btn btn-outline-light">Send</button>
            </form>
        </div>
    </section>
</main>
<script>window.__APP_BASE = <?= json_encode(app_base_path(), JSON_UNESCAPED_SLASHES) ?>;</script>
<script type="module" src="<?= htmlspecialchars(app_url('assets/js/view.js?v=20260327e'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
