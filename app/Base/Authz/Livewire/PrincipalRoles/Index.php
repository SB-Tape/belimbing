<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Authz\Livewire\PrincipalRoles;

use App\Base\Authz\Models\PrincipalRole;
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
        return view('livewire.admin.authz.principal-roles.index', [
            'assignments' => PrincipalRole::query()
                ->with('role')
                ->leftJoin('users', function ($join): void {
                    $join->on('base_authz_principal_roles.principal_id', '=', 'users.id')
                        ->where('base_authz_principal_roles.principal_type', '=', 'human_user');
                })
                ->leftJoin('companies', 'base_authz_principal_roles.company_id', '=', 'companies.id')
                ->select(
                    'base_authz_principal_roles.*',
                    'users.name as principal_name',
                    'users.email as principal_email',
                    'companies.name as company_name'
                )
                ->when($this->search, function ($query, $search): void {
                    $query->where(function ($q) use ($search): void {
                        $q->where('users.name', 'like', '%'.$search.'%')
                            ->orWhere('users.email', 'like', '%'.$search.'%')
                            ->orWhereHas('role', function ($rq) use ($search): void {
                                $rq->where('name', 'like', '%'.$search.'%');
                            });
                    });
                })
                ->orderByDesc('base_authz_principal_roles.created_at')
                ->paginate(25),
        ]);
    }
}
