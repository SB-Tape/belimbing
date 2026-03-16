<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Database\Livewire\DatabaseTables;

use App\Base\Database\Models\TableRegistry;
use App\Base\Foundation\Livewire\Concerns\ResetsPaginationOnSearch;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResetsPaginationOnSearch;
    use WithPagination;

    public string $search = '';

    /**
     * Toggle the stability flag for a table.
     */
    public function toggleStability(int $id): void
    {
        $table = TableRegistry::query()->findOrFail($id);

        if ($table->isStable()) {
            $table->markUnstable();
        } else {
            $table->markStable(Auth::id());
        }
    }

    /**
     * Map stability to a badge variant.
     */
    public function stabilityVariant(bool $isStable): string
    {
        return $isStable ? 'success' : 'default';
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.admin.system.database-tables.index', [
            'tables' => TableRegistry::query()
                ->when($this->search, function ($query, $search): void {
                    $query->where(function ($q) use ($search): void {
                        $q->where('table_name', 'like', '%'.$search.'%')
                            ->orWhere('module_name', 'like', '%'.$search.'%');
                    });
                })
                ->orderBy('module_name')
                ->orderBy('table_name')
                ->paginate(25),
        ]);
    }
}
