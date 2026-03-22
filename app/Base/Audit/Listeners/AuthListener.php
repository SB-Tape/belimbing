<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Audit\Listeners;

use App\Base\Audit\DTO\RequestContext;
use App\Base\Audit\Services\AuditBuffer;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Captures Laravel auth events (login, logout, failed login)
 * and buffers them as audit action entries.
 */
class AuthListener
{
    public function __construct(
        private readonly AuditBuffer $buffer,
        private readonly RequestContext $context,
    ) {}

    /**
     * Handle a user login event.
     */
    public function handleLogin(Login $event): void
    {
        $this->bufferAuthAction('auth.login', $event->user->getAuthIdentifier());
    }

    /**
     * Handle a user logout event.
     */
    public function handleLogout(Logout $event): void
    {
        $this->bufferAuthAction('auth.logout', $event->user?->getAuthIdentifier());
    }

    /**
     * Handle a failed login event.
     */
    public function handleFailed(Failed $event): void
    {
        $now = now();

        $this->buffer->bufferAction([
            'company_id' => $this->context->companyId,
            'actor_type' => $this->context->actorType,
            'actor_id' => $this->context->actorId,
            'ip_address' => $this->context->ipAddress,
            'url' => $this->context->url,
            'user_agent' => $this->context->userAgent,
            'event' => 'auth.login.failed',
            'payload' => json_encode(['email' => $event->credentials['email'] ?? null]),
            'correlation_id' => $this->context->correlationId,
            'occurred_at' => $now,
            'created_at' => $now,
        ]);
    }

    /**
     * Buffer a standard auth action entry.
     */
    private function bufferAuthAction(string $event, mixed $actorId): void
    {
        $now = now();

        $this->buffer->bufferAction([
            'company_id' => $this->context->companyId,
            'actor_type' => $this->context->actorType,
            'actor_id' => $actorId,
            'actor_role' => $this->context->actorRole,
            'ip_address' => $this->context->ipAddress,
            'url' => $this->context->url,
            'user_agent' => $this->context->userAgent,
            'event' => $event,
            'payload' => null,
            'correlation_id' => $this->context->correlationId,
            'occurred_at' => $now,
            'created_at' => $now,
        ]);
    }
}
