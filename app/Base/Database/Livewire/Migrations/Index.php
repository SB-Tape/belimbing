<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Database\Livewire\Migrations;

use App\Base\Foundation\Livewire\Concerns\ResetsPaginationOnSearch;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResetsPaginationOnSearch;
    use WithPagination;

    public string $search = '';

    public function render(): \Illuminate\Contracts\View\View
    {
        $query = DB::table('migrations')
            ->when($this->search, function ($q, $search): void {
                $q->where('migration', 'like', '%'.$search.'%');
            })
            ->orderByDesc('batch')
            ->orderByDesc('id');

        $totalCount = DB::table('migrations')->count();
        $latestBatch = DB::table('migrations')->max('batch');

        return view('livewire.admin.system.migrations.index', [
            'migrations' => $query->paginate(25),
            'totalCount' => $totalCount,
            'latestBatch' => $latestBatch,
        ]);
    }
}
