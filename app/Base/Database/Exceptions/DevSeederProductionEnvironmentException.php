<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Database\Exceptions;

use RuntimeException;

/**
 * Thrown when a dev seeder is run outside the local environment.
 */
final class DevSeederProductionEnvironmentException extends RuntimeException
{
    public static function forEnvironment(string $currentEnvironment): self
    {
        return new self(
            'Dev seeders may only run when APP_ENV=local. Current: '.$currentEnvironment
        );
    }
}
