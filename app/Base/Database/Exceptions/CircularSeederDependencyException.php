<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Database\Exceptions;

use RuntimeException;

/**
 * Thrown when dev seeders declare a circular dependency graph.
 */
final class CircularSeederDependencyException extends RuntimeException
{
    public static function forClasses(array $seederClasses): self
    {
        return new self(
            'Circular dependency detected among dev seeders: '.implode(', ', $seederClasses)
        );
    }
}
