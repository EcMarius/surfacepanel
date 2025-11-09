<?php

namespace VirPanel\Database\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\SchemaException;

/**
 * Schema Builder
 *
 * Provides a fluent interface for creating and modifying database tables
 */
class Schema
{
    /**
     * Database connection instance
     *
     * @var Connection
     */
    protected static Connection $connection;

    /**
     * Set the database connection
     *
     * @param Connection $connection
     * @return void
     */
    public static function setConnection(Connection $connection): void
    {
        static::$connection = $connection;
    }

    /**
     * Get the database connection
     *
     * @return Connection
     */
    public static function getConnection(): Connection
    {
        return static::$connection;
    }

    /**
     * Create a new table
     *
     * @param string $table
     * @param \Closure $callback
     * @return void
     */
    public static function create(string $table, \Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $schemaManager = static::$connection->createSchemaManager();
        $schema = $schemaManager->createSchema();

        $newTable = $schema->createTable(static::getTableName($table));

        foreach ($blueprint->getColumns() as $column) {
            $newTable->addColumn(
                $column['name'],
                $column['type'],
                $column['options']
            );
        }

        // Add primary key
        if ($primaryKey = $blueprint->getPrimaryKey()) {
            $newTable->setPrimaryKey($primaryKey);
        }

        // Add indexes
        foreach ($blueprint->getIndexes() as $index) {
            if ($index['unique']) {
                $newTable->addUniqueIndex($index['columns'], $index['name']);
            } else {
                $newTable->addIndex($index['columns'], $index['name']);
            }
        }

        // Add foreign keys
        foreach ($blueprint->getForeignKeys() as $foreignKey) {
            $newTable->addForeignKeyConstraint(
                static::getTableName($foreignKey['foreignTable']),
                $foreignKey['localColumns'],
                $foreignKey['foreignColumns'],
                $foreignKey['options'],
                $foreignKey['name']
            );
        }

        $queries = $schema->getMigrateToSql($schemaManager->createSchema(), static::$connection->getDatabasePlatform());

        foreach ($queries as $query) {
            static::$connection->executeStatement($query);
        }
    }

    /**
     * Modify an existing table
     *
     * @param string $table
     * @param \Closure $callback
     * @return void
     */
    public static function table(string $table, \Closure $callback): void
    {
        $blueprint = new Blueprint($table, true);
        $callback($blueprint);

        $schemaManager = static::$connection->createSchemaManager();
        $schema = $schemaManager->createSchema();

        $tableObject = $schema->getTable(static::getTableName($table));

        // Add new columns
        foreach ($blueprint->getColumns() as $column) {
            if (!$tableObject->hasColumn($column['name'])) {
                $tableObject->addColumn(
                    $column['name'],
                    $column['type'],
                    $column['options']
                );
            }
        }

        // Add indexes
        foreach ($blueprint->getIndexes() as $index) {
            if ($index['unique']) {
                $tableObject->addUniqueIndex($index['columns'], $index['name']);
            } else {
                $tableObject->addIndex($index['columns'], $index['name']);
            }
        }

        // Add foreign keys
        foreach ($blueprint->getForeignKeys() as $foreignKey) {
            $tableObject->addForeignKeyConstraint(
                static::getTableName($foreignKey['foreignTable']),
                $foreignKey['localColumns'],
                $foreignKey['foreignColumns'],
                $foreignKey['options'],
                $foreignKey['name']
            );
        }

        $queries = $schema->getMigrateToSql($schemaManager->createSchema(), static::$connection->getDatabasePlatform());

        foreach ($queries as $query) {
            static::$connection->executeStatement($query);
        }
    }

    /**
     * Drop a table if it exists
     *
     * @param string $table
     * @return void
     */
    public static function dropIfExists(string $table): void
    {
        $schemaManager = static::$connection->createSchemaManager();

        if ($schemaManager->tablesExist([static::getTableName($table)])) {
            $schemaManager->dropTable(static::getTableName($table));
        }
    }

    /**
     * Drop a table
     *
     * @param string $table
     * @return void
     */
    public static function drop(string $table): void
    {
        $schemaManager = static::$connection->createSchemaManager();
        $schemaManager->dropTable(static::getTableName($table));
    }

    /**
     * Check if a table exists
     *
     * @param string $table
     * @return bool
     */
    public static function hasTable(string $table): bool
    {
        $schemaManager = static::$connection->createSchemaManager();
        return $schemaManager->tablesExist([static::getTableName($table)]);
    }

    /**
     * Check if a column exists in a table
     *
     * @param string $table
     * @param string $column
     * @return bool
     */
    public static function hasColumn(string $table, string $column): bool
    {
        $schemaManager = static::$connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns(static::getTableName($table));

        return isset($columns[$column]);
    }

    /**
     * Rename a table
     *
     * @param string $from
     * @param string $to
     * @return void
     */
    public static function rename(string $from, string $to): void
    {
        $schemaManager = static::$connection->createSchemaManager();
        $schemaManager->renameTable(static::getTableName($from), static::getTableName($to));
    }

    /**
     * Get the full table name with prefix
     *
     * @param string $table
     * @return string
     */
    protected static function getTableName(string $table): string
    {
        $prefix = config('database.prefix', 'vp_');
        return $prefix . $table;
    }
}
