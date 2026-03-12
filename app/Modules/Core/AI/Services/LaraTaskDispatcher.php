<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

class LaraTaskDispatcher
{
    public function __construct(
        private readonly LaraCapabilityMatcher $capabilityMatcher,
    ) {}

    /**
     * Dispatch a task to an accessible Agent.
     *
     * @return array{dispatch_id: string, status: string, employee_id: int, employee_name: string, task: string, acting_for_user_id: int, created_at: string}
     *
     * @throws AuthorizationException
     */
    public function dispatchForCurrentUser(int $employeeId, string $task): array
    {
        $agent = $this->capabilityMatcher->findAccessibleAgentById($employeeId);
        $actingForUserId = auth()->id();

        if ($agent === null || ! is_int($actingForUserId)) {
            throw new AuthorizationException(__('Unauthorized Agent dispatch target.'));
        }

        return [
            'dispatch_id' => 'agent_dispatch_'.Str::random(12),
            'status' => 'queued',
            'employee_id' => $agent['employee_id'],
            'employee_name' => $agent['name'],
            'task' => trim($task),
            'acting_for_user_id' => $actingForUserId,
            'created_at' => now()->toIso8601String(),
        ];
    }
}
