<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Queue\Livewire\JobBatches;

use App\Base\Foundation\Livewire\TableSearchablePaginatedList;
use Illuminate\Support\Facades\DB;

class Index extends TableSearchablePaginatedList
{
    protected const string TABLE = 'job_batches';

    protected const string VIEW_NAME = 'livewire.admin.system.job-batches.index';

    protected const string VIEW_DATA_KEY = 'batches';

    protected const string SORT_COLUMN = 'created_at';

    protected const array SEARCH_COLUMNS = ['name', 'id'];

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
}
