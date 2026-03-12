<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\Employee\Exceptions;

use App\Base\Foundation\Enums\BlbErrorCode;
use App\Base\Foundation\Exceptions\BlbInvariantViolationException;

/**
 * Thrown when an attempt is made to delete the system Agent (Lara).
 */
final class SystemEmployeeDeletionException extends BlbInvariantViolationException
{
    public function __construct()
    {
        parent::__construct(
            'Lara (the system Agent) cannot be deleted.',
            BlbErrorCode::SYSTEM_EMPLOYEE_DELETION_FORBIDDEN,
            ['employee_id' => 1],
        );
    }
}
