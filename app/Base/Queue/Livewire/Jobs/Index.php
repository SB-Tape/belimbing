<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Queue\Livewire\Jobs;

use App\Base\Foundation\Livewire\TableSearchablePaginatedList;
use Illuminate\Support\Facades\DB;

class Index extends TableSearchablePaginatedList
{
    protected const string TABLE = 'jobs';

    protected const string VIEW_NAME = 'livewire.admin.system.jobs.index';

    protected const string VIEW_DATA_KEY = 'jobs';

    protected const string SORT_COLUMN = 'id';

    protected const array SEARCH_COLUMNS = ['queue', 'payload'];

    public function deleteJob(int $id): void
    {
        DB::table('jobs')->where('id', $id)->delete();
    }
}
