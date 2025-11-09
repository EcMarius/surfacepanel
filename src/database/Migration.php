<?php

namespace VirPanel\Database;

/**
 * Base Migration Class
 *
 * All migrations must extend this class
 */
abstract class Migration
{
    /**
     * Run the migration
     *
     * @return void
     */
    abstract public function up(): void;

    /**
     * Reverse the migration
     *
     * @return void
     */
    abstract public function down(): void;

    /**
     * Get the migration name
     *
     * @return string
     */
    public function getName(): string
    {
        return static::class;
    }
}
