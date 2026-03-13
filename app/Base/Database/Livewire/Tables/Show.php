<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Database\Livewire\Tables;

use App\Base\Database\Services\TableInspector;
use App\Base\Foundation\Livewire\Concerns\ResetsPaginationOnSearch;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Generic table viewer — displays contents of any registered database table.
 *
 * Read-only. Supports search across string/text columns, sortable column
 * headers, and pagination. Table must exist in the TableRegistry.
 */
class Show extends Component
{
    use ResetsPaginationOnSearch;
    use WithPagination;

    public string $tableName = '';

    public string $search = '';

    public string $sortColumn = '';

    public string $sortDirection = 'asc';

    /**
     * Initialize with the table name from the route parameter.
     *
     * Aborts with 404 if the table is not in the registry.
     */
    public function mount(string $tableName): void
    {
        $inspector = app(TableInspector::class);

        if (! $inspector->isRegistered($tableName)) {
            abort(404);
        }

        $this->tableName = $tableName;
    }

    /**
     * Toggle sort on a column. Clicking the same column flips direction.
     */
    public function sort(string $column): void
    {
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    /**
     * Format a cell value for display.
     *
     * Handles nulls, booleans, long strings, and JSON.
     */
    public function formatCell(mixed $value, string $typeName): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value) || $typeName === 'bool' || $typeName === 'boolean') {
            return $value ? '✓' : '✗';
        }

        $stringValue = (string) $value;

        if (mb_strlen($stringValue) > 120) {
            return mb_substr($stringValue, 0, 120).'…';
        }

        return $stringValue;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $inspector = app(TableInspector::class);
        $columns = $inspector->columns($this->tableName);
        $rowCount = $inspector->rowCount($this->tableName);

        return view('livewire.admin.system.tables.show', [
            'columns' => $columns,
            'rows' => $inspector->rows(
                $this->tableName,
                $this->search ?: null,
                $this->sortColumn ?: null,
                $this->sortDirection,
            ),
            'rowCount' => $rowCount,
        ]);
    }
}
