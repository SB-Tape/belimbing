<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Queue\Livewire\JobBatches;

use App\Base\Foundation\Livewire\Concerns\ResetsPaginationOnSearch;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResetsPaginationOnSearch;
    use WithPagination;

    public string $search = '';

    public function cancelBatch(string $id): void
    {
        DB::table('job_batches')
            ->where('id', $id)
            ->whereNull('cancelled_at')
            ->whereNull('finished_at')
            ->update(['cancelled_at' => now()->timestamp]);
    }

    public function pruneCompleted(): void
    {
        DB::table('job_batches')
            ->whereNotNull('finished_at')
            ->delete();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.admin.system.job-batches.index', [
            'batches' => DB::table('job_batches')
                ->when($this->search, function ($query, $search): void {
                    $query->where(function ($q) use ($search): void {
                        $q->where('name', 'like', '%'.$search.'%')
                            ->orWhere('id', 'like', '%'.$search.'%');
                    });
                })
                ->orderByDesc('created_at')
                ->paginate(25),
        ]);
    }
}
