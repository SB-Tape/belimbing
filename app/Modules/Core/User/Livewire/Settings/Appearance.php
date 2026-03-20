<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\User\Livewire\Settings;

use Livewire\Component;

class Appearance extends Component
{
    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.profile.appearance');
    }
}
