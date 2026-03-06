<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\Company\Exceptions;

use LogicException;

/**
 * Thrown when an attempt is made to delete the licensee company (id=1).
 */
final class LicenseeCompanyDeletionException extends LogicException
{
    public function __construct()
    {
        parent::__construct('The licensee company (id=1) cannot be deleted.');
    }
}
