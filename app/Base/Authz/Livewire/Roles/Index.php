<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Authz\Livewire\Roles;

use App\Base\Authz\Contracts\AuthorizationService;
use App\Base\Authz\DTO\Actor;
use App\Base\Authz\Models\Role;
use App\Base\Foundation\Livewire\Concerns\ResetsPaginationOnSearch;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResetsPaginationOnSearch;
    use WithPagination;

    public string $search = '';

    public function render(): \Illuminate\Contracts\View\View
    {
        $authUser = auth()->user();

        $authActor = Actor::forUser($authUser);

        $canCreate = app(AuthorizationService::class)
            ->can($authActor, 'admin.role.create')
            ->allowed;

        return view('livewire.admin.roles.index', [
            'canCreate' => $canCreate,
            'roles' => Role::query()
                ->with('company')
                ->withCount('capabilities', 'principalRoles')
                ->when($this->search, function ($query, $search): void {
                    $query->where(function ($q) use ($search): void {
                        $q->where('name', 'like', '%'.$search.'%')
                            ->orWhere('code', 'like', '%'.$search.'%')
                            ->orWhere('description', 'like', '%'.$search.'%');
                    });
                })
                ->orderByDesc('is_system')
                ->orderBy('name')
                ->paginate(10),
        ]);
    }
}
