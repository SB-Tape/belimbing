<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\AI\DTO;

/**
 * Result of a catalog sync operation.
 */
readonly class CatalogSyncResult
{
    public function __construct(
        public bool $updated,
        public string $etag,
        public int $providerCount,
        public int $modelCount,
    ) {}
}
