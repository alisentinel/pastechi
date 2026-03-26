<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/utils.php';
require_once __DIR__ . '/../lib/db-config.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

ensure_storage();
cleanup_expired_files();

if (db_setup_required() || !db_can_connect()) {
    app_log('warn', 'db_unavailable_for_route');
}
