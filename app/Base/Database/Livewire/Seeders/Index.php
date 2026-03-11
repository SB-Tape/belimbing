<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Database\Livewire\Seeders;

use App\Base\Database\Models\SeederRegistry;
use App\Base\Foundation\Livewire\Concerns\ResetsPaginationOnSearch;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResetsPaginationOnSearch;
    use WithPagination;

    public string $search = '';

    /**
     * Extract the short class name (basename) from a fully qualified class name.
     */
    public function shortClass(string $fqcn): string
    {
        return class_basename($fqcn);
    }

    /**
     * Map a seeder status to a badge variant.
     */
    public function statusVariant(string $status): string
    {
        return match ($status) {
            SeederRegistry::STATUS_COMPLETED => 'success',
            SeederRegistry::STATUS_FAILED => 'danger',
            SeederRegistry::STATUS_RUNNING => 'warning',
            default => 'default',
        };
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.admin.system.seeders.index', [
            'seeders' => SeederRegistry::query()
                ->when($this->search, function ($query, $search): void {
                    $query->where(function ($q) use ($search): void {
                        $q->where('seeder_class', 'like', '%'.$search.'%')
                            ->orWhere('module_name', 'like', '%'.$search.'%');
                    });
                })
                ->orderBy('migration_file')
                ->orderBy('seeder_class')
                ->paginate(25),
        ]);
    }
}
