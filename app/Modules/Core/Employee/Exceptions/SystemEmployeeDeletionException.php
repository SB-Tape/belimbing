<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\Employee\Exceptions;

use LogicException;

/**
 * Thrown when an attempt is made to delete the system Digital Worker (Lara).
 */
final class SystemEmployeeDeletionException extends LogicException
{
    public function __construct()
    {
        parent::__construct('Lara (the system Digital Worker) cannot be deleted.');
    }
}
