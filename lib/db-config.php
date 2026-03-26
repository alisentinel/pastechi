<?php
declare(strict_types=1);

/**
 * Database Configuration
 * Loads .env and .env.local values.
 */

function load_env_file(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");
        if ($key !== '') {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

$envPath = dirname(__DIR__) . '/.env';
$envLocalPath = dirname(__DIR__) . '/.env.local';
load_env_file($envPath);
load_env_file($envLocalPath);

// Database type: 'mysql', 'sqlite'
define('DB_TYPE', getenv('DB_TYPE') ?: 'mysql');

// MySQL/MariaDB Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'pastechi');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Connection Timeout
define('DB_TIMEOUT', 5);

// PDO DSN construction
function get_pdo_dsn(): string {
    if (DB_TYPE === 'mysql') {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );
    }
    throw new RuntimeException('Unsupported DB_TYPE: ' . DB_TYPE);
}

// PDO Options
function get_pdo_options(): array {
    return [
        PDO::ATTR_TIMEOUT => DB_TIMEOUT,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
}

/**
 * Get or create database connection
 */
function get_db(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                get_pdo_dsn(),
                DB_USER,
                DB_PASS,
                get_pdo_options()
            );
        } catch (PDOException $e) {
            app_log('error', 'db_connection_failed', [
                'host' => DB_HOST,
                'database' => DB_NAME,
                'error' => $e->getMessage(),
            ]);
            json_response(['ok' => false, 'error' => 'database_unavailable'], 503);
        }
    }
    
    return $pdo;
}

function db_setup_required(): bool
{
    $required = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER'];
    foreach ($required as $name) {
        $value = getenv($name);
        if (!is_string($value) || trim($value) === '') {
            return true;
        }
    }

    return false;
}

function db_can_connect(): bool
{
    if (db_setup_required()) {
        return false;
    }

    try {
        $pdo = new PDO(get_pdo_dsn(), DB_USER, DB_PASS, get_pdo_options());
        $pdo->query('SELECT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function test_db_connection(string $host, int $port, string $name, string $user, string $pass): bool
{
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, DB_CHARSET);
        $pdo = new PDO($dsn, $user, $pass, get_pdo_options());
        $pdo->query('SELECT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function write_env_file(string $host, int $port, string $name, string $user, string $pass): bool
{
    $envPath = dirname(__DIR__) . '/.env';
    $content = "# Database Configuration\n"
        . 'DB_HOST=' . $host . "\n"
        . 'DB_PORT=' . $port . "\n"
        . 'DB_NAME=' . $name . "\n"
        . 'DB_USER=' . $user . "\n"
        . 'DB_PASS=' . $pass . "\n";

    return file_put_contents($envPath, $content, LOCK_EX) !== false;
}
