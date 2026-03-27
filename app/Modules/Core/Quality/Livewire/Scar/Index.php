<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\Quality\Livewire\Scar;

use App\Modules\Core\Quality\Livewire\StatusFilteredSearchableIndex;
use App\Modules\Core\Quality\Models\Scar;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Index extends StatusFilteredSearchableIndex
{
    protected const string VIEW_NAME = 'livewire.quality.scar.index';

    protected const string VIEW_DATA_KEY = 'scars';

    protected const string SORT_COLUMN = 'created_at';

    /**
     * @var list<string>
     */
    protected const array SEARCH_COLUMNS = ['scar_no', 'supplier_name', 'product_name'];

    public string $search = '';

    public function statusVariant(string $status): string
    {
        return match ($status) {
            'draft' => 'default',
            'issued' => 'info',
            'acknowledged' => 'accent',
            'containment_submitted' => 'accent',
            'under_investigation' => 'warning',
            'response_submitted' => 'accent',
            'under_review' => 'accent',
            'action_required' => 'warning',
            'verification_pending' => 'info',
            'closed' => 'default',
            'rejected' => 'danger',
            'cancelled' => 'default',
            default => 'default',
        };
    }

    protected function baseQuery(): EloquentBuilder
    {
        return Scar::query()
            ->with('ncr', 'issueOwner');
    }
}
