<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Queue\Livewire\Jobs;

use App\Base\Foundation\Livewire\Concerns\ResetsPaginationOnSearch;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResetsPaginationOnSearch;
    use WithPagination;

    public string $search = '';

    public function deleteJob(int $id): void
    {
        DB::table('jobs')->where('id', $id)->delete();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.admin.system.jobs.index', [
            'jobs' => DB::table('jobs')
                ->when($this->search, function ($query, $search): void {
                    $query->where(function ($q) use ($search): void {
                        $q->where('queue', 'like', '%'.$search.'%')
                            ->orWhere('payload', 'like', '%'.$search.'%');
                    });
                })
                ->orderByDesc('id')
                ->paginate(25),
        ]);
    }
}
