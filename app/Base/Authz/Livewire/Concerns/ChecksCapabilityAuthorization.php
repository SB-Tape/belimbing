<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Authz\Livewire\Concerns;

use App\Base\Authz\Contracts\AuthorizationService;
use App\Base\Authz\DTO\Actor;
use App\Base\Authz\Enums\PrincipalType;
use Illuminate\Support\Facades\Session;

trait ChecksCapabilityAuthorization
{
    /**
     * Check if the current user has the given capability.
     *
     * Flashes a friendly error if denied.
     */
    protected function checkCapability(string $capability): bool
    {
        $authUser = auth()->user();

        $actor = new Actor(
            type: PrincipalType::HUMAN_USER,
            id: (int) $authUser->getAuthIdentifier(),
            companyId: $authUser->company_id !== null ? (int) $authUser->company_id : null,
        );

        $decision = app(AuthorizationService::class)->can($actor, $capability);

        if (! $decision->allowed) {
            Session::flash('error', __('You do not have permission to perform this action.'));

            return false;
        }

        return true;
    }
}
