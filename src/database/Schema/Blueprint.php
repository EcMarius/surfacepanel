<?php

namespace VirPanel\Database\Schema;

use Doctrine\DBAL\Types\Types;

/**
 * Schema Blueprint
 *
 * Provides a fluent interface for defining table structures
 */
class Blueprint
{
    /**
     * The table name
     *
     * @var string
     */
    protected string $table;

    /**
     * Whether this is modifying an existing table
     *
     * @var bool
     */
    protected bool $modifying = false;

    /**
     * The columns to add
     *
     * @var array
     */
    protected array $columns = [];

    /**
     * The primary key columns
     *
     * @var array
     */
    protected array $primaryKey = [];

    /**
     * The indexes to add
     *
     * @var array
     */
    protected array $indexes = [];

    /**
     * The foreign keys to add
     *
     * @var array
     */
    protected array $foreignKeys = [];

    /**
     * Create a new blueprint instance
     *
     * @param string $table
     * @param bool $modifying
     */
    public function __construct(string $table, bool $modifying = false)
    {
        $this->table = $table;
        $this->modifying = $modifying;
    }

    /**
     * Add an auto-incrementing ID column
     *
     * @param string $column
     * @return $this
     */
    public function id(string $column = 'id'): static
    {
        $this->columns[] = [
            'name' => $column,
            'type' => Types::BIGINT,
            'options' => [
                'unsigned' => true,
                'autoincrement' => true,
                'notnull' => true,
            ]
        ];

        $this->primaryKey[] = $column;

        return $this;
    }

    /**
     * Add a foreign ID column
     *
     * @param string $column
     * @return ForeignKeyDefinition
     */
    public function foreignId(string $column): ForeignKeyDefinition
    {
        $this->columns[] = [
            'name' => $column,
            'type' => Types::BIGINT,
            'options' => [
                'unsigned' => true,
                'notnull' => false,
            ]
        ];

        return new ForeignKeyDefinition($this, $column);
    }

    /**
     * Add a string column
     *
     * @param string $column
     * @param int $length
     * @return ColumnDefinition
     */
    public function string(string $column, int $length = 255): ColumnDefinition
    {
        $this->columns[] = [
            'name' => $column,
            'type' => Types::STRING,
            'options' => [
                'length' => $length,
                'notnull' => false,
            ]
        ];

        return new ColumnDefinition($this, $column);
    }

    /**
     * Add a text column
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function text(string $column): ColumnDefinition
    {
        $this->columns[] = [
            'name' => $column,
            'type' => Types::TEXT,
            'options' => [
                'notnull' => false,
            ]
        ];

        return new ColumnDefinition($this, $column);
    }

    /**
     * Add an integer column
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function integer(string $column): ColumnDefinition
    {
        $this->columns[] = [
            'name' => $column,
            'type' => Types::INTEGER,
            'options' => [
                'notnull' => false,
            ]
        ];

        return new ColumnDefinition($this, $column);
    }

    /**
     * Add a big integer column
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function bigInteger(string $column): ColumnDefinition
    {
        $this->columns[] = [
            'name' => $column,
            'type' => Types::BIGINT,
            'options' => [
                'notnull' => false,
            ]
        ];

        return new ColumnDefinition($this, $column);
    }

    /**
     * Add an unsigned big integer column
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function unsignedBigInteger(string $column): ColumnDefinition
    {
        $this->columns[] = [
            'name' => $column,
            'type' => Types::BIGINT,
            'options' => [
                'unsigned' => true,
                'notnull' => false,
            ]
        ];

        return new ColumnDefinition($this, $column);
    }

    /**
     * Add an unsigned tiny integer column
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function unsignedTinyInteger(string $column): ColumnDefinition
    {
        $this->columns[] = [
            'name' => $column,
            'type' => Types::SMALLINT,
            'options' => [
                'unsigned' => true,
                'notnull' => false,
            ]
        ];

        return new ColumnDefinition($this, $column);
    }

    /**
     * Add a decimal column
     *
     * @param string $column
     * @param int $precision
     * @param int $scale
     * @return ColumnDefinition
     */
    public function decimal(string $column, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        $this->columns[] = [
            'name' => $column,
            'type' => Types::DECIMAL,
            'options' => [
                'precision' => $precision,
                'scale' => $scale,
                'notnull' => false,
            ]
        ];

        return new ColumnDefinition($this, $column);
    }

    /**
     * Add a boolean column
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function boolean(string $column): ColumnDefinition
    {
        $this->columns[] = [
            'name' => $column,
            'type' => Types::BOOLEAN,
            'options' => [
                'notnull' => false,
            ]
        ];

        return new ColumnDefinition($this, $column);
    }

    /**
     * Add an enum column
     *
     * @param string $column
     * @param array $allowed
     * @return ColumnDefinition
     */
    public function enum(string $column, array $allowed): ColumnDefinition
    {
        $this->columns[] = [
            'name' => $column,
            'type' => Types::STRING,
            'options' => [
                'length' => 255,
                'notnull' => false,
                'comment' => 'ENUM: ' . implode(',', $allowed),
            ]
        ];

        return new ColumnDefinition($this, $column);
    }

    /**
     * Add a JSON column
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function json(string $column): ColumnDefinition
    {
        $this->columns[] = [
            'name' => $column,
            'type' => Types::JSON,
            'options' => [
                'notnull' => false,
            ]
        ];

        return new ColumnDefinition($this, $column);
    }

    /**
     * Add a timestamp column
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function timestamp(string $column): ColumnDefinition
    {
        $this->columns[] = [
            'name' => $column,
            'type' => Types::DATETIME_MUTABLE,
            'options' => [
                'notnull' => false,
            ]
        ];

        return new ColumnDefinition($this, $column);
    }

    /**
     * Add created_at and updated_at timestamp columns
     *
     * @return void
     */
    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    /**
     * Add a soft delete timestamp column
     *
     * @return void
     */
    public function softDeletes(): void
    {
        $this->timestamp('deleted_at')->nullable();
    }

