<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Foundation\Livewire\Concerns;

trait ResetsPaginationOnSearch
{
    public function updatedSearch(): void
    {
        $this->resetPage();
    }
}
