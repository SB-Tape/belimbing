<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Queue\Livewire\FailedJobs;

use App\Base\Foundation\Livewire\Concerns\ResetsPaginationOnSearch;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResetsPaginationOnSearch;
    use WithPagination;

    public string $search = '';

    public function retryJob(string $uuid): void
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);
    }

    public function retryAll(): void
    {
        Artisan::call('queue:retry', ['id' => ['all']]);
    }

    public function deleteJob(int $id): void
    {
        DB::table('failed_jobs')->where('id', $id)->delete();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.admin.system.failed-jobs.index', [
            'failedJobs' => DB::table('failed_jobs')
                ->when($this->search, function ($query, $search): void {
                    $query->where(function ($q) use ($search): void {
                        $q->where('queue', 'like', '%'.$search.'%')
                            ->orWhere('uuid', 'like', '%'.$search.'%')
                            ->orWhere('exception', 'like', '%'.$search.'%');
                    });
                })
                ->orderByDesc('failed_at')
                ->paginate(25),
        ]);
    }
}
