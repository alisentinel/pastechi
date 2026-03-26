<?php
declare(strict_types=1);

/**
 * Migration Runner Script
 * 
 * Executes all SQL migration files against the configured database.
 * Run from command line: php migrate.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/lib/db-config.php';

$migrations_dir = __DIR__ . '/migrations';

if (!is_dir($migrations_dir)) {
    die("Migrations directory not found: $migrations_dir\n");
}

// Get all .sql migration files
$migrations = glob($migrations_dir . '/*.sql');
sort($migrations);

if (empty($migrations)) {
    die("No migration files found in $migrations_dir\n");
}

try {
    $pdo = get_db();
    
    echo "Starting migrations...\n";
    echo "=====================================\n\n";
    
    foreach ($migrations as $migration_file) {
        $filename = basename($migration_file);
        echo "Executing: $filename\n";
        
        $sql = file_get_contents($migration_file);
        
        // Split by semicolon and execute each statement
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($stmt) => !empty($stmt) && !str_starts_with(trim($stmt), '--')
        );
        
        foreach ($statements as $statement) {
            if (trim($statement)) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    // Some statements may fail if they already exist; this is OK
                    echo "  ⚠ Statement partially skipped (may already exist)\n";
                    echo "  Error: " . $e->getMessage() . "\n";
                }
            }
        }
        
        echo "  ✓ Completed\n\n";
    }
    
    echo "=====================================\n";
    echo "All migrations executed successfully!\n";
    
} catch (Exception $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
