#!/usr/bin/env php
<?php

/**
 * Database Migration Script
 *
 * Runs database migrations and optionally seeds data
 * Usage: php migrate.php [--seed] [--fresh]
 */

require_once __DIR__ . '/../vendor/autoload.php';

use VirPanel\Core\Application;
use VirPanel\Database\MigrationRunner;
use VirPanel\Database\Seeder;

$options = getopt('', ['seed', 'fresh', 'rollback::', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Database Migration Script

Usage: php migrate.php [OPTIONS]

Options:
  --seed           Run database seeders after migration
  --fresh          Drop all tables and re-run all migrations
  --rollback[=N]   Rollback last N migrations (default: 1)
  --help           Show this help message

Examples:
  php migrate.php
  php migrate.php --seed
  php migrate.php --fresh --seed
  php migrate.php --rollback=3

HELP;
    exit(0);
}

// Initialize application
try {
    echo "VirPanel Database Migration\n";
    echo str_repeat('=', 50) . "\n\n";

    $app = Application::getInstance(__DIR__ . '/..');
    $db = $app->getContainer()->get('database');

    $runner = new MigrationRunner($db);

    // Handle rollback
    if (isset($options['rollback'])) {
        $steps = is_string($options['rollback']) ? (int) $options['rollback'] : 1;
        echo "[INFO] Rolling back last {$steps} migration(s)...\n";

        $rolledBack = $runner->rollback($steps);

        foreach ($rolledBack as $migration) {
            echo "[SUCCESS] Rolled back: {$migration}\n";
        }

        echo "\n[SUCCESS] Rollback completed\n";
        exit(0);
    }

    // Handle fresh migration
    if (isset($options['fresh'])) {
        echo "[WARNING] This will drop all tables and re-run all migrations!\n";
        echo "Are you sure? (yes/no): ";

        $handle = fopen('php://stdin', 'r');
        $line = trim(fgets($handle));
        fclose($handle);

        if (strtolower($line) !== 'yes') {
            echo "[INFO] Fresh migration cancelled\n";
            exit(0);
        }

        echo "\n[INFO] Resetting database...\n";
        $rolledBack = $runner->reset();

        foreach ($rolledBack as $migration) {
            echo "[INFO] Rolled back: {$migration}\n";
        }
    }

    // Run migrations
    echo "[INFO] Running migrations...\n\n";

    $migrated = $runner->migrate();

    if (empty($migrated)) {
        echo "[INFO] Nothing to migrate\n";
    } else {
        foreach ($migrated as $migration) {
            echo "[SUCCESS] Migrated: {$migration}\n";
        }

        echo "\n[SUCCESS] Migrations completed successfully\n";
    }

    // Run seeders if requested
    if (isset($options['seed'])) {
        echo "\n[INFO] Running database seeders...\n";

        $seeder = new Seeder($db);
        $seeder->run();

        echo "[SUCCESS] Seeding completed\n";
    }

    exit(0);
} catch (\Throwable $e) {
    echo "\n[ERROR] Migration failed: " . $e->getMessage() . "\n";
    echo "[DEBUG] " . $e->getFile() . ':' . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
