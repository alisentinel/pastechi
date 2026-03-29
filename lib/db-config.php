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
            if (function_exists('json_response')) {
                json_response(['ok' => false, 'error' => 'database_unavailable'], 503);
            }
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
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

function write_env_file(
    string $host,
    int $port,
    string $name,
    string $user,
    string $pass,
    string $appName,
    int $maxPayloadBytes,
    int $attachmentMaxBytes,
    string $attachmentAllowedExtensions
): bool
{
    $envPath = dirname(__DIR__) . '/.env';
    $safeAppName = str_replace(["\r", "\n"], ' ', trim($appName));
    $safeAttachmentExt = str_replace(["\r", "\n"], '', trim($attachmentAllowedExtensions));

    $content = "# PasteChi Configuration\n"
        . 'APP_NAME=' . $safeAppName . "\n\n"
        . "# Database Configuration\n"
        . 'DB_HOST=' . $host . "\n"
        . 'DB_PORT=' . $port . "\n"
        . 'DB_NAME=' . $name . "\n"
        . 'DB_USER=' . $user . "\n"
        . 'DB_PASS=' . $pass . "\n\n"
        . "# Payload & Attachment Policy\n"
        . 'MAX_PAYLOAD_BYTES=' . $maxPayloadBytes . "\n"
        . 'ATTACHMENT_MAX_BYTES=' . $attachmentMaxBytes . "\n"
        . 'ATTACHMENT_ALLOWED_EXTENSIONS=' . ($safeAttachmentExt === '' ? '*' : $safeAttachmentExt) . "\n";

    return file_put_contents($envPath, $content, LOCK_EX) !== false;
}

function db_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name LIMIT 1');
    $stmt->execute([
        ':table_name' => $table,
        ':column_name' => $column,
    ]);

    return (bool) $stmt->fetchColumn();
}

function db_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (db_column_exists($pdo, $table, $column)) {
        return;
    }

    $pdo->exec(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition));
}

function db_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name LIMIT 1');
    $stmt->execute([
        ':table_name' => $table,
    ]);

    return (bool) $stmt->fetchColumn();
}

