<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Foundation\Livewire;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

abstract class TableSearchablePaginatedList extends SearchablePaginatedList
{
    protected const string TABLE = '';

    protected function query(): EloquentBuilder|QueryBuilder
    {
        return DB::table(static::TABLE);
    }
}