    /**
     * Add an index to the table
     *
     * @param string|array $columns
     * @param string|null $name
     * @return void
     */
    public function index(string|array $columns, ?string $name = null): void
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?? $this->createIndexName('index', $columns);

        $this->indexes[] = [
            'columns' => $columns,
            'name' => $name,
            'unique' => false,
        ];
    }

    /**
     * Add a unique index to the table
     *
     * @param string|array $columns
     * @param string|null $name
     * @return void
     */
    public function unique(string|array $columns, ?string $name = null): void
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?? $this->createIndexName('unique', $columns);

        $this->indexes[] = [
            'columns' => $columns,
            'name' => $name,
            'unique' => true,
        ];
    }

    /**
     * Add a foreign key to the table
     *
     * @param string $column
     * @param string $foreignTable
     * @param string $foreignColumn
     * @param array $options
     * @return void
     */
    public function addForeignKey(string $column, string $foreignTable, string $foreignColumn = 'id', array $options = []): void
    {
        $name = $this->table . '_' . $column . '_foreign';

        $this->foreignKeys[] = [
            'name' => $name,
            'localColumns' => [$column],
            'foreignTable' => $foreignTable,
            'foreignColumns' => [$foreignColumn],
            'options' => $options,
        ];
    }

    /**
     * Create an index name
     *
     * @param string $type
     * @param array $columns
     * @return string
     */
    protected function createIndexName(string $type, array $columns): string
    {
        $index = strtolower($this->table . '_' . implode('_', $columns) . '_' . $type);
        return str_replace(['-', '.'], '_', $index);
    }

    /**
     * Get the columns
     *
     * @return array
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get the primary key
     *
     * @return array
     */
    public function getPrimaryKey(): array
    {
        return $this->primaryKey;
    }

    /**
     * Get the indexes
     *
     * @return array
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    /**
     * Get the foreign keys
     *
     * @return array
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    /**
     * Get a column by name
     *
     * @param string $name
     * @return array|null
     */
    public function getColumn(string $name): ?array
    {
        foreach ($this->columns as $index => $column) {
            if ($column['name'] === $name) {
                return ['index' => $index, 'column' => $column];
            }
        }

        return null;
    }

    /**
     * Update a column option
     *
     * @param string $column
     * @param string $option
     * @param mixed $value
     * @return void
     */
    public function updateColumnOption(string $column, string $option, mixed $value): void
    {
        $col = $this->getColumn($column);

        if ($col) {
            $this->columns[$col['index']]['options'][$option] = $value;
        }
    }
}

/**
 * Column Definition Helper
 */
class ColumnDefinition
{
    protected Blueprint $blueprint;
    protected string $column;

    public function __construct(Blueprint $blueprint, string $column)
    {
        $this->blueprint = $blueprint;
        $this->column = $column;
    }

    /**
     * Make the column nullable
     *
     * @return $this
     */
    public function nullable(): static
    {
        $this->blueprint->updateColumnOption($this->column, 'notnull', false);
        return $this;
    }

    /**
     * Set a default value
     *
     * @param mixed $value
     * @return $this
     */
    public function default(mixed $value): static
    {
        $this->blueprint->updateColumnOption($this->column, 'default', $value);
        return $this;
    }

    /**
     * Make the column unique
     *
     * @return $this
     */
    public function unique(): static
    {
        $this->blueprint->unique($this->column);
        return $this;
    }

    /**
     * Add an index to the column
     *
     * @return $this
     */
    public function index(): static
    {
        $this->blueprint->index($this->column);
        return $this;
    }

    /**
     * Use current timestamp as default
     *
     * @return $this
     */
    public function useCurrent(): static
    {
        $this->blueprint->updateColumnOption($this->column, 'default', 'CURRENT_TIMESTAMP');
        return $this;
    }
}

/**
 * Foreign Key Definition Helper
 */
class ForeignKeyDefinition extends ColumnDefinition
{
    /**
     * Reference a foreign table
     *
     * @param string|null $table
     * @param string $column
     * @return $this
     */
    public function constrained(?string $table = null, string $column = 'id'): static
    {
        if ($table === null) {
            // Infer table name from column name (e.g., user_id -> users)
            $table = str_replace('_id', '', $this->column);
            if (!str_ends_with($table, 's')) {
                $table .= 's';
            }
        }

        $this->blueprint->addForeignKey($this->column, $table, $column);
        return $this;
    }

    /**
     * Set cascade on delete
     *
     * @return $this
     */
    public function cascadeOnDelete(): static
    {
        $foreignKeys = $this->blueprint->getForeignKeys();
        $lastIndex = count($foreignKeys) - 1;

        if ($lastIndex >= 0) {
            $foreignKeys[$lastIndex]['options']['onDelete'] = 'CASCADE';
            // Update the blueprint (this is a workaround, ideally we'd have a setter)
        }

        return $this;
    }

    /**
     * Set null on delete
     *
     * @return $this
     */
    public function nullOnDelete(): static
    {
        $foreignKeys = $this->blueprint->getForeignKeys();
        $lastIndex = count($foreignKeys) - 1;

        if ($lastIndex >= 0) {
            $foreignKeys[$lastIndex]['options']['onDelete'] = 'SET NULL';
        }

        return $this;
    }
}