function ensure_database_schema(): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $pdo = get_db();

    $pdo->exec("CREATE TABLE IF NOT EXISTS pastes (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(6) NOT NULL,
        codeHash VARCHAR(64) NOT NULL UNIQUE,
        ciphertext LONGTEXT NOT NULL,
        iv VARCHAR(256) NOT NULL,
        salt VARCHAR(256) NOT NULL,
        kdfIterations INT NOT NULL,
        createdAt BIGINT NOT NULL,
        expireAt BIGINT NOT NULL,
        views INT NOT NULL DEFAULT 0,
        maxViews INT NOT NULL DEFAULT 0,
        burnAfterRead BOOLEAN NOT NULL DEFAULT FALSE,
        uniqueViewsOnly BOOLEAN NOT NULL DEFAULT FALSE,
        lockUntil BIGINT NOT NULL DEFAULT 0,
        binding_type VARCHAR(32) NOT NULL DEFAULT 'none',
        binding_hash VARCHAR(256) NOT NULL DEFAULT '',
        modes_discussion BOOLEAN NOT NULL DEFAULT FALSE,
        modes_forensics BOOLEAN NOT NULL DEFAULT FALSE,
        discussion_salt VARCHAR(256) NOT NULL DEFAULT '',
        requires_fragment BOOLEAN NOT NULL DEFAULT FALSE,
        password_protected BOOLEAN NOT NULL DEFAULT TRUE,
        forensics_buckets JSON DEFAULT NULL,
        INSERT_TS TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UPDATE_TS TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY idx_codeHash (codeHash),
        INDEX idx_expires (expireAt),
        INDEX idx_created (createdAt)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS discussions (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        paste_codeHash VARCHAR(64) NOT NULL,
        message_ciphertext LONGTEXT NOT NULL,
        message_iv VARCHAR(256) NOT NULL,
        message_kdfIterations INT NOT NULL,
        createdAt BIGINT NOT NULL,
        INSERT_TS TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (paste_codeHash) REFERENCES pastes(codeHash) ON DELETE CASCADE,
        INDEX idx_paste_codeHash (paste_codeHash),
        INDEX idx_created (createdAt)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
        `key` VARCHAR(128) PRIMARY KEY,
        window_start BIGINT NOT NULL,
        count INT NOT NULL DEFAULT 0,
        INSERT_TS TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UPDATE_TS TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_window_start (window_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS logs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        ts BIGINT NOT NULL,
        level VARCHAR(16) NOT NULL,
        message VARCHAR(256) NOT NULL,
        path VARCHAR(256) NOT NULL DEFAULT '',
        context_json JSON NULL,
        INSERT_TS TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ts (ts),
        INDEX idx_level (level),
        INDEX idx_message (message)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db_add_column_if_missing($pdo, 'pastes', 'codeHash', 'VARCHAR(64) NULL');
    db_add_column_if_missing($pdo, 'pastes', 'uniqueViewsOnly', 'BOOLEAN NOT NULL DEFAULT FALSE');
    db_add_column_if_missing($pdo, 'pastes', 'lockUntil', 'BIGINT NOT NULL DEFAULT 0');
    db_add_column_if_missing($pdo, 'pastes', 'binding_type', "VARCHAR(32) NOT NULL DEFAULT 'none'");
    db_add_column_if_missing($pdo, 'pastes', 'binding_hash', "VARCHAR(256) NOT NULL DEFAULT ''");
    db_add_column_if_missing($pdo, 'pastes', 'modes_discussion', 'BOOLEAN NOT NULL DEFAULT FALSE');
    db_add_column_if_missing($pdo, 'pastes', 'modes_forensics', 'BOOLEAN NOT NULL DEFAULT FALSE');
    db_add_column_if_missing($pdo, 'pastes', 'discussion_salt', "VARCHAR(256) NOT NULL DEFAULT ''");
    db_add_column_if_missing($pdo, 'pastes', 'requires_fragment', 'BOOLEAN NOT NULL DEFAULT FALSE');
    db_add_column_if_missing($pdo, 'pastes', 'password_protected', 'BOOLEAN NOT NULL DEFAULT TRUE');
    db_add_column_if_missing($pdo, 'pastes', 'forensics_buckets', 'JSON DEFAULT NULL');

    $pdo->prepare("UPDATE pastes SET codeHash = SHA2(CONCAT(:pepper, '|code|', code), 256) WHERE codeHash IS NULL OR codeHash = ''")
        ->execute([':pepper' => (string) SERVER_PEPPER]);

    try {
        $pdo->exec('ALTER TABLE pastes ADD UNIQUE INDEX idx_codeHash (codeHash)');
    } catch (Throwable $e) {
        // Index may already exist.
    }

    if (db_column_exists($pdo, 'discussions', 'paste_code') && !db_column_exists($pdo, 'discussions', 'paste_codeHash')) {
        $pdo->exec('ALTER TABLE discussions ADD COLUMN paste_codeHash VARCHAR(64) NULL');
        $pdo->exec("UPDATE discussions d INNER JOIN pastes p ON p.code = d.paste_code SET d.paste_codeHash = p.codeHash WHERE d.paste_codeHash IS NULL OR d.paste_codeHash = ''");
        $pdo->exec('ALTER TABLE discussions MODIFY COLUMN paste_codeHash VARCHAR(64) NOT NULL');
        try {
            $pdo->exec('ALTER TABLE discussions MODIFY COLUMN paste_code VARCHAR(6) NULL');
        } catch (Throwable $e) {
            // ignore
        }
    }

    try {
        $pdo->exec('ALTER TABLE discussions ADD INDEX idx_paste_codeHash (paste_codeHash)');
    } catch (Throwable $e) {
        // Index may already exist.
    }

    if (db_table_exists($pdo, 'discussions')) {
        try {
            $pdo->exec('ALTER TABLE discussions ADD CONSTRAINT fk_discussions_codehash FOREIGN KEY (paste_codeHash) REFERENCES pastes(codeHash) ON DELETE CASCADE');
        } catch (Throwable $e) {
            // FK may already exist.
        }
    }

    $initialized = true;
}

function auto_migrate_if_possible(): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    if (db_setup_required() || !db_can_connect()) {
        return;
    }

    ensure_database_schema();
}
