<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Livewire\Concerns;

/**
 * Shared display formatting for cost and token count values.
 *
 * Used by both the provider management page and the setup flow to render
 * consistent model cost and token count displays.
 */
trait FormatsDisplayValues
{
    /**
     * Format a cost value for display (2 decimal places).
     */
    public function formatCost(?string $cost): string
    {
        if ($cost === null || $cost === '') {
            return '—';
        }

        return '$'.number_format((float) $cost, 2);
    }

    /**
     * Format a token count for display (e.g. 200000 → "200K", 1048576 → "1M").
     */
    public function formatTokenCount(?int $count): string
    {
        if ($count === null) {
            return '—';
        }

        $value = (float) $count;
        $suffix = '';

        if ($count >= 1_000_000) {
            $value = $count / 1_000_000;
            $suffix = 'M';
        } elseif ($count >= 1_000) {
            $value = $count / 1_000;
            $suffix = 'K';
        }

        return $suffix === ''
            ? (string) $count
            : rtrim(rtrim(number_format($value, 1), '0'), '.').$suffix;
    }
}
