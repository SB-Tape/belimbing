<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Services\Browser;

/**
 * Manages a pool of headless browser contexts with per-company concurrency limits.
 *
 * Each DW session gets an isolated browser context. Currently tracks contexts
 * in-memory; future versions will persist context state for cross-process
 * coordination.
 */
class BrowserPoolManager
{
    /** @var array<string, array{company_id: int, session_id: string, created_at: float}> */
    private array $activeContexts = [];

    public function __construct(
        private readonly BrowserContextFactory $contextFactory,
    ) {}

    /**
     * Acquire a browser context for the given company and session.
     *
     * Returns the context ID on success, or false if the browser tool is
     * disabled, Playwright is unavailable, or the per-company limit is reached.
     *
     * @param  int  $companyId  Company requesting the context
     * @param  string  $sessionId  DW session identifier
     */
    public function acquireContext(int $companyId, string $sessionId): string|false
    {
        if (! config('ai.tools.browser.enabled', false)) {
            return false;
        }

        if (! $this->contextFactory->isAvailable()) {
            return false;
        }

        // Return existing context for this company+session pair.
        foreach ($this->activeContexts as $contextId => $entry) {
            if ($entry['company_id'] === $companyId && $entry['session_id'] === $sessionId) {
                return $contextId;
            }
        }

        $maxContexts = config('ai.tools.browser.max_contexts_per_company', 3);

        if ($this->getActiveContextCount($companyId) >= $maxContexts) {
            return false;
        }

        $contextId = $this->contextFactory->createContextId($companyId, $sessionId);

        $this->activeContexts[$contextId] = [
            'company_id' => $companyId,
            'session_id' => $sessionId,
            'created_at' => microtime(true),
        ];

        return $contextId;
    }

    /**
     * Release a browser context, freeing the concurrency slot.
     *
     * @param  string  $contextId  Context ID to release
     */
    public function releaseContext(string $contextId): void
    {
        unset($this->activeContexts[$contextId]);
    }

    /**
     * Count active browser contexts for a company.
     *
     * @param  int  $companyId  Company to count contexts for
     */
    public function getActiveContextCount(int $companyId): int
    {
        $count = 0;

        foreach ($this->activeContexts as $entry) {
            if ($entry['company_id'] === $companyId) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Check whether a context is currently active.
     *
     * @param  string  $contextId  Context ID to check
     */
    public function hasContext(string $contextId): bool
    {
        return isset($this->activeContexts[$contextId]);
    }

    /**
     * Check whether the browser pool is available for use.
     *
     * Returns true only when the browser tool is enabled and Playwright is installed.
     */
    public function isAvailable(): bool
    {
        return config('ai.tools.browser.enabled', false) && $this->contextFactory->isAvailable();
    }
}
