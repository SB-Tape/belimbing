<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Authz\Enums;

enum PrincipalType: string
{
    case HUMAN_USER = 'human_user';
    case AGENT = 'agent';
    case CONSOLE = 'console';
    case SCHEDULER = 'scheduler';
    case QUEUE = 'queue';

    /**
     * Whether this principal type represents a process rather than a user or agent.
     */
    public function isProcess(): bool
    {
        return match ($this) {
            self::CONSOLE, self::SCHEDULER, self::QUEUE => true,
            default => false,
        };
    }
}
