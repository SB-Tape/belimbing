<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Database\Services;

use App\Base\Database\Models\TableRegistry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only inspector for any registered database table.
 *
 * Provides column metadata and paginated row access via Laravel's
 * Schema Builder and Query Builder — fully DB-agnostic.
 */
class TableInspector
{
    /**
     * Column types considered searchable (LIKE-compatible).
     *
     * @var list<string>
     */
    private const SEARCHABLE_TYPE_PATTERNS = [
        'char',
        'text',
        'varchar',
        'string',
    ];

    /**
     * Get column metadata for a table.
     *
     * @return list<array{name: string, type_name: string, type: string, nullable: bool, default: mixed, auto_increment: bool}>
     */
    public function columns(string $table): array
    {
        return Schema::getColumns($table);
    }

    /**
     * Get paginated rows with optional search and sort.
     *
     * @param  string  $table  Table name
     * @param  string|null  $search  Search term (LIKE across string columns)
     * @param  string|null  $sortColumn  Column to sort by (must exist in table)
     * @param  string  $sortDirection  'asc' or 'desc'
     * @param  int  $perPage  Rows per page
     */
    public function rows(
        string $table,
        ?string $search,
        ?string $sortColumn,
        string $sortDirection = 'asc',
        int $perPage = 25,
    ): LengthAwarePaginator {
        $query = DB::table($table);

        if ($search !== null && $search !== '') {
            $searchable = $this->searchableColumns($table);

            if ($searchable !== []) {
                $query->where(function ($q) use ($searchable, $search): void {
                    foreach ($searchable as $index => $column) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $q->{$method}($column, 'like', '%'.$search.'%');
                    }
                });
            }
        }

        if ($sortColumn !== null && $sortColumn !== '' && $this->columnExists($table, $sortColumn)) {
            $query->orderBy($sortColumn, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get total row count for a table.
     */
    public function rowCount(string $table): int
    {
        return DB::table($table)->count();
    }

    /**
     * Get column names that are searchable (string/text types).
     *
     * @return list<string>
     */
    public function searchableColumns(string $table): array
    {
        $columns = $this->columns($table);
        $searchable = [];

        foreach ($columns as $column) {
            $typeName = strtolower($column['type_name']);

            foreach (self::SEARCHABLE_TYPE_PATTERNS as $pattern) {
                if (str_contains($typeName, $pattern)) {
                    $searchable[] = $column['name'];
                    break;
                }
            }
        }

        return $searchable;
    }

    /**
     * Check if a table is registered in the TableRegistry.
     */
    public function isRegistered(string $table): bool
    {
        return TableRegistry::query()->where('table_name', $table)->exists();
    }

    /**
     * Check if a column exists in a table.
     */
    private function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }
}
