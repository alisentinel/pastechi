<?php
declare(strict_types=1);

/**
 * Render the shared top navigation with the same controls on every page.
 */
function render_app_navbar(): void
{
    $brandHref = app_lang_url('index.php');
    $langBasePath = basename((string) ($_SERVER['PHP_SELF'] ?? 'index.php'));
    $langQuery = $_GET;
    if (!is_array($langQuery)) {
        $langQuery = [];
    }
    unset($langQuery['lang']);

    $langEn = app_lang_url($langBasePath, array_merge($langQuery, ['lang' => 'en']));
    $langFa = app_lang_url($langBasePath, array_merge($langQuery, ['lang' => 'fa']));
    $createHref = app_lang_url('create.php');

    ?>
    <div class="app-nav">
        <div class="app-nav-start">
            <a class="text-decoration-none app-brand"
                href="<?= htmlspecialchars($brandHref, ENT_QUOTES, 'UTF-8') ?>"><?= APP_NAME ?></a>
            <a class="btn btn-sm btn-outline-light" href="<?= htmlspecialchars($createHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('home.create_button'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <div class="app-nav-controls">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="langDropdown"
                    data-bs-toggle="dropdown" aria-expanded="false">
                    <?= htmlspecialchars(t('ui.lang'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <ul class="dropdown-menu" aria-labelledby="langDropdown">
                    <li><a class="dropdown-item" href="<?= htmlspecialchars($langEn, ENT_QUOTES, 'UTF-8') ?>">English</a>
                    </li>
                    <li><a class="dropdown-item" href="<?= htmlspecialchars($langFa, ENT_QUOTES, 'UTF-8') ?>">فارسی</a></li>
                </ul>
            </div>
            <a class="btn btn-sm btn-outline-secondary"
                href="<?= htmlspecialchars(app_lang_url('documents.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('nav.documents'), ENT_QUOTES, 'UTF-8') ?></a>
            <a class="btn btn-sm btn-outline-secondary"
                href="<?= htmlspecialchars(app_lang_url('privacy.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('nav.privacy'), ENT_QUOTES, 'UTF-8') ?></a>
            <a class="btn btn-sm btn-outline-secondary"
                href="<?= htmlspecialchars(app_lang_url('mirror.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('nav.mirror'), ENT_QUOTES, 'UTF-8') ?></a>
            <button id="themeToggle" type="button" class="btn btn-sm btn-outline-secondary"></button>
        </div>
    </div>
    <?php
}