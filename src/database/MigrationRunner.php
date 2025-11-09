<?php

namespace VirPanel\Database;

use Doctrine\DBAL\Connection;
use VirPanel\Database\Schema\Schema;

/**
 * Migration Runner
 *
 * Manages and executes database migrations
 */
class MigrationRunner
{
    /**
     * Database connection
     *
     * @var Connection
     */
    protected Connection $connection;

    /**
     * Migrations path
     *
     * @var string
     */
    protected string $migrationsPath;

    /**
     * Migrations table name
     *
     * @var string
     */
    protected string $migrationsTable = 'migrations';

    /**
     * Create a new migration runner instance
     *
     * @param Connection $connection
     * @param string|null $migrationsPath
     */
    public function __construct(Connection $connection, ?string $migrationsPath = null)
    {
        $this->connection = $connection;
        $this->migrationsPath = $migrationsPath ?? base_path('src/database/migrations');

        Schema::setConnection($connection);
    }

    /**
     * Run all pending migrations
     *
     * @return array
     */
    public function migrate(): array
    {
        $this->createMigrationsTable();

        $migrated = [];
        $migrations = $this->getPendingMigrations();

        foreach ($migrations as $migration) {
            $this->runMigration($migration);
            $migrated[] = $migration;
        }

        return $migrated;
    }

    /**
     * Rollback the last batch of migrations
     *
     * @param int $steps
     * @return array
     */
    public function rollback(int $steps = 1): array
    {
        $this->createMigrationsTable();

        $rolledBack = [];
        $batches = $this->getLastBatches($steps);

        foreach ($batches as $batch) {
            $migrations = $this->getMigrationsForBatch($batch);

            foreach (array_reverse($migrations) as $migration) {
                $this->rollbackMigration($migration);
                $rolledBack[] = $migration;
            }
        }

        return $rolledBack;
    }

    /**
     * Reset all migrations
     *
     * @return array
     */
    public function reset(): array
    {
        $this->createMigrationsTable();

        $rolledBack = [];
        $migrations = $this->getRanMigrations();

        foreach (array_reverse($migrations) as $migration) {
            $this->rollbackMigration($migration);
            $rolledBack[] = $migration;
        }

        return $rolledBack;
    }

    /**
     * Refresh all migrations
     *
     * @return array
     */
    public function refresh(): array
    {
        $this->reset();
        return $this->migrate();
    }

    /**
     * Get migration status
     *
     * @return array
     */
    public function status(): array
    {
        $this->createMigrationsTable();

        $ran = $this->getRanMigrations();
        $migrations = $this->getAllMigrations();
        $status = [];

        foreach ($migrations as $migration) {
            $status[] = [
                'migration' => $migration,
                'ran' => in_array($migration, $ran),
            ];
        }

        return $status;
    }

    /**
     * Run a single migration
     *
     * @param string $migration
     * @return void
     */
    protected function runMigration(string $migration): void
    {
        $file = $this->migrationsPath . DIRECTORY_SEPARATOR . $migration . '.php';

        if (!file_exists($file)) {
            logger("Migration file not found: $file");
            return;
        }

        $instance = require $file;

        if (!$instance instanceof Migration) {
            logger("Invalid migration: $migration");
            return;
        }

        try {
            $this->connection->beginTransaction();

            $instance->up();

            $this->recordMigration($migration);

            $this->connection->commit();

            logger("Migrated: $migration");
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            logger("Migration failed: $migration - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Rollback a single migration
     *
     * @param string $migration
     * @return void
     */
    protected function rollbackMigration(string $migration): void
    {
        $file = $this->migrationsPath . DIRECTORY_SEPARATOR . $migration . '.php';

        if (!file_exists($file)) {
            logger("Migration file not found: $file");
            return;
        }

        $instance = require $file;

        if (!$instance instanceof Migration) {
            logger("Invalid migration: $migration");
            return;
        }

        try {
            $this->connection->beginTransaction();

            $instance->down();

            $this->removeMigration($migration);

            $this->connection->commit();

            logger("Rolled back: $migration");
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            logger("Rollback failed: $migration - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all pending migrations
     *
     * @return array
     */
    protected function getPendingMigrations(): array
    {
        $ran = $this->getRanMigrations();
        $all = $this->getAllMigrations();

        return array_diff($all, $ran);
    }

    /**
     * Get all ran migrations
     *
     * @return array
     */
    protected function getRanMigrations(): array
    {
        $prefix = config('database.prefix', 'vp_');
        $table = $prefix . $this->migrationsTable;

        $result = $this->connection->fetchAllAssociative(
            "SELECT migration FROM {$table} ORDER BY id ASC"
        );

        return array_column($result, 'migration');
    }

    /**
     * Get all migration files
     *
     * @return array
     */
    protected function getAllMigrations(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = scandir($this->migrationsPath);
        $migrations = [];

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $migrations[] = pathinfo($file, PATHINFO_FILENAME);
            }
        }

        sort($migrations);

        return $migrations;
    }

    /**
     * Get last N batches
     *
     * @param int $steps
     * @return array
     */
    protected function getLastBatches(int $steps): array
    {
        $prefix = config('database.prefix', 'vp_');
        $table = $prefix . $this->migrationsTable;

        $result = $this->connection->fetchAllAssociative(
            "SELECT DISTINCT batch FROM {$table} ORDER BY batch DESC LIMIT ?",
            [$steps]
        );

        return array_column($result, 'batch');
    }

    /**
     * Get migrations for a specific batch
     *
     * @param int $batch
     * @return array
     */
    protected function getMigrationsForBatch(int $batch): array
    {
        $prefix = config('database.prefix', 'vp_');
        $table = $prefix . $this->migrationsTable;

        $result = $this->connection->fetchAllAssociative(
            "SELECT migration FROM {$table} WHERE batch = ? ORDER BY id ASC",
            [$batch]
        );

        return array_column($result, 'migration');
    }

    /**
     * Record a migration as ran
     *
     * @param string $migration
     * @return void
     */
    protected function recordMigration(string $migration): void
    {
        $prefix = config('database.prefix', 'vp_');
        $table = $prefix . $this->migrationsTable;

        $batch = $this->getNextBatchNumber();

        $this->connection->insert($table, [
            'migration' => $migration,
            'batch' => $batch,
        ]);
    }

    /**
     * Remove a migration record
     *
     * @param string $migration
     * @return void
     */
    protected function removeMigration(string $migration): void
    {
        $prefix = config('database.prefix', 'vp_');
        $table = $prefix . $this->migrationsTable;

        $this->connection->delete($table, ['migration' => $migration]);
    }

    /**
     * Get the next batch number
     *
     * @return int
     */
    protected function getNextBatchNumber(): int
    {
        $prefix = config('database.prefix', 'vp_');
        $table = $prefix . $this->migrationsTable;

        $result = $this->connection->fetchOne(
            "SELECT MAX(batch) FROM {$table}"
        );

        return ((int) $result) + 1;
    }

    /**
     * Create the migrations table if it doesn't exist
     *
     * @return void
     */
    protected function createMigrationsTable(): void
    {
        $prefix = config('database.prefix', 'vp_');
        $table = $prefix . $this->migrationsTable;

        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([$table])) {
            $this->connection->executeStatement("
                CREATE TABLE {$table} (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    batch INT NOT NULL
                )
            ");
        }
    }
}
