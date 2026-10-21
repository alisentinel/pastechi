<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/lib/utils.php';

cleanup_expired_files();
echo "cleanup complete\n";
