<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db-config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . app_url('install.php?error=method_not_allowed'));
    exit;
}

$dbHost = trim((string) ($_POST['db_host'] ?? ''));
$dbPort = (int) ($_POST['db_port'] ?? 0);
$dbName = trim((string) ($_POST['db_name'] ?? ''));
$dbUser = trim((string) ($_POST['db_user'] ?? ''));
$dbPass = (string) ($_POST['db_pass'] ?? '');

if ($dbHost === '' || $dbPort < 1 || $dbPort > 65535 || $dbName === '' || $dbUser === '') {
    header('Location: ' . app_url('install.php?error=invalid_input'));
    exit;
}

if (!test_db_connection($dbHost, $dbPort, $dbName, $dbUser, $dbPass)) {
    header('Location: ' . app_url('install.php?error=database_connection_failed'));
    exit;
}

if (!write_env_file($dbHost, $dbPort, $dbName, $dbUser, $dbPass)) {
    header('Location: ' . app_url('install.php?error=env_write_failed'));
    exit;
}

header('Location: ' . app_url('?setup=ok'));
exit;
